<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsArticleEventServiceInterface;
use App\Contracts\News\NewsCalendarEventMoveServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\DTOs\News\NewsCalendarEventMoveResultDTO;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleEvent;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use App\Support\News\SampleCalendarEvents;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class NewsCalendarEventMoveService implements NewsCalendarEventMoveServiceInterface
{
    private const LEGACY_SOURCE = 'news-events';

    /** A multi-day entry becomes one session per day, up to this many. */
    private const MAX_SESSIONS = 14;

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflow,
        private readonly NewsArticleCmsServiceInterface $articles,
        private readonly FormDefinitionServiceInterface $forms,
    ) {}

    public function move(string $actor, bool $write): NewsCalendarEventMoveResultDTO
    {
        $userId = $this->actorId($actor);
        $categoryId = NewsCategory::query()->where('slug', 'events')->value('id');
        if ($categoryId === null) {
            throw new RuntimeException('The Events news category is missing; run the migrations first.');
        }

        $published = $this->cmsWorkflow->getPublishedPayload('news.events') ?? [];
        $samples = SampleCalendarEvents::untouchedIds($published);
        $alreadyMoved = NewsArticleEvent::query()->whereNotNull('legacy_event_id')->pluck('news_article_id', 'legacy_event_id')->all();
        $result = ['moved' => [], 'alreadyMoved' => [], 'samples' => [], 'failed' => []];

        foreach ($this->publishedEvents($published) as $id => $event) {
            $title = $this->title($event, 'en') ?? $this->title($event, 'ar') ?? $id;

            if (in_array($id, $samples, true)) {
                $result['samples'][] = $id;

                continue;
            }
            if (isset($alreadyMoved[$id])) {
                $result['alreadyMoved'][] = ['id' => $id, 'title' => $title, 'articleId' => (int) $alreadyMoved[$id]];

                continue;
            }

            $payload = $this->articlePayload($event, (int) $categoryId);
            if ($payload === null) {
                $result['failed'][] = ['id' => $id, 'title' => $title, 'errors' => ['The event has no valid start date.']];

                continue;
            }

            $registrations = $this->legacyRegistrations($id);
            if (! $write) {
                $result['moved'][] = ['id' => $id, 'title' => $title, 'articleId' => null, 'sessions' => count($payload['event']['sessions']), 'registrations' => count($registrations)];

                continue;
            }

            try {
                $articleId = $this->publishArticle($id, $payload, $userId, $registrations);
                $result['moved'][] = ['id' => $id, 'title' => $title, 'articleId' => $articleId, 'sessions' => count($payload['event']['sessions']), 'registrations' => count($registrations)];
            } catch (InvalidArgumentException $exception) {
                $result['failed'][] = ['id' => $id, 'title' => $title, 'errors' => explode("\n", $exception->getMessage())];
            }
        }

        return new NewsCalendarEventMoveResultDTO(
            moved: $result['moved'],
            alreadyMoved: $result['alreadyMoved'],
            samples: $result['samples'],
            failed: $result['failed'],
            written: $write,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<DynamicFormSubmission>  $registrations
     */
    private function publishArticle(string $legacyId, array $payload, int $userId, array $registrations): int
    {
        $prepared = $this->articles->prepareDraft(new NewsArticleCmsDataDTO(null, $payload), $userId);
        $articleId = (int) $prepared->articleId;
        $targetKey = (string) $prepared->targetKey;
        $errors = $this->articles->publishErrors($targetKey, $prepared->payload);

        if ($errors !== []) {
            // Nothing was saved for the shell article yet; remove it so a re-run starts clean.
            NewsArticle::query()->whereKey($articleId)->forceDelete();

            throw new InvalidArgumentException(collect($errors)->flatten()->implode("\n"));
        }

        try {
            $this->cmsWorkflow->saveDraft($targetKey, $prepared->payload, $userId);
            $this->cmsWorkflow->publish($targetKey, $userId);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Publishing failed: '.$exception->getMessage(), 0, $exception);
        }

        NewsArticleEvent::query()->where('news_article_id', $articleId)->update(['legacy_event_id' => $legacyId]);

        // Registrations sent for the old entry now count against the article's seats.
        foreach ($registrations as $submission) {
            $data = $submission->payload_json;
            $data['_context'] = array_merge($data['_context'], [
                'source' => NewsArticleEventServiceInterface::REGISTRATION_SOURCE,
                'event_id' => (string) $articleId,
                'legacy_event_id' => $legacyId,
            ]);
            $submission->forceFill(['payload_json' => $data])->save();
        }

        return $articleId;
    }

    /**
     * Published entries keyed by ID with each language's record. An entry in
     * the past list stays past even if its date was edited later.
     *
     * @param  array<string, mixed>  $published
     * @return array<string, array{ar: ?array<string, mixed>, en: ?array<string, mixed>, past: bool}>
     */
    private function publishedEvents(array $published): array
    {
        $events = [];

        foreach (['ar', 'en'] as $locale) {
            foreach (['upcoming', 'past'] as $list) {
                $records = $published['translations'][$locale][$list] ?? null;
                foreach (is_array($records) ? $records : [] as $record) {
                    $id = is_array($record) ? trim((string) ($record['id'] ?? '')) : '';
                    if ($id === '' || trim((string) ($record['title'] ?? '')) === '') {
                        continue;
                    }
                    $events[$id] ??= ['ar' => null, 'en' => null, 'past' => false];
                    $events[$id][$locale] ??= $record;
                    $events[$id]['past'] = $events[$id]['past'] || $list === 'past';
                }
            }
        }

        return $events;
    }

    /**
     * @param  array{ar: ?array<string, mixed>, en: ?array<string, mixed>, past: bool}  $event
     * @return array<string, mixed>|null
     */
    private function articlePayload(array $event, int $categoryId): ?array
    {
        $sessions = $this->sessions($event);
        if ($sessions === []) {
            return null;
        }

        $base = $event['en'] ?? $event['ar'] ?? [];
        $formId = is_string($base['formId'] ?? null) ? trim($base['formId']) : '';
        $registration = ! $event['past'] && $formId !== '' && $this->forms->isEnabled($formId);
        $capacity = is_numeric($base['capacity'] ?? null) && (int) $base['capacity'] > 0 ? (int) $base['capacity'] : null;
        $registered = is_numeric($base['registered'] ?? null) ? max(0, (int) $base['registered']) : 0;
        $image = $this->image(is_string($base['image'] ?? null) ? $base['image'] : null);

        $translations = [];
        $seoMeta = [];
        foreach (['ar', 'en'] as $locale) {
            $record = $event[$locale] ?? $base;
            $title = (string) ($this->title($event, $locale) ?? '');
            $summary = trim(strip_tags((string) ($record['summary'] ?? '')));
            $translations[] = [
                'locale' => $locale,
                'title' => $title,
                'excerpt' => $summary !== '' ? $summary : null,
                'body' => $this->body($record, $title),
            ];
            if ($image['url'] !== null) {
                $seoMeta[] = ['locale' => $locale, 'og_image_url' => $image['url'], 'robots' => 'index,follow'];
            }
        }

        return [
            'news_category_id' => $categoryId,
            'cover_media_id' => $image['mediaId'],
            'is_enabled' => true,
            'is_featured' => (bool) ($base['featured'] ?? false),
            'translations' => $translations,
            'attachments' => $this->gallery($base),
            'seoMeta' => $seoMeta,
            'event' => [
                'kind' => $this->kind($base),
                'requires_registration' => $registration,
                'show_on_calendar' => true,
                'formId' => $registration ? $formId : null,
                'capacity' => $registration ? $capacity : null,
                'prior_registrations' => $registration ? ($capacity === null ? $registered : min($capacity, $registered)) : 0,
                'location_ar' => $this->text($event['ar'] ?? null, 'location') ?? $this->text($base, 'location'),
                'location_en' => $this->text($event['en'] ?? null, 'location') ?? $this->text($base, 'location'),
                'results_ar' => $this->text($event['ar'] ?? null, 'results'),
                'results_en' => $this->text($event['en'] ?? null, 'results'),
                'sessions' => $sessions,
                'speakers' => $this->speakers($event),
            ],
        ];
    }

    /**
     * One session for an entry within a day; one per day, with the same
     * hours, for an entry spanning several days.
     *
     * @param  array{ar: ?array<string, mixed>, en: ?array<string, mixed>, past: bool}  $event
     * @return list<array<string, mixed>>
     */
    private function sessions(array $event): array
    {
        $base = $event['en'] ?? $event['ar'] ?? [];
        $timezone = (string) config('app.admin_timezone', 'Asia/Damascus');
        $start = $this->moment($base['startsAt'] ?? null, $timezone);
        if ($start === null) {
            return [];
        }

        $end = $this->moment($base['endsAt'] ?? null, $timezone);
        $end = $end !== null && $end->greaterThan($start) ? $end : null;
        $startTime = $start->format('H:i');
        $endTime = $end?->format('H:i');
        $endTime = $endTime !== null && $endTime > $startTime ? $endTime : null;
        $lastDay = $end?->startOfDay() ?? $start->startOfDay();
        $sessions = [];

        for ($day = $start->startOfDay(); $day->lessThanOrEqualTo($lastDay) && count($sessions) < self::MAX_SESSIONS; $day = $day->addDay()) {
            $sessions[] = ['date' => $day->format('Y-m-d'), 'starts_at' => $startTime, 'ends_at' => $endTime, 'speakers' => []];
        }

        return $sessions;
    }

    private function moment(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '' || strtotime($value) === false) {
            return null;
        }

        return CarbonImmutable::parse(trim($value), $timezone)->setTimezone($timezone);
    }

    /**
     * @param  array{ar: ?array<string, mixed>, en: ?array<string, mixed>, past: bool}  $event
     * @return list<array<string, mixed>>
     */
    private function speakers(array $event): array
    {
        $ar = is_array($event['ar']['speakers'] ?? null) ? array_values($event['ar']['speakers']) : [];
        $en = is_array($event['en']['speakers'] ?? null) ? array_values($event['en']['speakers']) : [];
        $speakers = [];

        for ($index = 0; $index < max(count($ar), count($en)); $index++) {
            $arSpeaker = is_array($ar[$index] ?? null) ? $ar[$index] : [];
            $enSpeaker = is_array($en[$index] ?? null) ? $en[$index] : [];
            $nameAr = $this->text($arSpeaker, 'name') ?? $this->text($enSpeaker, 'name');
            $nameEn = $this->text($enSpeaker, 'name') ?? $nameAr;
            if ($nameAr === null || $nameEn === null) {
                continue;
            }
            $speakers[] = [
                'source' => 'external',
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'title_ar' => $this->text($arSpeaker, 'title') ?? $this->text($enSpeaker, 'title'),
                'title_en' => $this->text($enSpeaker, 'title') ?? $this->text($arSpeaker, 'title'),
            ];
        }

        return $speakers;
    }

    /**
     * The page list's own category becomes the event type when it says so.
     *
     * @param  array<string, mixed>  $record
     */
    private function kind(array $record): string
    {
        $category = mb_strtolower(trim((string) ($record['categoryId'] ?? '')).' '.trim((string) ($record['categoryLabel'] ?? '')));

        return match (true) {
            str_contains($category, 'conferen') || str_contains($category, 'مؤتمر') => 'conference',
            str_contains($category, 'seminar') || str_contains($category, 'ندوة') => 'seminar',
            default => 'event',
        };
    }

    /** @param array<string, mixed> $record */
    private function body(array $record, string $title): string
    {
        $summary = trim(strip_tags((string) ($record['summary'] ?? '')));
        $html = '<p>'.e($summary !== '' ? $summary : $title).'</p>';

        $highlights = array_values(array_filter(
            array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', is_array($record['highlights'] ?? null) ? $record['highlights'] : []),
            fn (string $item): bool => $item !== '',
        ));
        if ($highlights !== []) {
            $html .= '<ul>'.implode('', array_map(fn (string $item): string => '<li>'.e($item).'</li>', $highlights)).'</ul>';
        }

        $participants = $this->text($record, 'participants');
        if ($participants !== null) {
            $html .= '<p>'.e($participants).'</p>';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<array<string, mixed>>
     */
    private function gallery(array $record): array
    {
        $attachments = [];
        foreach (is_array($record['gallery'] ?? null) ? $record['gallery'] : [] as $index => $path) {
            $url = is_string($path) ? $this->absoluteUrl($path) : null;
            if ($url !== null) {
                $attachments[] = ['kind' => 'image', 'legacy_path' => $url, 'sort_order' => $index];
            }
        }

        return $attachments;
    }

    /**
     * The media library item the image came from, or its address.
     *
     * @return array{mediaId: ?int, url: ?string}
     */
    private function image(?string $value): array
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return ['mediaId' => null, 'url' => null];
        }

        $path = ltrim((string) preg_replace('#^/?storage/#', '', (string) (parse_url($value, PHP_URL_PATH) ?: $value)), '/');
        $media = MediaAsset::query()->where('path', $path)->orWhere('webp_path', $path)->first();
        if ($media instanceof MediaAsset && str_starts_with((string) $media->mime_type, 'image/')) {
            return ['mediaId' => (int) $media->getKey(), 'url' => null];
        }

        return ['mediaId' => null, 'url' => $this->absoluteUrl($value)];
    }

    private function absoluteUrl(string $value): ?string
    {
        $resolved = MediaUrlResolver::resolve(trim($value));
        if ($resolved === null || $resolved === '') {
            return null;
        }

        return str_starts_with($resolved, '/') ? url($resolved) : $resolved;
    }

    /** @return list<DynamicFormSubmission> */
    private function legacyRegistrations(string $legacyId): array
    {
        return DynamicFormSubmission::query()
            ->where('payload_json', 'like', '%"event_id":'.json_encode($legacyId).'%')
            ->get()
            ->filter(fn (DynamicFormSubmission $submission): bool => is_array($submission->payload_json['_context'] ?? null)
                && ($submission->payload_json['_context']['source'] ?? null) === self::LEGACY_SOURCE
                && ($submission->payload_json['_context']['event_id'] ?? null) === $legacyId)
            ->values()
            ->all();
    }

    /** @param array{ar: ?array<string, mixed>, en: ?array<string, mixed>, past: bool} $event */
    private function title(array $event, string $locale): ?string
    {
        return $this->text($event[$locale] ?? null, 'title')
            ?? $this->text($event[$locale === 'ar' ? 'en' : 'ar'] ?? null, 'title');
    }

    /** @param array<string, mixed>|null $record */
    private function text(?array $record, string $field): ?string
    {
        $value = is_array($record) && is_string($record[$field] ?? null) ? trim(strip_tags($record[$field])) : '';

        return $value !== '' ? $value : null;
    }

    private function actorId(string $actor): int
    {
        $query = User::query();
        $user = ctype_digit($actor) ? $query->find((int) $actor) : $query->where('email', $actor)->first();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('No user matches "'.$actor.'". Pass a user id or email with --user.');
        }

        return (int) $user->getKey();
    }
}
