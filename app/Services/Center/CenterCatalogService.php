<?php

declare(strict_types=1);

namespace App\Services\Center;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Person\FacultyMember;
use JsonException;

final class CenterCatalogService implements CenterCatalogServiceInterface
{
    /** Faculties a unit course can name, by public slug. */
    private const FACULTIES = [
        'medicine',
        'dentistry',
        'pharmacy',
        'artificial-intelligence',
        'building-construction-engineering',
        'petroleum',
        'business-administration',
    ];

    private const PARTS = ['staff', 'courses'];

    private const DATA_DIRECTORY = 'seeders/Data/centers';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $defaults = null;

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function centerSlugs(): array
    {
        $centers = config('centers.centers', []);

        return is_array($centers)
            ? array_values(array_filter(array_map('strval', array_keys($centers)), static fn (string $slug): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1))
            : [];
    }

    public function isCenter(string $centerSlug): bool
    {
        return in_array($centerSlug, $this->centerSlugs(), true);
    }

    public function isCenterTarget(string $targetKey): bool
    {
        return $this->parseTarget($targetKey) !== null;
    }

    public function targetKeys(): array
    {
        $keys = ['centers.landing'];

        foreach ($this->centerSlugs() as $centerSlug) {
            $keys[] = 'centers.'.$centerSlug;

            foreach (self::PARTS as $part) {
                $keys[] = 'centers.'.$centerSlug.'.'.$part;
            }
        }

        return $keys;
    }

    public function parseTarget(string $targetKey): ?array
    {
        if ($targetKey === 'centers.landing') {
            return ['center' => null, 'part' => 'landing'];
        }

        $parts = explode('.', $targetKey);

        if (($parts[0] ?? '') !== 'centers' || ! $this->isCenter((string) ($parts[1] ?? ''))) {
            return null;
        }

        if (count($parts) === 2) {
            return ['center' => $parts[1], 'part' => 'page'];
        }

        return count($parts) === 3 && in_array($parts[2], self::PARTS, true)
            ? ['center' => $parts[1], 'part' => $parts[2]]
            : null;
    }

    public function defaultPayload(string $targetKey): array
    {
        $target = $this->parseTarget($targetKey);

        if ($target === null) {
            return [];
        }

        $defaults = $this->defaults();

        if ($target['part'] === 'landing') {
            return is_array($defaults['landing'] ?? null) ? $defaults['landing'] : [];
        }

        $center = is_array($defaults[$target['center']] ?? null) ? $defaults[$target['center']] : [];
        $payload = is_array($center[$target['part']] ?? null) ? $center[$target['part']] : [];

        if ($target['part'] === 'courses') {
            $payload['courses'] = $this->defaultCourses((string) $target['center']);
        }

        return $payload;
    }

    public function content(string $targetKey): array
    {
        return $this->published($targetKey) ?? $this->defaultPayload($targetKey);
    }

    public function hasPublished(string $targetKey): bool
    {
        return $this->published($targetKey) !== null;
    }

    public function centerName(string $centerSlug, string $locale): ?string
    {
        if (! $this->isCenter($centerSlug)) {
            return null;
        }

        $content = $this->content('centers.'.$centerSlug);
        $translations = is_array($content['translations'] ?? null) ? $content['translations'] : [];

        foreach ([$locale, $locale === 'ar' ? 'en' : 'ar'] as $candidate) {
            $title = trim((string) ($translations[$candidate]['hero']['title'] ?? ''));

            if ($title !== '') {
                return $title;
            }
        }

        return null;
    }

    public function centerOptions(string $locale): array
    {
        $options = [];

        foreach ($this->centerSlugs() as $centerSlug) {
            $options[$centerSlug] = $this->centerName($centerSlug, $locale) ?? $centerSlug;
        }

        return $options;
    }

    public function courses(string $centerSlug, ?array $payload = null): array
    {
        if (! $this->isCenter($centerSlug)) {
            return [];
        }

        $payload ??= $this->content('centers.'.$centerSlug.'.courses');
        $courses = [];

        foreach (is_array($payload['courses'] ?? null) ? $payload['courses'] : [] as $course) {
            if (is_array($course) && ($clean = $this->cleanCourse($course)) !== null) {
                $courses[] = $clean;
            }
        }

        return $courses;
    }

    public function unitCourseOptions(string $locale): array
    {
        $centerSlug = $this->requirementsUnit();

        if ($centerSlug === null) {
            return [];
        }

        $required = $locale === 'ar' ? 'إجباري' : 'Required';
        $elective = $locale === 'ar' ? 'اختياري' : 'Elective';
        $options = [];

        foreach ($this->courses($centerSlug) as $course) {
            $title = (string) ($locale === 'ar' ? ($course['titleAr'] ?: $course['titleEn']) : ($course['titleEn'] ?: $course['titleAr']));
            $options[(string) $course['id']] = $title.' · '.($course['required'] ? $required : $elective);
        }

        return $options;
    }

    public function studyPlanCourseUrl(string $facultySlug, array $course, string $locale): ?string
    {
        if (($course['type'] ?? null) !== 'university') {
            return null;
        }

        $centerSlug = $this->requirementsUnit();

        if ($centerSlug === null) {
            return null;
        }

        $base = '/'.$locale.'/centers/'.$centerSlug.'/';
        $match = $this->matchingUnitCourse($centerSlug, $facultySlug, $course);

        if ($match !== null) {
            return $base.($match['required'] ? 'required-courses' : 'elective-courses').'#course-'.$match['id'];
        }

        return $base.(($course['required'] ?? true) !== false ? 'required-courses' : 'elective-courses');
    }

    public function validationErrors(string $targetKey, array $payload): array
    {
        $target = $this->parseTarget($targetKey);

        if ($target === null) {
            return [];
        }

        $errors = [];

        match ($target['part']) {
            'landing' => $this->landingErrors($payload, $errors),
            'page' => $this->pageErrors((string) $target['center'], $payload, $errors),
            'courses' => $this->courseErrors($payload, $errors),
            default => null,
        };

        return $errors;
    }

    /** @return array<string, mixed>|null */
    private function published(string $targetKey): ?array
    {
        if (! $this->isCenterTarget($targetKey)) {
            return null;
        }

        $cached = $this->cacheService
            ->tags(['cms', 'cms:'.$targetKey, 'centers'])
            ->remember('centers.published-payload.'.$targetKey, function () use ($targetKey): array {
                $content = CmsTargetContent::query()
                    ->where('target_key', $targetKey)
                    ->where('status', PublicationStatus::Published->value)
                    ->first();

                return ['payload' => $content instanceof CmsTargetContent && is_array($content->payload_json) ? $content->payload_json : null];
            }, (int) config('cache.public_page_ttl', 3600));

        return is_array($cached) && is_array($cached['payload'] ?? null) ? $cached['payload'] : null;
    }

    /** The first center built on the Requirements Unit template. */
    private function requirementsUnit(): ?string
    {
        foreach ($this->centerSlugs() as $centerSlug) {
            if ((string) config('centers.centers.'.$centerSlug.'.template') === 'requirements-unit') {
                return $centerSlug;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>|null
     */
    private function matchingUnitCourse(string $centerSlug, string $facultySlug, array $course): ?array
    {
        $courses = $this->courses($centerSlug);
        $picked = trim((string) ($course['requirementsUnitCourseId'] ?? ''));

        if ($picked !== '') {
            foreach ($courses as $unitCourse) {
                if ($unitCourse['id'] === $picked) {
                    return $unitCourse;
                }
            }
        }

        $code = $this->codeKey((string) ($course['code'] ?? ''));

        if ($code === '') {
            return null;
        }

        foreach ($courses as $unitCourse) {
            foreach ($unitCourse['faculties'] as $link) {
                if ($link['faculty'] === $facultySlug && $this->codeKey($link['code']) === $code) {
                    return $unitCourse;
                }
            }
        }

        return null;
    }

    private function codeKey(string $code): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $code));
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>|null
     */
    private function cleanCourse(array $course): ?array
    {
        $id = trim((string) ($course['id'] ?? ''));

        if ($id === '') {
            return null;
        }

        $hours = is_array($course['hours'] ?? null) ? $course['hours'] : [];
        $faculties = [];

        foreach (is_array($course['faculties'] ?? null) ? $course['faculties'] : [] as $link) {
            if (! is_array($link)) {
                continue;
            }

            $faculty = trim((string) ($link['faculty'] ?? ''));
            $code = trim((string) ($link['code'] ?? ''));

            if ($faculty === '' || $code === '') {
                continue;
            }

            $entry = ['faculty' => $faculty, 'code' => $code];

            if (is_numeric($link['credits'] ?? null)) {
                $entry['credits'] = $this->number($link['credits']);
            }

            $faculties[] = $entry;
        }

        $teachers = [];
        foreach (['theory', 'practical'] as $part) {
            $stored = is_array($course['teachers'][$part] ?? null) ? $course['teachers'][$part] : [];
            $teachers[$part] = [
                'memberIds' => array_values(array_unique(array_map('intval', array_filter(
                    is_array($stored['memberIds'] ?? null) ? $stored['memberIds'] : [],
                    static fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
                )))),
                'enabled' => ($stored['enabled'] ?? true) !== false,
            ];
        }

        $lessons = [];
        foreach (is_array($course['lessons'] ?? null) ? $course['lessons'] : [] as $lesson) {
            if (! is_array($lesson)) {
                continue;
            }

            $titleAr = trim((string) ($lesson['titleAr'] ?? ''));
            $titleEn = trim((string) ($lesson['titleEn'] ?? ''));
            $pdfUrl = trim((string) ($lesson['pdfUrl'] ?? ''));

            if ($titleAr === '' && $titleEn === '' && $pdfUrl === '') {
                continue;
            }

            $lessons[] = [
                'type' => in_array($lesson['type'] ?? null, ['lecture', 'lab'], true) ? (string) $lesson['type'] : 'lecture',
                'titleAr' => $titleAr,
                'titleEn' => $titleEn,
                'pdfUrl' => $pdfUrl,
            ];
        }

        return [
            'id' => $id,
            'code' => trim((string) ($course['code'] ?? '')),
            'titleAr' => trim((string) ($course['titleAr'] ?? '')),
            'titleEn' => trim((string) ($course['titleEn'] ?? '')),
            'descriptionAr' => trim((string) ($course['descriptionAr'] ?? '')),
            'descriptionEn' => trim((string) ($course['descriptionEn'] ?? '')),
            'required' => ($course['required'] ?? true) !== false,
            'credits' => $this->number($course['credits'] ?? 0),
            'hours' => [
                'theory' => $this->number($hours['theory'] ?? 0),
                'practical' => $this->number($hours['practical'] ?? 0),
            ],
            'prerequisites' => array_values(array_unique(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                is_array($course['prerequisites'] ?? null) ? $course['prerequisites'] : [],
            ), static fn (string $value): bool => $value !== '' && $value !== $id))),
            'faculties' => $faculties,
            'teachers' => $teachers,
            'specificationUrl' => trim((string) ($course['specificationUrl'] ?? '')),
            'lessons' => $lessons,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    private function landingErrors(array $payload, array &$errors): void
    {
        foreach (['ar', 'en'] as $locale) {
            $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];

            foreach (is_array($content['offers']['items'] ?? null) ? $content['offers']['items'] : [] as $index => $item) {
                if (! is_array($item) || trim((string) ($item['title'] ?? '')) === '') {
                    $errors[$locale][] = 'Offer '.((int) $index + 1).' needs a title.';
                }
            }

            foreach (is_array($content['extraCards'] ?? null) ? $content['extraCards'] : [] as $index => $card) {
                if (! is_array($card) || trim((string) ($card['title'] ?? '')) === '' || ! $this->isSafeLink($card['url'] ?? null)) {
                    $errors[$locale][] = 'Extra center card '.((int) $index + 1).' needs a title and a site path or https link.';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    private function pageErrors(string $centerSlug, array $payload, array &$errors): void
    {
        $managerId = $payload['managerMemberId'] ?? null;

        if ($managerId !== null && $managerId !== '') {
            $exists = is_numeric($managerId) && FacultyMember::query()
                ->whereKey((int) $managerId)
                ->where('center_slug', $centerSlug)
                ->exists();

            if (! $exists) {
                $errors['managerMemberId'][] = 'The manager must be a profile of this center\'s staff.';
            }
        }

        foreach (['ar', 'en'] as $locale) {
            $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];

            foreach (is_array($content['highlights'] ?? null) ? $content['highlights'] : [] as $index => $card) {
                if (! is_array($card) || ! in_array($card['subpage'] ?? null, self::SUBPAGES, true) || trim((string) ($card['title'] ?? '')) === '') {
                    $errors[$locale][] = 'Card '.((int) $index + 1).' needs a page and a title.';
                }
            }

            foreach (is_array($content['tabs'] ?? null) ? $content['tabs'] : [] as $index => $tab) {
                if (! is_array($tab) || trim((string) ($tab['label'] ?? '')) === '' || trim((string) ($tab['body'] ?? '')) === '') {
                    $errors[$locale][] = 'Tab '.((int) $index + 1).' needs a label and text.';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    private function courseErrors(array $payload, array &$errors): void
    {
        $courses = array_values(array_filter(is_array($payload['courses'] ?? null) ? $payload['courses'] : [], 'is_array'));
        $ids = [];
        $codes = [];
        $memberIds = [];

        foreach ($courses as $index => $course) {
            $label = 'Course '.($index + 1);
            $id = trim((string) ($course['id'] ?? ''));

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
                $errors['courses'][] = $label.' needs an id of lowercase letters, numbers and dashes.';
            } elseif (isset($ids[$id])) {
                $errors['courses'][] = $label.' repeats the id "'.$id.'".';
            }

            $ids[$id] = true;
        }

        foreach ($courses as $index => $course) {
            $clean = $this->cleanCourse($course);
            $label = 'Course '.($index + 1).($clean !== null ? ' ('.$clean['id'].')' : '');

            if ($clean === null) {
                continue;
            }

            if ($clean['titleAr'] === '' || $clean['titleEn'] === '') {
                $errors['courses'][] = $label.' needs a title in Arabic and in English.';
            }

            foreach ([$course['credits'] ?? 0, $course['hours']['theory'] ?? 0, $course['hours']['practical'] ?? 0] as $value) {
                if ($value !== null && $value !== '' && (! is_numeric($value) || (float) $value < 0 || (float) $value > 40)) {
                    $errors['courses'][] = $label.': credits and hours must be numbers between 0 and 40.';
                    break;
                }
            }

            foreach ($clean['prerequisites'] as $prerequisite) {
                if (! isset($ids[$prerequisite])) {
                    $errors['courses'][] = $label.': the prerequisite "'.$prerequisite.'" is not a course of this list.';
                }
            }

            foreach ($clean['faculties'] as $link) {
                if (! in_array($link['faculty'], self::FACULTIES, true)) {
                    $errors['courses'][] = $label.': "'.$link['faculty'].'" is not a faculty.';

                    continue;
                }

                $key = $link['faculty'].'|'.$this->codeKey($link['code']);

                if (isset($codes[$key]) && $codes[$key] !== $clean['id']) {
                    $errors['courses'][] = $label.': the code '.$link['code'].' of this faculty is already used by '.$codes[$key].'.';
                }

                $codes[$key] = $clean['id'];
            }

            foreach ($clean['teachers'] as $teacher) {
                array_push($memberIds, ...$teacher['memberIds']);
            }

            if ($clean['specificationUrl'] !== '' && ! $this->isSafeLink($clean['specificationUrl'])) {
                $errors['courses'][] = $label.': the specification must be a site path or an https link.';
            }

            foreach ($clean['lessons'] as $lesson) {
                if (($lesson['titleAr'] === '' && $lesson['titleEn'] === '') || ($lesson['pdfUrl'] !== '' && ! $this->isSafeLink($lesson['pdfUrl']))) {
                    $errors['courses'][] = $label.': each lesson needs a title, and its file a site path or an https link.';
                    break;
                }
            }
        }

        $memberIds = array_values(array_unique($memberIds));

        if ($memberIds !== [] && FacultyMember::query()->whereKey($memberIds)->count() !== count($memberIds)) {
            $errors['courses'][] = 'A teacher picked for a course no longer has a profile.';
        }

        if (isset($errors['courses'])) {
            $errors['courses'] = array_values(array_unique($errors['courses']));
        }
    }

    private function isSafeLink(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        return (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || (str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    private function number(mixed $value): int|float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return floor($number) === $number ? (int) $number : $number;
    }

    /** @return list<array<string, mixed>> */
    private function defaultCourses(string $centerSlug): array
    {
        $data = $this->readJson(database_path(self::DATA_DIRECTORY.'/'.$centerSlug.'-courses.json'));

        return array_values(array_filter(is_array($data['courses'] ?? null) ? $data['courses'] : [], 'is_array'));
    }

    /** @return array<string, array<string, mixed>> */
    private function defaults(): array
    {
        if ($this->defaults === null) {
            $data = $this->readJson(database_path(self::DATA_DIRECTORY.'/centers.json'));
            $this->defaults = array_filter($data, 'is_array');
        }

        return $this->defaults;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
