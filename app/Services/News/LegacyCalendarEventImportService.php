<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\LegacyCalendarEventImportServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\DTOs\News\LegacyCalendarEventImportResultDTO;
use App\Models\User\User;
use App\Support\News\SampleCalendarEvents;
use InvalidArgumentException;

/**
 * The old homepage calendar listed events (ser=10 items) that never reached
 * the new Events page. They are added there as past events, through the CMS
 * draft / publish workflow, so the calendar and the past list show them.
 */
final class LegacyCalendarEventImportService implements LegacyCalendarEventImportServiceInterface
{
    private const TARGET = 'news.events';

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly NewsServiceInterface $newsService,
    ) {}

    public function import(array $source, string $actor, bool $write): LegacyCalendarEventImportResultDTO
    {
        $userId = $this->actorId($actor);
        $events = array_values(array_filter(is_array($source['events'] ?? null) ? $source['events'] : [], 'is_array'));

        if ($events === []) {
            throw new InvalidArgumentException('The source file lists no events.');
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload(self::TARGET, $userId);
        $hasPublished = $this->cmsWorkflowService->getPublishedPayload(self::TARGET) !== null;
        $payload = is_array($draftPayload) ? $draftPayload : $this->newsService->getEditablePayload(self::TARGET);
        $expectedVersion = $this->cmsWorkflowService->latestEditableDraftVersion(self::TARGET, $userId);
        $added = [];
        $present = [];
        $completed = [];
        $samples = $this->untouchedSampleIds($payload);

        foreach (['ar', 'en'] as $locale) {
            $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];

            // Never published: the editable payload is the sample page, whose events are not real.
            if (! is_array($draftPayload) && ! $hasPublished) {
                $content['upcoming'] = [];
                $content['past'] = [];
            }

            $content['categories'] = $this->withCategories($content['categories'] ?? [], $events, $locale);
            $categoryLabels = array_column($content['categories'], 'label', 'id');
            // The sample events the editor started from are not real events.
            $notSample = static fn (mixed $event): bool => ! is_array($event) || ! in_array((string) ($event['id'] ?? ''), $samples, true);
            $upcoming = array_values(array_filter(is_array($content['upcoming'] ?? null) ? $content['upcoming'] : [], $notSample));
            $past = array_values(array_filter(is_array($content['past'] ?? null) ? $content['past'] : [], $notSample));

            foreach ($events as $event) {
                $id = trim((string) ($event['id'] ?? ''));
                $text = is_array($event[$locale] ?? null) ? $event[$locale] : [];

                if ($id === '' || trim((string) ($text['title'] ?? '')) === '' || strtotime((string) ($event['startsAt'] ?? '')) === false) {
                    throw new InvalidArgumentException('Event "'.$id.'" needs an id, a start date and a '.$locale.' title.');
                }

                $categoryId = is_array($event['category'] ?? null) ? (string) ($event['category']['id'] ?? '') : '';
                // What the admin's event form requires to publish.
                $required = [
                    'timeLabel' => (string) ($text['timeLabel'] ?? ''),
                    'location' => (string) ($text['location'] ?? ''),
                    'categoryId' => $categoryId,
                    'categoryLabel' => (string) ($categoryLabels[$categoryId] ?? ''),
                    'image' => (string) ($event['image'] ?? ''),
                ];

                $found = false;

                foreach (['upcoming', 'past'] as $list) {
                    foreach (${$list} as $index => $record) {
                        if (! is_array($record) || (string) ($record['id'] ?? '') !== $id) {
                            continue;
                        }

                        $found = true;

                        // Fill only what is missing; whatever an editor wrote stays.
                        foreach ($required as $field => $value) {
                            if (trim((string) ($record[$field] ?? '')) === '' && $value !== '') {
                                ${$list}[$index][$field] = $value;
                                $completed[$id] = $id;
                            }
                        }
                    }
                }

                if ($found) {
                    $present[$id] = $id;

                    continue;
                }

                $past[] = [
                    'id' => $id,
                    'title' => trim((string) $text['title']),
                    'summary' => trim((string) ($text['summary'] ?? '')),
                    'startsAt' => (string) $event['startsAt'],
                    'endsAt' => is_string($event['endsAt'] ?? null) && $event['endsAt'] !== '' ? $event['endsAt'] : null,
                    'dateLabel' => (string) ($text['dateLabel'] ?? ''),
                    ...$required,
                    'participants' => '',
                    'highlights' => [],
                    'speakers' => [],
                    'results' => '',
                    'gallery' => [],
                ];
                $added[$id] = $id;
            }

            usort($past, static fn (array $a, array $b): int => strcmp((string) ($b['startsAt'] ?? ''), (string) ($a['startsAt'] ?? '')));
            $content['upcoming'] = $upcoming;
            $content['past'] = $past;
            $payload['translations'][$locale] = $content;
        }

        $written = false;
        $published = false;

        if ($write && ($added !== [] || $completed !== [] || $samples !== [])) {
            $this->cmsWorkflowService->saveDraft(self::TARGET, $payload, $userId, $expectedVersion);
            $written = true;
            // An editor's unfinished draft is not published along with the import.
            $published = ! is_array($draftPayload) && $this->cmsWorkflowService->publish(self::TARGET, $userId);
        }

        return new LegacyCalendarEventImportResultDTO(
            added: array_values($added),
            present: array_values(array_diff($present, $added)),
            completed: array_values(array_diff($completed, $added)),
            removedSamples: $samples,
            incomplete: $this->incompleteEvents($payload),
            written: $written,
            published: $published,
            hadPendingDraft: is_array($draftPayload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function untouchedSampleIds(array $payload): array
    {
        return SampleCalendarEvents::untouchedIds($payload);
    }

    /**
     * Events the events editor will refuse to publish, with the fields they lack.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function incompleteEvents(array $payload): array
    {
        $report = [];

        foreach (['ar', 'en'] as $locale) {
            foreach (['upcoming', 'past'] as $list) {
                foreach (is_array($payload['translations'][$locale][$list] ?? null) ? $payload['translations'][$locale][$list] : [] as $event) {
                    if (! is_array($event)) {
                        continue;
                    }

                    $missing = array_keys(array_filter(
                        ['title' => 0, 'summary' => 0, 'startsAt' => 0, 'dateLabel' => 0, 'timeLabel' => 0, 'location' => 0, 'categoryId' => 0, 'image' => 0],
                        static fn (int $unused, string $field): bool => trim((string) ($event[$field] ?? '')) === '',
                        ARRAY_FILTER_USE_BOTH,
                    ));

                    if ($missing !== []) {
                        $report[] = $list.' '.(string) ($event['id'] ?? '?').' "'.(string) ($event['title'] ?? '').'" ('.$locale.'): '.implode(', ', $missing);
                    }
                }
            }
        }

        return $report;
    }

    /**
     * The events' categories, added to the page's category list when missing.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array{id: string, label: string}>
     */
    private function withCategories(mixed $categories, array $events, string $locale): array
    {
        $list = array_values(array_filter(is_array($categories) ? $categories : [], static fn (mixed $category): bool => is_array($category) && is_string($category['id'] ?? null) && is_string($category['label'] ?? null)));
        $ids = array_column($list, 'id');

        foreach ($events as $event) {
            $category = is_array($event['category'] ?? null) ? $event['category'] : [];
            $id = trim((string) ($category['id'] ?? ''));
            $label = trim((string) ($category[$locale] ?? ''));

            if ($id !== '' && $label !== '' && ! in_array($id, $ids, true)) {
                $list[] = ['id' => $id, 'label' => $label];
                $ids[] = $id;
            }
        }

        return $list;
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
