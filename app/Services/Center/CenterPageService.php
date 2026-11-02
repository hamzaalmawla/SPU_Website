<?php

declare(strict_types=1);

namespace App\Services\Center;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Center\CenterPageServiceInterface;
use App\DTOs\Center\CenterLandingPageDTO;
use App\DTOs\Center\CenterPageDTO;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Support\MediaUrlResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class CenterPageService implements CenterPageServiceInterface
{
    private const DEFAULT_IMAGE = '/images/uni-main-place.JPG';

    public function __construct(
        private readonly CenterCatalogServiceInterface $catalog,
    ) {}

    public function getLanding(string $locale): CenterLandingPageDTO
    {
        return $this->landingDto($locale, $this->catalog->content('centers.landing'));
    }

    public function getPage(string $centerSlug, string $subpageSlug, string $locale, array $filters = []): ?CenterPageDTO
    {
        if (! $this->catalog->isCenter($centerSlug) || ($subpageSlug !== '' && ! in_array($subpageSlug, CenterCatalogServiceInterface::SUBPAGES, true))) {
            return null;
        }

        return $this->pageDto($centerSlug, $subpageSlug, $locale, $filters, []);
    }

    public function previewLanding(string $locale, array $payload): CenterLandingPageDTO
    {
        return $this->landingDto($locale, $payload);
    }

    public function previewPage(string $targetKey, string $locale, array $payload, string $subpageSlug = ''): ?CenterPageDTO
    {
        $target = $this->catalog->parseTarget($targetKey);

        if ($target === null || $target['center'] === null) {
            return null;
        }

        $subpage = match ($target['part']) {
            'staff' => 'staff',
            'courses' => $subpageSlug === 'elective-courses' ? 'elective-courses' : 'required-courses',
            default => '',
        };

        return $this->pageDto((string) $target['center'], $subpage, $locale, [], [$target['part'] => $payload]);
    }

    public function staffOptions(string $centerSlug, string $locale): array
    {
        if (! $this->catalog->isCenter($centerSlug)) {
            return [];
        }

        return FacultyMember::query()
            ->where('center_slug', $centerSlug)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (FacultyMember $member): array => [(int) $member->getKey() => $this->memberName($member, $locale) ?? (string) $member->slug])
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function landingDto(string $locale, array $payload): CenterLandingPageDTO
    {
        $content = $this->localized($payload, $locale);
        $cards = [];

        foreach ($this->catalog->centerSlugs() as $centerSlug) {
            $page = $this->catalog->content('centers.'.$centerSlug);
            $pageContent = $this->localized($page, $locale);
            $title = trim((string) ($pageContent['hero']['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $cards[] = [
                'slug' => $centerSlug,
                'title' => $title,
                'summary' => trim((string) ($pageContent['hero']['summary'] ?? '')),
                'image' => $this->image($page['heroImage'] ?? null),
                'url' => '/'.$locale.'/centers/'.$centerSlug,
            ];
        }

        foreach (is_array($content['extraCards'] ?? null) ? $content['extraCards'] : [] as $card) {
            $title = is_array($card) ? trim((string) ($card['title'] ?? '')) : '';
            $url = is_array($card) ? trim((string) ($card['url'] ?? '')) : '';

            if ($title === '' || $url === '') {
                continue;
            }

            $cards[] = [
                'slug' => '',
                'title' => $title,
                'summary' => trim((string) ($card['summary'] ?? '')),
                'image' => $this->image($card['image'] ?? null),
                'url' => $url,
            ];
        }

        $heroImage = $this->image($payload['heroImage'] ?? null);
        $title = trim((string) ($content['hero']['title'] ?? '')) ?: ($locale === 'ar' ? 'المراكز' : 'Centers');

        return new CenterLandingPageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            content: $content,
            heroImage: $heroImage,
            cards: $cards,
            seoTitle: trim((string) ($content['seoTitle'] ?? '')) ?: $title,
            seoDescription: trim((string) ($content['seoDescription'] ?? '')) ?: trim(strip_tags((string) ($content['hero']['summary'] ?? ''))),
            seoImage: $heroImage,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, array<string, mixed>>  $overrides  draft payloads by part (preview)
     */
    private function pageDto(string $centerSlug, string $subpageSlug, string $locale, array $filters, array $overrides): CenterPageDTO
    {
        $payloads = [];
        foreach (['page', 'staff', 'courses'] as $part) {
            $key = $part === 'page' ? 'centers.'.$centerSlug : 'centers.'.$centerSlug.'.'.$part;
            $payloads[$part] = $overrides[$part] ?? $this->catalog->content($key);
        }

        $pageContent = $this->localized($payloads['page'], $locale);
        $staffContent = $this->localized($payloads['staff'], $locale);
        $courseContent = $this->localized($payloads['courses'], $locale);
        $centerName = trim((string) ($pageContent['hero']['title'] ?? '')) ?: $centerSlug;
        $base = '/'.$locale.'/centers/'.$centerSlug;
        $heroImage = $this->image($payloads['page']['heroImage'] ?? null);

        $managerId = is_numeric($payloads['page']['managerMemberId'] ?? null) ? (int) $payloads['page']['managerMemberId'] : null;
        $managerTitle = trim((string) ($pageContent['manager']['title'] ?? '')) ?: ($locale === 'ar' ? 'مدير الوحدة' : 'Unit Manager');
        $staff = $this->staff($centerSlug, $locale, $managerId, $managerTitle);
        $manager = null;
        foreach ($staff as $member) {
            if ($member['isManager']) {
                $manager = [
                    'name' => $member['name'],
                    'image' => $member['image'],
                    'url' => $member['profileUrl'],
                    'title' => $managerTitle,
                    'message' => trim((string) ($pageContent['manager']['message'] ?? '')),
                ];
            }
        }

        $content = match ($subpageSlug) {
            '' => $pageContent,
            'staff' => $staffContent,
            'required-courses' => [...$courseContent, 'hero' => is_array($courseContent['hero'] ?? null) ? $courseContent['hero'] : []],
            default => [...$courseContent, 'hero' => is_array($courseContent['electiveHero'] ?? null) ? $courseContent['electiveHero'] : []],
        };

        $allCourses = $this->catalog->courses($centerSlug, $payloads['courses']);
        $facultyNames = $this->facultyNames($allCourses, $locale);
        $courses = [];
        $filterOptions = [];
        $activeFilters = [];

        if (in_array($subpageSlug, ['required-courses', 'elective-courses'], true)) {
            $wantRequired = $subpageSlug === 'required-courses';
            $listed = array_values(array_filter($allCourses, static fn (array $course): bool => $course['required'] === $wantRequired));

            foreach ($listed as $course) {
                foreach ($course['faculties'] as $link) {
                    if (isset($facultyNames[$link['faculty']])) {
                        $filterOptions[$link['faculty']] = $facultyNames[$link['faculty']];
                    }
                }
            }

            $faculty = is_string($filters['faculty'] ?? null) ? trim($filters['faculty']) : '';
            if ($faculty !== '' && isset($filterOptions[$faculty])) {
                $activeFilters['faculty'] = $faculty;
                $listed = array_values(array_filter($listed, static fn (array $course): bool => in_array($faculty, array_column($course['faculties'], 'faculty'), true)));
            }

            $courses = $this->courseCards($listed, $allCourses, $locale, $base, $facultyNames);
        }

        $navigation = [];
        foreach (['', ...CenterCatalogServiceInterface::SUBPAGES] as $slug) {
            $hero = match ($slug) {
                '' => $pageContent['hero'] ?? [],
                'staff' => $staffContent['hero'] ?? [],
                'required-courses' => $courseContent['hero'] ?? [],
                default => $courseContent['electiveHero'] ?? [],
            };
            $navigation[] = [
                'slug' => $slug,
                'label' => trim((string) ($hero['title'] ?? '')) ?: $this->subpageLabel($slug, $locale, $centerName),
                'summary' => trim((string) ($hero['summary'] ?? '')),
                'url' => $base.($slug !== '' ? '/'.$slug : ''),
                'active' => $slug === $subpageSlug,
            ];
        }

        $title = trim((string) ($content['hero']['title'] ?? '')) ?: $this->subpageLabel($subpageSlug, $locale, $centerName);
        $path = $base.($subpageSlug !== '' ? '/'.$subpageSlug : '');
        $gallery = array_values(array_filter(array_map(
            fn (mixed $image): ?string => is_string($image) && trim($image) !== '' ? $this->image($image) : null,
            is_array($payloads['page']['gallery'] ?? null) ? $payloads['page']['gallery'] : [],
        )));

        return new CenterPageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            subpageSlug: $subpageSlug,
            center: [
                'slug' => $centerSlug,
                'name' => $centerName,
                'url' => $base,
                'heroImage' => $heroImage,
                'gallery' => $gallery,
            ],
            content: $content,
            manager: $manager,
            staff: $subpageSlug === 'staff' || $subpageSlug === '' ? $staff : [],
            courses: $courses,
            navigation: $navigation,
            filters: $activeFilters,
            filterOptions: $filterOptions,
            title: $title,
            path: $path,
            seoTitle: trim((string) ($content['seoTitle'] ?? '')) ?: ($subpageSlug === '' ? $title : $title.' | '.$centerName),
            seoDescription: trim((string) ($content['seoDescription'] ?? '')) ?: trim(strip_tags((string) ($content['hero']['summary'] ?? ''))),
            seoImage: $heroImage,
        );
    }

    /**
     * The center's public staff profiles, the manager first.
     *
     * @return array<int, array{slug: string, name: string, position: string, image: string|null, profileUrl: string, isManager: bool}>
     */
    private function staff(string $centerSlug, string $locale, ?int $managerId, string $managerTitle): array
    {
        $members = FacultyMember::query()
            ->public()
            ->where('center_slug', $centerSlug)
            ->with(['translations', 'photoMedia'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $cards = [];
        foreach ($members as $member) {
            $name = $this->memberName($member, $locale);
            $slug = (string) $member->slug;

            if ($name === null || preg_match('/^[A-Za-z0-9-]+$/', $slug) !== 1) {
                continue;
            }

            $translation = $member->translations->firstWhere('locale', $locale) ?? $member->translations->first();
            $isManager = $managerId !== null && (int) $member->getKey() === $managerId;

            $cards[] = [
                'slug' => $slug,
                'name' => $name,
                'position' => $isManager ? $managerTitle : trim((string) ($translation?->position ?: $translation?->title ?: '')),
                'image' => $this->memberImage($member),
                'profileUrl' => '/'.$locale.'/about/profile/'.$slug,
                'isManager' => $isManager,
            ];
        }

        usort($cards, static fn (array $left, array $right): int => (int) $right['isManager'] <=> (int) $left['isManager']);

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $listed
     * @param  list<array<string, mixed>>  $allCourses
     * @param  array<string, string>  $facultyNames
     * @return array<int, array<string, mixed>>
     */
    private function courseCards(array $listed, array $allCourses, string $locale, string $base, array $facultyNames): array
    {
        $byId = [];
        foreach ($allCourses as $course) {
            $byId[$course['id']] = $course;
        }

        $memberIds = [];
        foreach ($listed as $course) {
            foreach ($course['teachers'] as $teacher) {
                if ($teacher['enabled']) {
                    array_push($memberIds, ...$teacher['memberIds']);
                }
            }
        }
        $teacherCards = $this->memberCards(array_values(array_unique($memberIds)), $locale);

        return array_map(function (array $course) use ($byId, $locale, $base, $facultyNames, $teacherCards): array {
            $teachers = [];
            foreach ($course['teachers'] as $part => $teacher) {
                if (! $teacher['enabled']) {
                    continue;
                }
                foreach ($teacher['memberIds'] as $memberId) {
                    if (isset($teacherCards[$memberId])) {
                        $teachers[] = ['part' => $part, ...$teacherCards[$memberId]];
                    }
                }
            }

            $prerequisites = [];
            foreach ($course['prerequisites'] as $prerequisiteId) {
                if (isset($byId[$prerequisiteId])) {
                    $prerequisite = $byId[$prerequisiteId];
                    $prerequisites[] = [
                        'title' => $this->text($prerequisite, 'title', $locale),
                        'url' => $base.'/'.($prerequisite['required'] ? 'required-courses' : 'elective-courses').'#course-'.$prerequisite['id'],
                    ];
                }
            }

            $faculties = [];
            foreach ($course['faculties'] as $link) {
                $faculties[] = [
                    'faculty' => $link['faculty'],
                    'name' => $facultyNames[$link['faculty']] ?? $link['faculty'],
                    'code' => $link['code'],
                    'credits' => $link['credits'] ?? null,
                    'url' => '/'.$locale.'/faculties/'.$link['faculty'].'/study-plan',
                ];
            }

            return [
                'id' => $course['id'],
                'anchor' => 'course-'.$course['id'],
                'code' => $course['code'],
                'title' => $this->text($course, 'title', $locale),
                'description' => $this->text($course, 'description', $locale),
                'required' => $course['required'],
                'credits' => $course['credits'],
                'hours' => $course['hours'],
                'prerequisites' => $prerequisites,
                'faculties' => $faculties,
                'teachers' => $teachers,
                'specificationUrl' => $course['specificationUrl'],
                'lessons' => array_map(fn (array $lesson): array => [
                    'type' => $lesson['type'],
                    'title' => $this->text($lesson, 'title', $locale),
                    'url' => $lesson['pdfUrl'],
                ], $course['lessons']),
            ];
        }, $listed);
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array<int, array{name: string, image: string|null, url: string}>
     */
    private function memberCards(array $memberIds, string $locale): array
    {
        if ($memberIds === []) {
            return [];
        }

        $cards = [];
        foreach (FacultyMember::query()->public()->whereKey($memberIds)->with(['translations', 'photoMedia'])->get() as $member) {
            $name = $this->memberName($member, $locale);

            if ($name !== null) {
                $cards[(int) $member->getKey()] = [
                    'name' => $name,
                    'image' => $this->memberImage($member),
                    'url' => '/'.$locale.'/about/profile/'.$member->slug,
                ];
            }
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @return array<string, string>
     */
    private function facultyNames(array $courses, string $locale): array
    {
        $slugs = [];
        foreach ($courses as $course) {
            foreach ($course['faculties'] as $link) {
                $slugs[$link['faculty']] = true;
            }
        }

        if ($slugs === []) {
            return [];
        }

        /** @var EloquentCollection<int, Faculty> $faculties */
        $faculties = Faculty::query()
            ->whereIn('public_slug', array_keys($slugs))
            ->where('is_enabled', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $names = [];
        foreach ($faculties as $faculty) {
            $translation = $faculty->translations->firstWhere('locale', $locale) ?? $faculty->translations->first();
            $name = trim((string) ($translation?->name ?? ''));

            if ($name !== '') {
                $names[(string) $faculty->public_slug] = $name;
            }
        }

        return $names;
    }

    private function memberName(FacultyMember $member, string $locale): ?string
    {
        $translation = $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', $locale === 'ar' ? 'en' : 'ar');
        $name = trim((string) ($translation?->full_name ?? ''));

        return $name !== '' ? $name : null;
    }

    private function memberImage(FacultyMember $member): ?string
    {
        return $member->photoMedia instanceof MediaAsset
            ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
            : MediaUrlResolver::resolveLegacy($member->legacy_photo_path);
    }

    /** @param array<string, mixed> $item */
    private function text(array $item, string $field, string $locale): string
    {
        $first = trim((string) ($item[$field.($locale === 'ar' ? 'Ar' : 'En')] ?? ''));

        return $first !== '' ? $first : trim((string) ($item[$field.($locale === 'ar' ? 'En' : 'Ar')] ?? ''));
    }

    private function subpageLabel(string $subpageSlug, string $locale, string $centerName): string
    {
        $ar = $locale === 'ar';

        return match ($subpageSlug) {
            'staff' => $ar ? 'الكادر التدريسي' : 'Teaching Staff',
            'required-courses' => $ar ? 'المقررات الإجبارية' : 'Required Courses',
            'elective-courses' => $ar ? 'المقررات الاختيارية' : 'Elective Courses',
            default => $centerName,
        };
    }

    /**
     * The content in this language, else the other one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function localized(array $payload, string $locale): array
    {
        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];

        if (is_array($translations[$locale] ?? null) && $translations[$locale] !== []) {
            return $translations[$locale];
        }

        $other = $translations[$locale === 'ar' ? 'en' : 'ar'] ?? null;

        return is_array($other) ? $other : [];
    }

    private function image(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return self::DEFAULT_IMAGE;
        }

        return MediaUrlResolver::resolve($value) ?? self::DEFAULT_IMAGE;
    }

    private function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }
}
