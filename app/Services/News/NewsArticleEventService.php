<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\News\NewsArticleEventCmsServiceInterface;
use App\Contracts\News\NewsArticleEventServiceInterface;
use App\DTOs\News\NewsArticleEventDTO;
use App\DTOs\News\NewsArticleEventSessionDTO;
use App\DTOs\News\NewsArticleEventSpeakerDTO;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleTranslation;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use App\Support\MediaUrlResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class NewsArticleEventService implements NewsArticleEventServiceInterface
{
    public function __construct(
        private readonly NewsArticleEventCmsServiceInterface $eventCms,
        private readonly FormDefinitionServiceInterface $forms,
    ) {}

    public function forArticle(int $articleId, string $locale): ?NewsArticleEventDTO
    {
        $payload = $this->eventCms->storedPayload($articleId);
        if ($payload === null) {
            return null;
        }

        $article = NewsArticle::query()->with('translations')->find($articleId);

        return $article instanceof NewsArticle
            ? $this->map($payload, $articleId, $this->articleTitle($article, $locale), $locale, false)
            : null;
    }

    public function fromPayload(?array $event, int $articleId, string $title, string $locale): ?NewsArticleEventDTO
    {
        return $event === null ? null : $this->map($event, $articleId, $title, $locale, true);
    }

    public function findPublicEvent(string $article, string $locale): ?NewsArticleEventDTO
    {
        $record = $this->publicArticle($article);

        return $record instanceof NewsArticle ? $this->forArticle((int) $record->getKey(), $locale) : null;
    }

    public function registrationCount(int $articleId): int
    {
        $eventId = (string) $articleId;

        return DynamicFormSubmission::query()
            ->where('payload_json', 'like', '%"event_id":'.json_encode($eventId).'%')
            ->get(['id', 'payload_json'])
            ->filter(fn (DynamicFormSubmission $submission): bool => ($submission->payload_json['_context']['source'] ?? null) === self::REGISTRATION_SOURCE
                && ($submission->payload_json['_context']['event_id'] ?? null) === $eventId)
            ->count();
    }

    public function calendarFile(string $article, string $locale): ?string
    {
        $record = $this->publicArticle($article);
        $event = $record instanceof NewsArticle ? $this->forArticle((int) $record->getKey(), $locale) : null;
        if (! $event instanceof NewsArticleEventDTO || $event->calendarUrl === null || ! $record instanceof NewsArticle) {
            return null;
        }

        $url = url('/'.$locale.'/news/'.$record->getKey());
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'spu.edu.sy');
        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Syrian Private University//Events//'.strtoupper($locale),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($event->sessions as $index => $session) {
            $summary = $event->title.($session->title !== null ? ' — '.$session->title : '');
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:news-article-'.$record->getKey().'-'.($index + 1).'@'.$host;
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART:'.CarbonImmutable::parse($session->startsAt)->utc()->format('Ymd\THis\Z');
            if ($session->endsAt !== null) {
                $lines[] = 'DTEND:'.CarbonImmutable::parse($session->endsAt)->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.$this->icsText($summary);
            $location = $session->location ?? $event->location ?? $event->onlineUrl;
            if ($location !== null) {
                $lines[] = 'LOCATION:'.$this->icsText($location);
            }
            $lines[] = 'URL:'.$url;
            $lines[] = 'DESCRIPTION:'.$this->icsText($url);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $line): string => $this->foldLine($line), $lines))."\r\n";
    }

    /** @param array<string, mixed> $event */
    private function map(array $event, int $articleId, string $title, string $locale, bool $preview): NewsArticleEventDTO
    {
        $timezone = (string) config('app.admin_timezone', 'Asia/Damascus');
        $sessions = [];
        $startsAt = null;
        $endsAt = null;

        foreach ($this->sortedSessions($event['sessions'] ?? null) as $session) {
            $date = (string) $session['date'];
            $start = CarbonImmutable::parse($date.' '.$session['starts_at'], $timezone);
            $end = is_string($session['ends_at'] ?? null) && $session['ends_at'] !== ''
                ? CarbonImmutable::parse($date.' '.$session['ends_at'], $timezone)
                : null;
            $lastMoment = $end ?? $start->endOfDay();
            $startsAt = $startsAt === null || $start->lessThan($startsAt) ? $start : $startsAt;
            $endsAt = $endsAt === null || $lastMoment->greaterThan($endsAt) ? $lastMoment : $endsAt;

            $sessions[] = new NewsArticleEventSessionDTO(
                date: $date,
                dateLabel: $start->locale($locale)->translatedFormat('l j F Y'),
                timeLabel: $start->format('H:i').($end !== null ? ' – '.$end->format('H:i') : ''),
                startsAt: $start->toIso8601String(),
                endsAt: $end?->toIso8601String(),
                title: $this->localized($session, 'title', $locale),
                location: $this->localized($session, 'location', $locale),
                speakers: $this->speakers($session['speakers'] ?? null, $locale),
            );
        }

        $now = CarbonImmutable::now();
        $isPast = $endsAt !== null && $endsAt->lessThan($now);
        $requiresRegistration = (bool) ($event['requires_registration'] ?? false);
        $formId = $requiresRegistration && is_string($event['formId'] ?? null) && $event['formId'] !== '' ? $event['formId'] : null;
        $capacity = $requiresRegistration && is_numeric($event['capacity'] ?? null) ? (int) $event['capacity'] : null;
        $closesAt = $requiresRegistration && is_string($event['registration_closes_at'] ?? null) && strtotime($event['registration_closes_at']) !== false
            ? CarbonImmutable::parse($event['registration_closes_at'], (string) config('app.timezone', 'UTC'))
            : null;
        $taken = $requiresRegistration ? max(0, (int) ($event['prior_registrations'] ?? 0)) + $this->registrationCount($articleId) : 0;
        $seatsLeft = $capacity === null ? null : max(0, $capacity - $taken);

        $state = match (true) {
            ! $requiresRegistration || $formId === null => 'none',
            $isPast || ($closesAt !== null && $closesAt->lessThanOrEqualTo($now)) || ! $this->forms->isEnabled($formId) => 'closed',
            $seatsLeft === 0 => 'full',
            default => 'open',
        };
        $hasCertificate = (bool) ($event['has_certificate'] ?? false);
        $articleUrl = '/'.$locale.'/news/'.$articleId;

        return new NewsArticleEventDTO(
            id: (string) $articleId,
            articleId: $articleId,
            locale: $locale,
            title: $title,
            kind: is_string($event['kind'] ?? null) ? $event['kind'] : 'event',
            requiresRegistration: $requiresRegistration,
            showOnCalendar: (bool) ($event['show_on_calendar'] ?? false),
            isPast: $isPast,
            registrationState: $state,
            formId: $formId,
            capacity: $capacity,
            seatsLeft: $seatsLeft,
            registrationClosesAt: $closesAt?->setTimezone($timezone)->toIso8601String(),
            registrationUrl: $state === 'open' && ! $preview ? $articleUrl.'/register' : null,
            calendarUrl: $sessions !== [] && ! $isPast && ! $preview ? $articleUrl.'/calendar.ics' : null,
            startsAt: $startsAt?->toIso8601String(),
            endsAt: $endsAt?->toIso8601String(),
            language: is_string($event['language'] ?? null) ? $event['language'] : null,
            hasCertificate: $hasCertificate,
            certificateNote: $hasCertificate ? $this->localized($event, 'certificate_note', $locale) : null,
            audience: array_values(array_filter(is_array($event['audience'] ?? null) ? $event['audience'] : [], 'is_string')),
            audienceNote: $this->localized($event, 'audience_note', $locale),
            organizer: $this->localized($event, 'organizer', $locale),
            location: $this->localized($event, 'location', $locale),
            mapUrl: $this->webUrl($event['map_url'] ?? null),
            onlineUrl: $this->webUrl($event['online_url'] ?? null),
            contactEmail: is_string($event['contact_email'] ?? null) && $event['contact_email'] !== '' ? $event['contact_email'] : null,
            contactPhone: is_string($event['contact_phone'] ?? null) && $event['contact_phone'] !== '' ? $event['contact_phone'] : null,
            invitationImageUrl: $this->mediaUrl($event['invitation_media_id'] ?? null),
            results: $this->localized($event, 'results', $locale),
            sessions: $sessions,
            speakers: $this->speakers($event['speakers'] ?? null, $locale),
        );
    }

    /** @return list<array<string, mixed>> */
    private function sortedSessions(mixed $sessions): array
    {
        $sessions = array_values(array_filter(
            is_array($sessions) ? $sessions : [],
            fn (mixed $session): bool => is_array($session)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($session['date'] ?? '')) === 1
                && preg_match('/^\d{2}:\d{2}$/', (string) ($session['starts_at'] ?? '')) === 1,
        ));
        usort($sessions, fn (array $a, array $b): int => [$a['date'], $a['starts_at']] <=> [$b['date'], $b['starts_at']]);

        return $sessions;
    }

    /** @return array<int, NewsArticleEventSpeakerDTO> */
    private function speakers(mixed $speakers, string $locale): array
    {
        $result = [];

        foreach (is_array($speakers) ? $speakers : [] as $speaker) {
            if (! is_array($speaker)) {
                continue;
            }
            $dto = ($speaker['source'] ?? null) === 'university'
                ? $this->profileSpeaker((string) ($speaker['profile'] ?? ''), $locale)
                : $this->externalSpeaker($speaker, $locale);
            if ($dto instanceof NewsArticleEventSpeakerDTO) {
                $result[] = $dto;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $speaker */
    private function externalSpeaker(array $speaker, string $locale): ?NewsArticleEventSpeakerDTO
    {
        $name = $this->localized($speaker, 'name', $locale);

        return $name === null ? null : new NewsArticleEventSpeakerDTO(
            name: $name,
            title: $this->localized($speaker, 'title', $locale),
            organization: $this->localized($speaker, 'organization', $locale),
            bio: $this->localized($speaker, 'bio', $locale),
            photoUrl: $this->mediaUrl($speaker['photo_media_id'] ?? null),
            profileUrl: null,
        );
    }

    /**
     * A university speaker always shows the profile as it is now. A hidden or
     * deleted profile drops the speaker rather than showing a dead link.
     */
    private function profileSpeaker(string $profile, string $locale): ?NewsArticleEventSpeakerDTO
    {
        if (preg_match('/^(faculty_member|person):([1-9][0-9]*)$/', $profile, $matches) !== 1) {
            return null;
        }

        if ($matches[1] === 'person') {
            $person = Person::query()->public()->with(['translations', 'photoMedia'])->find((int) $matches[2]);
            if (! $person instanceof Person) {
                return null;
            }
            $translation = $this->profileTranslation($person->translations->all(), $locale);
            if (! $translation instanceof PersonTranslation || trim((string) $translation->name) === '') {
                return null;
            }

            return new NewsArticleEventSpeakerDTO(
                name: trim((string) $translation->name),
                title: $this->filled($translation->title) ?? $this->filled($translation->role),
                organization: null,
                bio: null,
                photoUrl: $person->photoMedia instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($person->photoMedia->webp_path, $person->photoMedia->path, $person->photoMedia->disk)
                    : ($this->filled($person->image) ?? MediaUrlResolver::resolveLegacy($person->legacy_photo_path)),
                profileUrl: '/'.$locale.'/about/profile/'.$person->slug,
            );
        }

        $member = FacultyMember::query()->public()->with(['translations', 'photoMedia'])->find((int) $matches[2]);
        if (! $member instanceof FacultyMember) {
            return null;
        }
        $translation = $this->profileTranslation($member->translations->all(), $locale);
        if (! $translation instanceof FacultyMemberTranslation || trim((string) $translation->full_name) === '') {
            return null;
        }
        // A member linked to a leadership profile is shown at that address.
        $linkedSlug = $member->person_id !== null
            ? Person::query()->public()->whereKey($member->person_id)->value('slug')
            : null;

        return new NewsArticleEventSpeakerDTO(
            name: trim((string) $translation->full_name),
            title: $this->filled($translation->title) ?? $this->filled($translation->position),
            organization: null,
            bio: null,
            photoUrl: $member->photoMedia instanceof MediaAsset
                ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
                : MediaUrlResolver::resolveLegacy($member->legacy_photo_path),
            profileUrl: '/'.$locale.'/about/profile/'.(is_string($linkedSlug) && $linkedSlug !== '' ? $linkedSlug : $member->slug),
        );
    }

    /** @param array<int, FacultyMemberTranslation|PersonTranslation> $translations */
    private function profileTranslation(array $translations, string $locale): FacultyMemberTranslation|PersonTranslation|null
    {
        $byLocale = [];
        foreach ($translations as $translation) {
            $byLocale[(string) $translation->locale] = $translation;
        }

        return $byLocale[$locale] ?? $byLocale['ar'] ?? ($translations[0] ?? null);
    }

    private function publicArticle(string $article): ?NewsArticle
    {
        $article = trim($article);
        if ($article === '') {
            return null;
        }

        $record = NewsArticle::query()
            ->public()
            ->when(ctype_digit($article),
                fn (Builder $query): Builder => $query->whereKey((int) $article),
                fn (Builder $query): Builder => $query->where('slug', $article),
            )
            ->first();

        return $record instanceof NewsArticle ? $record : null;
    }

    private function articleTitle(NewsArticle $article, string $locale): string
    {
        $translation = $article->translations->firstWhere('locale', $locale);
        $title = $translation instanceof NewsArticleTranslation && trim((string) $translation->title) !== ''
            ? $translation->title
            : $article->translations->firstWhere('locale', 'ar')?->title;

        return trim(strip_tags((string) ($title ?? $article->slug)));
    }

    /** @param array<string, mixed> $values */
    private function localized(array $values, string $field, string $locale): ?string
    {
        return $this->filled($values[$field.'_'.$locale] ?? null)
            ?? $this->filled($values[$field.'_'.($locale === 'ar' ? 'en' : 'ar')] ?? null);
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function webUrl(mixed $value): ?string
    {
        $value = $this->filled($value);

        return $value !== null && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    private function mediaUrl(mixed $mediaId): ?string
    {
        $media = is_numeric($mediaId) ? MediaAsset::query()->find((int) $mediaId) : null;

        return $media instanceof MediaAsset ? MediaUrlResolver::resolveImage($media->webp_path, $media->path, $media->disk) : null;
    }

    /** RFC 5545 text: escape backslash, semicolon, comma and line breaks. */
    private function icsText(string $value): string
    {
        $value = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);

        return trim(strip_tags($value));
    }

    /** Lines longer than 75 octets continue on the next line after a space, without splitting a character. */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        foreach (mb_str_split($line) as $character) {
            $limit = $parts === [] ? 75 : 74;
            if (strlen($current.$character) > $limit) {
                $parts[] = $current;
                $current = '';
            }
            $current .= $character;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
