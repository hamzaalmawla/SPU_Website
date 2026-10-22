<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\News\NewsArticleEventCmsServiceInterface;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticleEvent;
use App\Models\News\NewsArticleEventSession;
use App\Models\News\NewsArticleEventSpeaker;
use App\Models\News\NewsArticleEventTranslation;
use App\Models\News\NewsCategory;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class NewsArticleEventCmsService implements NewsArticleEventCmsServiceInterface
{
    public const EVENTS_SLUG = 'events';

    public const SOCIETY_EVENTS_SLUG = 'society-events';

    private const TRANSLATED_FIELDS = ['location', 'organizer', 'audience_note', 'certificate_note', 'results'];

    private const SPEAKER_TEXT_FIELDS = ['name', 'title', 'organization', 'bio'];

    /** @var array<int, string|null> */
    private array $categorySlugs = [];

    public function __construct(
        private readonly FormDefinitionServiceInterface $forms,
    ) {}

    public function isEventCategory(?int $categoryId): bool
    {
        return in_array($this->categorySlug($categoryId), [self::EVENTS_SLUG, self::SOCIETY_EVENTS_SLUG], true);
    }

    public function normalize(mixed $event, ?int $categoryId): ?array
    {
        if (! $this->isEventCategory($categoryId)) {
            return null;
        }

        $event = is_array($event) ? $event : [];
        $society = $this->categorySlug($categoryId) === self::SOCIETY_EVENTS_SLUG;
        $kind = $this->stringValue($event['kind'] ?? null);
        $language = $this->stringValue($event['language'] ?? null);

        // Registration values stay in the draft when the toggle is switched
        // off, so switching it back does not lose them; only persist drops them.
        $normalized = [
            'kind' => $society ? 'society' : (in_array($kind, self::KINDS, true) ? $kind : 'event'),
            'requires_registration' => ! $society && (bool) ($event['requires_registration'] ?? false),
            'show_on_calendar' => (bool) ($event['show_on_calendar'] ?? false),
            'formId' => $this->nullableString($event['formId'] ?? null),
            'capacity' => $this->nullableInt($event['capacity'] ?? null),
            'prior_registrations' => max(0, (int) ($this->nullableInt($event['prior_registrations'] ?? null) ?? 0)),
            'registration_closes_at' => $this->nullableString($event['registration_closes_at'] ?? null),
            'language' => in_array($language, self::LANGUAGES, true) ? $language : null,
            'has_certificate' => (bool) ($event['has_certificate'] ?? false),
            'audience' => array_values(array_intersect(self::AUDIENCES, is_array($event['audience'] ?? null) ? $event['audience'] : [])),
            'invitation_media_id' => $this->nullableInt($event['invitation_media_id'] ?? null),
            'map_url' => $this->nullableString($event['map_url'] ?? null),
            'online_url' => $this->nullableString($event['online_url'] ?? null),
            'contact_email' => $this->nullableString($event['contact_email'] ?? null),
            'contact_phone' => $this->nullableString($event['contact_phone'] ?? null),
        ];

        foreach (self::TRANSLATED_FIELDS as $field) {
            foreach (['ar', 'en'] as $locale) {
                $normalized[$field.'_'.$locale] = $this->nullableString($event[$field.'_'.$locale] ?? null);
            }
        }

        $normalized['sessions'] = array_map(fn (array $session): array => [
            'date' => $this->nullableString($session['date'] ?? null),
            'starts_at' => $this->timeValue($session['starts_at'] ?? null),
            'ends_at' => $this->timeValue($session['ends_at'] ?? null),
            'title_ar' => $this->nullableString($session['title_ar'] ?? null),
            'title_en' => $this->nullableString($session['title_en'] ?? null),
            'location_ar' => $this->nullableString($session['location_ar'] ?? null),
            'location_en' => $this->nullableString($session['location_en'] ?? null),
            'speakers' => $this->normalizeSpeakers($session['speakers'] ?? null),
        ], $this->records($event['sessions'] ?? null));
        $normalized['speakers'] = $this->normalizeSpeakers($event['speakers'] ?? null);

        return $normalized;
    }

    public function storedPayload(int $articleId): ?array
    {
        $event = NewsArticleEvent::query()
            ->with(['translations', 'sessions.speakers', 'speakers'])
            ->where('news_article_id', $articleId)
            ->first();

        if (! $event instanceof NewsArticleEvent) {
            return null;
        }

        $payload = [
            'kind' => (string) $event->kind,
            'requires_registration' => (bool) $event->requires_registration,
            'show_on_calendar' => (bool) $event->show_on_calendar,
            'formId' => $event->form_key,
            'capacity' => $event->capacity,
            'prior_registrations' => (int) $event->prior_registrations,
            'registration_closes_at' => $event->registration_closes_at?->format('Y-m-d H:i:s'),
            'language' => $event->language,
            'has_certificate' => (bool) $event->has_certificate,
            'audience' => is_array($event->audience) ? array_values($event->audience) : [],
            'invitation_media_id' => $event->invitation_media_id,
            'map_url' => $event->map_url,
            'online_url' => $event->online_url,
            'contact_email' => $event->contact_email,
            'contact_phone' => $event->contact_phone,
        ];

        foreach (self::TRANSLATED_FIELDS as $field) {
            foreach (['ar', 'en'] as $locale) {
                $translation = $event->translations->firstWhere('locale', $locale);
                $payload[$field.'_'.$locale] = $translation instanceof NewsArticleEventTranslation ? $translation->{$field} : null;
            }
        }

        $payload['sessions'] = $event->sessions->map(fn (NewsArticleEventSession $session): array => [
            'date' => $session->date?->format('Y-m-d'),
            'starts_at' => $this->timeValue($session->starts_at),
            'ends_at' => $this->timeValue($session->ends_at),
            'title_ar' => $session->title_ar,
            'title_en' => $session->title_en,
            'location_ar' => $session->location_ar,
            'location_en' => $session->location_en,
            'speakers' => $session->speakers->map(fn (NewsArticleEventSpeaker $speaker): array => $this->speakerPayload($speaker))->values()->all(),
        ])->values()->all();
        $payload['speakers'] = $event->speakers->map(fn (NewsArticleEventSpeaker $speaker): array => $this->speakerPayload($speaker))->values()->all();

        return $payload;
    }

    public function validationErrors(?array $event, ?int $categoryId): array
    {
        if (! $this->isEventCategory($categoryId)) {
            return [];
        }

        $event ??= $this->normalize([], $categoryId) ?? [];
        $errors = [];
        $sessions = $this->records($event['sessions'] ?? null);

        // Society's Events off the calendar are plain articles and may have no date.
        $needsSession = ($event['kind'] ?? null) !== 'society' || ($event['show_on_calendar'] ?? false) === true;
        if ($sessions === [] && $needsSession) {
            $errors['event.sessions'][] = 'An event needs at least one session with a date and start time.';
        }

        foreach ($sessions as $index => $session) {
            $field = 'event.sessions.'.$index;
            $date = $this->stringValue($session['date'] ?? null);
            $startsAt = $this->stringValue($session['starts_at'] ?? null);
            $endsAt = $this->stringValue($session['ends_at'] ?? null);

            if (! $this->isDate($date)) {
                $errors[$field.'.date'][] = 'Each session needs a valid date.';
            }
            if (! $this->isTime($startsAt)) {
                $errors[$field.'.starts_at'][] = 'Each session needs a valid start time.';
            }
            if ($endsAt !== '' && (! $this->isTime($endsAt) || ($this->isTime($startsAt) && $endsAt <= $startsAt))) {
                $errors[$field.'.ends_at'][] = 'A session must end after it starts.';
            }

            $this->appendSpeakerErrors($errors, $field.'.speakers', $session['speakers'] ?? null);
        }

        $this->appendSpeakerErrors($errors, 'event.speakers', $event['speakers'] ?? null);

        if (($event['requires_registration'] ?? false) === true) {
            $formKey = $this->stringValue($event['formId'] ?? null);
            if ($formKey === '' || $this->forms->find($formKey) === null || ! $this->forms->isEnabled($formKey)) {
                $errors['event.formId'][] = 'An event with registration needs an enabled registration form.';
            }

            $capacity = $event['capacity'] ?? null;
            if ($capacity !== null && (! is_int($capacity) || $capacity < 1)) {
                $errors['event.capacity'][] = 'The number of seats must be at least 1, or left empty for no limit.';
            }

            $closesAt = $this->stringValue($event['registration_closes_at'] ?? null);
            if ($closesAt !== '' && strtotime($closesAt) === false) {
                $errors['event.registration_closes_at'][] = 'The registration closing time is not a valid date.';
            }
        }

        foreach (['map_url', 'online_url'] as $field) {
            $url = $this->stringValue($event[$field] ?? null);
            if ($url !== '' && ! $this->isWebUrl($url)) {
                $errors['event.'.$field][] = 'Enter a full web address starting with https:// or http://.';
            }
        }

        $email = $this->stringValue($event['contact_email'] ?? null);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['event.contact_email'][] = 'The contact email is not valid.';
        }

        $this->appendImageError($errors, 'event.invitation_media_id', $event['invitation_media_id'] ?? null);

        return $errors;
    }

    public function persist(int $articleId, ?array $event): bool
    {
        if ($event === null) {
            NewsArticleEvent::query()->where('news_article_id', $articleId)->delete();

            return true;
        }

        return DB::transaction(function () use ($articleId, $event): bool {
            $registration = (bool) ($event['requires_registration'] ?? false);
            $sessions = $this->sortedSessions($this->records($event['sessions'] ?? null));
            [$startsAt, $endsAt] = $this->eventBounds($sessions);

            $record = NewsArticleEvent::query()->updateOrCreate(['news_article_id' => $articleId], [
                'kind' => $this->stringValue($event['kind'] ?? null) ?: 'event',
                'requires_registration' => $registration,
                'show_on_calendar' => (bool) ($event['show_on_calendar'] ?? false),
                'form_key' => $registration ? $this->nullableString($event['formId'] ?? null) : null,
                'capacity' => $registration ? $this->nullableInt($event['capacity'] ?? null) : null,
                'prior_registrations' => $registration ? max(0, (int) ($event['prior_registrations'] ?? 0)) : 0,
                'registration_closes_at' => $registration ? $this->nullableString($event['registration_closes_at'] ?? null) : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'language' => $this->nullableString($event['language'] ?? null),
                'has_certificate' => (bool) ($event['has_certificate'] ?? false),
                'audience' => is_array($event['audience'] ?? null) ? array_values($event['audience']) : [],
                'invitation_media_id' => $this->nullableInt($event['invitation_media_id'] ?? null),
                'map_url' => $this->nullableString($event['map_url'] ?? null),
                'online_url' => $this->nullableString($event['online_url'] ?? null),
                'contact_email' => $this->nullableString($event['contact_email'] ?? null),
                'contact_phone' => $this->nullableString($event['contact_phone'] ?? null),
            ]);

            foreach (['ar', 'en'] as $locale) {
                $values = [];
                foreach (self::TRANSLATED_FIELDS as $field) {
                    $values[$field] = $this->nullableString($event[$field.'_'.$locale] ?? null);
                }
                if (! (bool) ($event['has_certificate'] ?? false)) {
                    $values['certificate_note'] = null;
                }
                $record->translations()->updateOrCreate(['locale' => $locale], $values);
            }

            NewsArticleEventSpeaker::query()->where('news_article_event_id', $record->getKey())->delete();
            $record->sessions()->delete();

            foreach ($sessions as $order => $session) {
                $sessionRecord = $record->sessions()->create([
                    'date' => $this->stringValue($session['date'] ?? null),
                    'starts_at' => $this->stringValue($session['starts_at'] ?? null),
                    'ends_at' => $this->nullableString($session['ends_at'] ?? null),
                    'title_ar' => $this->nullableString($session['title_ar'] ?? null),
                    'title_en' => $this->nullableString($session['title_en'] ?? null),
                    'location_ar' => $this->nullableString($session['location_ar'] ?? null),
                    'location_en' => $this->nullableString($session['location_en'] ?? null),
                    'sort_order' => $order,
                ]);
                $this->createSpeakers((int) $record->getKey(), (int) $sessionRecord->getKey(), $session['speakers'] ?? null);
            }

            $this->createSpeakers((int) $record->getKey(), null, $event['speakers'] ?? null);

            return true;
        });
    }

    public function speakerProfileOptions(string $locale, ?string $current = null): array
    {
        $locale = $locale === 'en' ? 'en' : 'ar';
        $options = [];

        // A leadership profile linked to a faculty member is listed once,
        // as that member, under the member's faculty.
        $leaders = Person::query()
            ->public()
            ->whereDoesntHave('linkedFacultyMembers', fn (Builder $query): Builder => $query->public())
            ->with('translations')
            ->get()
            ->mapWithKeys(fn (Person $person): array => ['person:'.$person->getKey() => $this->personLabel($person, $locale)])
            ->filter(fn (string $label): bool => $label !== '')
            ->sort()
            ->all();

        if ($leaders !== []) {
            $options[__('admin.news_article.event.speaker_groups.leadership', [], $locale)] = $leaders;
        }

        $groups = [];
        FacultyMember::query()
            ->public()
            ->with(['translations', 'faculty.translations'])
            ->get()
            ->each(function (FacultyMember $member) use (&$groups, $locale): void {
                $label = $this->memberLabel($member, $locale);
                if ($label === '') {
                    return;
                }
                $faculty = $member->faculty?->translations->firstWhere('locale', $locale)?->name
                    ?? $member->faculty?->translations->firstWhere('locale', 'ar')?->name;
                $group = filled($faculty) ? trim((string) $faculty) : __('admin.news_article.event.speaker_groups.no_faculty', [], $locale);
                $groups[$group]['faculty_member:'.$member->getKey()] = $label;
            });

        ksort($groups);
        foreach ($groups as $group => $members) {
            asort($members);
            $options[$group] = $members;
        }

        $current = $this->nullableString($current);
        if ($current !== null && ! $this->optionExists($options, $current)) {
            $label = $this->profileLabel($current, $locale);
            if ($label !== null) {
                $options[__('admin.news_article.event.speaker_groups.hidden', [], $locale)] = [$current => $label];
            }
        }

        return $options;
    }

    /** @return list<array<string, mixed>> */
    private function normalizeSpeakers(mixed $speakers): array
    {
        return array_map(function (array $speaker): array {
            $source = $this->stringValue($speaker['source'] ?? null) === 'university' ? 'university' : 'external';
            $normalized = [
                'source' => $source,
                'profile' => $this->nullableString($speaker['profile'] ?? null),
                'photo_media_id' => $this->nullableInt($speaker['photo_media_id'] ?? null),
            ];
            foreach (self::SPEAKER_TEXT_FIELDS as $field) {
                foreach (['ar', 'en'] as $locale) {
                    $normalized[$field.'_'.$locale] = $this->nullableString($speaker[$field.'_'.$locale] ?? null);
                }
            }

            return $normalized;
        }, $this->records($speakers));
    }

    /** @return array<string, mixed> */
    private function speakerPayload(NewsArticleEventSpeaker $speaker): array
    {
        $profile = match (true) {
            $speaker->faculty_member_id !== null => 'faculty_member:'.$speaker->faculty_member_id,
            $speaker->person_id !== null => 'person:'.$speaker->person_id,
            default => null,
        };
        $payload = [
            'source' => $profile !== null ? 'university' : 'external',
            'profile' => $profile,
            'photo_media_id' => $speaker->photo_media_id,
        ];
        foreach (self::SPEAKER_TEXT_FIELDS as $field) {
            foreach (['ar', 'en'] as $locale) {
                $payload[$field.'_'.$locale] = $speaker->{$field.'_'.$locale};
            }
        }

        return $payload;
    }

    private function createSpeakers(int $eventId, ?int $sessionId, mixed $speakers): void
    {
        foreach ($this->records($speakers) as $order => $speaker) {
            $university = ($speaker['source'] ?? null) === 'university';
            [$type, $profileId] = $university ? $this->parseProfile($this->stringValue($speaker['profile'] ?? null)) : [null, null];
            $values = [
                'news_article_event_id' => $eventId,
                'news_article_event_session_id' => $sessionId,
                'faculty_member_id' => $type === 'faculty_member' ? $profileId : null,
                'person_id' => $type === 'person' ? $profileId : null,
                // A university speaker's name, title and photo come from the profile.
                'photo_media_id' => $university ? null : $this->nullableInt($speaker['photo_media_id'] ?? null),
                'sort_order' => $order,
            ];
            foreach (self::SPEAKER_TEXT_FIELDS as $field) {
                foreach (['ar', 'en'] as $locale) {
                    $values[$field.'_'.$locale] = $university ? null : $this->nullableString($speaker[$field.'_'.$locale] ?? null);
                }
            }
            NewsArticleEventSpeaker::query()->create($values);
        }
    }

    /** @param array<string, array<int, string>> $errors */
    private function appendSpeakerErrors(array &$errors, string $prefix, mixed $speakers): void
    {
        foreach ($this->records($speakers) as $index => $speaker) {
            $field = $prefix.'.'.$index;
            if (($speaker['source'] ?? null) === 'university') {
                if ($this->profileLabel($this->stringValue($speaker['profile'] ?? null), 'ar') === null) {
                    $errors[$field.'.profile'][] = 'Choose the speaker\'s university profile.';
                }

                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                if ($this->stringValue($speaker['name_'.$locale] ?? null) === '') {
                    $errors[$field.'.name_'.$locale][] = 'An outside speaker needs a name in Arabic and English.';
                }
            }
            $this->appendImageError($errors, $field.'.photo_media_id', $speaker['photo_media_id'] ?? null);
        }
    }

    /** @param array<string, array<int, string>> $errors */
    private function appendImageError(array &$errors, string $field, mixed $value): void
    {
        $id = $this->nullableInt($value);
        if ($id === null) {
            return;
        }
        $media = MediaAsset::query()->find($id);
        if (! $media instanceof MediaAsset || ! str_starts_with((string) $media->mime_type, 'image/')) {
            $errors[$field][] = 'The selected media file is unavailable or has the wrong type.';
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return list<array<string, mixed>>
     */
    private function sortedSessions(array $sessions): array
    {
        usort($sessions, fn (array $a, array $b): int => [$this->stringValue($a['date'] ?? null), $this->stringValue($a['starts_at'] ?? null)]
            <=> [$this->stringValue($b['date'] ?? null), $this->stringValue($b['starts_at'] ?? null)]);

        return $sessions;
    }

    /**
     * Session dates and times are what the editor typed, in the admin time
     * zone; the stored bounds are in the app time zone.
     *
     * @param  list<array<string, mixed>>  $sessions
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function eventBounds(array $sessions): array
    {
        if ($sessions === []) {
            return [null, null];
        }

        $timezone = (string) config('app.admin_timezone', 'Asia/Damascus');
        $appTimezone = (string) config('app.timezone', 'UTC');
        $first = $sessions[0];
        $startsAt = CarbonImmutable::parse($this->stringValue($first['date'] ?? null).' '.$this->stringValue($first['starts_at'] ?? null), $timezone);
        $endsAt = null;

        foreach ($sessions as $session) {
            $date = $this->stringValue($session['date'] ?? null);
            $end = $this->nullableString($session['ends_at'] ?? null);
            $moment = $end !== null
                ? CarbonImmutable::parse($date.' '.$end, $timezone)
                : CarbonImmutable::parse($date, $timezone)->endOfDay();
            $endsAt = $endsAt === null || $moment->greaterThan($endsAt) ? $moment : $endsAt;
        }

        return [$startsAt->setTimezone($appTimezone), $endsAt?->setTimezone($appTimezone)];
    }

    /** @return array{0: ?string, 1: ?int} */
    private function parseProfile(string $value): array
    {
        return preg_match('/^(faculty_member|person):([1-9][0-9]*)$/', $value, $matches) === 1
            ? [$matches[1], (int) $matches[2]]
            : [null, null];
    }

    private function profileLabel(string $value, string $locale): ?string
    {
        [$type, $id] = $this->parseProfile($value);

        return match ($type) {
            'faculty_member' => ($member = FacultyMember::query()->with('translations')->find($id)) instanceof FacultyMember ? $this->memberLabel($member, $locale) : null,
            'person' => ($person = Person::query()->with('translations')->find($id)) instanceof Person ? $this->personLabel($person, $locale) : null,
            default => null,
        };
    }

    private function memberLabel(FacultyMember $member, string $locale): string
    {
        $names = $member->translations->mapWithKeys(fn (FacultyMemberTranslation $translation): array => [(string) $translation->locale => trim((string) $translation->full_name)])->all();

        return $this->bilingualLabel($names, $locale);
    }

    private function personLabel(Person $person, string $locale): string
    {
        $names = $person->translations->mapWithKeys(fn (PersonTranslation $translation): array => [(string) $translation->locale => trim((string) $translation->name)])->all();

        return $this->bilingualLabel($names, $locale);
    }

    /**
     * Both names go in the label so the dropdown search finds a speaker
     * whichever language the editor types in.
     *
     * @param  array<string, string>  $names
     */
    private function bilingualLabel(array $names, string $locale): string
    {
        $other = $locale === 'ar' ? 'en' : 'ar';
        $primary = ($names[$locale] ?? '') !== '' ? $names[$locale] : ($names[$other] ?? '');
        $secondary = $names[$other] ?? '';

        return $secondary !== '' && $secondary !== $primary ? $primary.' — '.$secondary : $primary;
    }

    /** @param array<string, array<string, string>> $options */
    private function optionExists(array $options, string $value): bool
    {
        foreach ($options as $group) {
            if (array_key_exists($value, $group)) {
                return true;
            }
        }

        return false;
    }

    private function categorySlug(?int $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        return $this->categorySlugs[$categoryId] ??= NewsCategory::query()->whereKey($categoryId)->value('slug');
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function isDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private function isWebUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /** Times are kept as "HH:MM"; the time column and picker may add seconds. */
    private function timeValue(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $value, $matches) === 1 ? $matches[1] : ($value !== '' ? $value : null);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
