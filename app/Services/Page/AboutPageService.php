<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\DTOs\About\AboutContentPageDTO;
use App\DTOs\About\AboutLandingDTO;
use App\DTOs\About\AboutVisionMissionDTO;
use App\DTOs\About\LeadershipDirectoryDTO;
use App\DTOs\About\PartnershipDirectoryDTO;
use App\DTOs\About\StaffDirectoryDTO;
use App\DTOs\About\StaffDirectoryItemDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\PersonDTO;
use App\Models\Content\Directorate;
use App\Models\Content\DirectorateTranslation;
use App\Models\Content\Partnership;
use App\Models\Content\PartnershipTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyTranslation;
use App\Models\Media\MediaAsset;
use App\Models\Page\AboutPage;
use App\Models\Page\AboutPageTranslation;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\MediaUrlResolver;
use Illuminate\Support\Collection;

final class AboutPageService implements AboutPageServiceInterface
{
    private const STAFF_PER_PAGE = 9;

    private const PARTNERSHIPS_PER_PAGE = 6;

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly AboutNavigationCardServiceInterface $navigationCardService,
    ) {}

    public function getAboutLanding(string $locale): AboutLandingDTO
    {
        $published = $this->publishedLocalizedPayload('about.landing', $locale);

        if ($published !== null) {
            return $this->landingDto($locale, $published);
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', 'about')
            ->with('translations')
            ->firstOrFail();
        $translation = $this->aboutTranslation($page, $locale);
        $payload = is_array($page->payload_json) ? $page->payload_json : [];

        return $this->landingDto($locale, $this->landingPayloadFromPage($page, $locale));
    }

    public function buildPreviewAboutLanding(string $locale, array $content): AboutLandingDTO
    {
        return $this->landingDto($locale, $content);
    }

    public function getVisionMission(string $locale): AboutVisionMissionDTO
    {
        $published = $this->publishedLocalizedPayload('about.vision-mission', $locale);

        if ($published !== null) {
            return $this->visionMissionDto($locale, $published);
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', 'vision-mission')
            ->with('translations')
            ->firstOrFail();

        return $this->visionMissionDto($locale, $this->contentPayloadFromPage($page, $locale));
    }

    public function buildPreviewVisionMission(string $locale, array $content): AboutVisionMissionDTO
    {
        return $this->visionMissionDto($locale, $content);
    }

    public function getEditablePayload(string $targetKey): array
    {
        if (! in_array($targetKey, ['about.landing', 'about.vision-mission', 'about.history', 'about.leadership', 'about.directorates', 'about.partnerships', 'about.directorates_staff', 'about.quality-policy', 'about.ethical-charter', 'about.organizational-structure', 'about.accreditation', 'about.why-spu'], true)) {
            throw new \InvalidArgumentException('Unsupported about target.');
        }

        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);

        if (is_array($published['translations']['ar'] ?? null) && is_array($published['translations']['en'] ?? null)) {
            return [
                'translations' => [
                    'ar' => $published['translations']['ar'],
                    'en' => $published['translations']['en'],
                ],
            ];
        }

        if ($targetKey === 'about.landing') {
            $page = AboutPage::query()
                ->public()
                ->where('slug', 'about')
                ->with('translations')
                ->firstOrFail();

            return [
                'translations' => [
                    'ar' => $this->landingPayloadFromPage($page, 'ar'),
                    'en' => $this->landingPayloadFromPage($page, 'en'),
                ],
            ];
        }

        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            throw new \InvalidArgumentException('Unsupported about target.');
        }

        if ($slug === 'directorates_staff') {
            return [
                'translations' => [
                    'ar' => $this->staffDirectoryPayload('ar'),
                    'en' => $this->staffDirectoryPayload('en'),
                ],
            ];
        }

        $fallback = $this->importedContentPayload($slug);

        if ($fallback !== null) {
            return [
                'translations' => [
                    'ar' => $this->localizedImportedPayload($fallback, 'ar'),
                    'en' => $this->localizedImportedPayload($fallback, 'en'),
                ],
            ];
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', $slug)
            ->with('translations')
            ->firstOrFail();

        return [
            'translations' => [
                'ar' => $this->contentPayloadFromPage($page, 'ar'),
                'en' => $this->contentPayloadFromPage($page, 'en'),
            ],
        ];
    }

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO
    {
        $published = $this->publishedLocalizedPayload('about.'.$slug, $locale);

        if ($published !== null) {
            return $this->contentPageDto($slug, $locale, $published);
        }

        $fallback = $this->importedContentPayload($slug);

        if ($fallback !== null) {
            return $this->contentPageDto($slug, $locale, $this->localizedImportedPayload($fallback, $locale));
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', $slug)
            ->with('translations')
            ->first();

        if (! $page instanceof AboutPage) {
            return null;
        }

        return $this->contentPageDto($slug, $locale, $this->contentPayloadFromPage($page, $locale));
    }

    public function buildPreviewContentPage(string $targetKey, string $locale, array $content): ?AboutContentPageDTO
    {
        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            return null;
        }

        return $this->contentPageDto($slug, $locale, $content);
    }

    public function getStaffDirectoryPage(string $locale): AboutContentPageDTO
    {
        $published = $this->publishedLocalizedPayload('about.directorates_staff', $locale);

        return $this->contentPageDto('directorates_staff', $locale, $published ?? $this->staffDirectoryPayload($locale));
    }

    public function getStaffDirectory(
        string $locale,
        ?string $requestedFaculty = null,
        int $requestedPage = 1,
    ): StaffDirectoryDTO {
        $facultyLabels = $this->staffFacultyLabels($locale);
        $people = Person::query()
            ->public()
            ->whereIn('category', ['rector', 'vice_president', 'dean', 'council'])
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Person $person) use ($locale, $facultyLabels): StaffDirectoryItemDTO {
                $translation = $this->personTranslation($person, $locale);
                $facultySlug = is_string($person->faculty_scope_slug) && $person->faculty_scope_slug !== ''
                    ? $person->faculty_scope_slug
                    : null;

                return new StaffDirectoryItemDTO(
                    sourceType: 'person',
                    slug: (string) $person->slug,
                    name: (string) $translation->name,
                    role: (string) $translation->role,
                    image: $person->image,
                    facultySlug: $facultySlug,
                    facultyName: $facultySlug !== null ? ($facultyLabels[$facultySlug] ?? null) : null,
                );
            });
        $facultyMembers = FacultyMember::query()
            ->public()
            ->with(['translations', 'faculty.translations', 'photoMedia'])
            ->orderBy('sort_order')
            ->get();
        $legacyMediaByTargetId = $this->legacyMediaByTargetId('faculty_members', $facultyMembers->pluck('id')->all());
        $facultyMembers = $facultyMembers
            ->map(function (FacultyMember $member) use ($locale, $facultyLabels, $legacyMediaByTargetId): ?StaffDirectoryItemDTO {
                $translation = $this->facultyMemberTranslation($member, $locale);

                if (! $translation instanceof FacultyMemberTranslation) {
                    return null;
                }

                $legacyPhoto = $member->legacy_photo_path
                    ?? ($legacyMediaByTargetId[(int) $member->getKey()]['legacy_photo'] ?? null);

                $facultySlug = $member->faculty instanceof Faculty
                    ? (string) ($member->faculty->faculty_scope_slug ?: $member->faculty->public_slug ?: $member->faculty->slug)
                    : null;

                return new StaffDirectoryItemDTO(
                    sourceType: 'faculty-member',
                    slug: (string) $member->slug,
                    name: (string) $translation->full_name,
                    role: (string) ($translation->position ?: $translation->title ?: ''),
                    image: $member->photoMedia instanceof MediaAsset
                        ? MediaUrlResolver::resolve($member->photoMedia->path, $member->photoMedia->disk)
                        : MediaUrlResolver::resolveLegacy($legacyPhoto),
                    facultySlug: $facultySlug,
                    facultyName: $facultySlug !== null ? ($facultyLabels[$facultySlug] ?? null) : null,
                );
            })
            ->filter(fn (?StaffDirectoryItemDTO $item): bool => $item !== null)
            ->values();
        $allItems = $people->concat($facultyMembers)->values();
        $availableFacultySlugs = $allItems
            ->pluck('facultySlug')
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->values();
        $facultyFilters = collect($facultyLabels)
            ->filter(fn (string $label, string $slug): bool => $availableFacultySlugs->contains($slug))
            ->map(fn (string $label, string $slug): array => ['slug' => $slug, 'label' => $label])
            ->values()
            ->all();
        $activeFaculty = is_string($requestedFaculty) && $availableFacultySlugs->contains($requestedFaculty)
            ? $requestedFaculty
            : '';
        $filteredItems = $activeFaculty !== ''
            ? $allItems->filter(fn (StaffDirectoryItemDTO $item): bool => $item->facultySlug === $activeFaculty)->values()
            : $allItems;
        $totalItems = $filteredItems->count();
        $totalPages = max(1, (int) ceil($totalItems / self::STAFF_PER_PAGE));
        $currentPage = min(max($requestedPage, 1), $totalPages);

        return new StaffDirectoryDTO(
            items: $filteredItems->slice(($currentPage - 1) * self::STAFF_PER_PAGE, self::STAFF_PER_PAGE)->values(),
            facultyFilters: $facultyFilters,
            activeFaculty: $activeFaculty,
            currentPage: $currentPage,
            totalPages: $totalPages,
            totalItems: $totalItems,
            perPage: self::STAFF_PER_PAGE,
        );
    }

    public function getLeadershipProfiles(string $locale): Collection
    {
        return $this->mapPersons(
            Person::query()->public()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getLeadershipDirectory(string $locale, ?string $requestedFaculty = null): LeadershipDirectoryDTO
    {
        $people = $this->getLeadershipProfiles($locale);
        $deanFacultySlugs = $people
            ->filter(fn (PersonDTO $person): bool => $person->category === 'dean' && $person->facultySlug !== null)
            ->pluck('facultySlug')
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->values();
        $facultyFilters = Faculty::query()
            ->enabled()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Faculty $faculty) use ($locale, $deanFacultySlugs): ?array {
                $slug = (string) ($faculty->public_slug ?: $faculty->slug);
                $scopeSlug = (string) ($faculty->faculty_scope_slug ?: $slug);

                if (! $deanFacultySlugs->contains($slug) && ! $deanFacultySlugs->contains($scopeSlug)) {
                    return null;
                }

                $translation = $this->facultyTranslation($faculty, $locale);

                return ['slug' => $scopeSlug, 'label' => (string) $translation->name];
            })
            ->filter(fn (?array $filter): bool => $filter !== null)
            ->values()
            ->all();
        $validSlugs = collect($facultyFilters)->pluck('slug');
        $activeFaculty = is_string($requestedFaculty) && $validSlugs->contains($requestedFaculty)
            ? $requestedFaculty
            : '';

        return new LeadershipDirectoryDTO(
            people: $people,
            facultyFilters: $facultyFilters,
            activeFaculty: $activeFaculty,
        );
    }

    public function getDirectorates(string $locale): Collection
    {
        return $this->mapDirectorates(
            Directorate::query()->public()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO
    {
        $directorate = Directorate::query()->public()->where('slug', $slug)->with('translations')->first();

        if (! $directorate instanceof Directorate) {
            return null;
        }

        return $this->mapDirectorate($directorate, $locale);
    }

    public function getPartnerships(
        string $locale,
        ?string $requestedCategory = null,
        ?string $requestedQuery = null,
        int $requestedPage = 1,
    ): PartnershipDirectoryDTO {
        $categoryLabels = [
            'academic' => $locale === 'ar' ? 'أكاديمي' : 'Academic',
            'research' => $locale === 'ar' ? 'بحثي' : 'Research',
            'clinical' => $locale === 'ar' ? 'طبي وسريري' : 'Clinical',
        ];
        $activeCategory = is_string($requestedCategory) && array_key_exists($requestedCategory, $categoryLabels)
            ? $requestedCategory
            : '';
        $query = trim((string) $requestedQuery);
        $partnerships = $this->mapPartnerships(
            Partnership::query()->public()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        )->filter(function (PartnershipDTO $partnership) use ($activeCategory, $query): bool {
            if ($activeCategory !== '' && $partnership->categoryKey !== $activeCategory) {
                return false;
            }

            if ($query === '') {
                return true;
            }

            return mb_stripos(implode(' ', [
                $partnership->name,
                $partnership->category,
                $partnership->description,
                (string) $partnership->scope,
            ]), $query) !== false;
        })->values();
        $totalItems = $partnerships->count();
        $totalPages = max(1, (int) ceil($totalItems / self::PARTNERSHIPS_PER_PAGE));
        $currentPage = min(max($requestedPage, 1), $totalPages);

        return new PartnershipDirectoryDTO(
            items: $partnerships->slice(($currentPage - 1) * self::PARTNERSHIPS_PER_PAGE, self::PARTNERSHIPS_PER_PAGE)->values(),
            categories: collect($categoryLabels)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            activeCategory: $activeCategory,
            query: $query,
            currentPage: $currentPage,
            totalPages: $totalPages,
            totalItems: $totalItems,
            perPage: self::PARTNERSHIPS_PER_PAGE,
        );
    }

    /** @return array<int, array<string, string>> */
    public function getAboutSubPages(string $locale, ?string $excludeTargetKey = null): array
    {
        return $this->buildAboutNavigationCards($locale, $excludeTargetKey);
    }

    public function mapPerson(Person $person, string $locale): PersonDTO
    {
        $translation = $this->personTranslation($person, $locale);

        return new PersonDTO(
            id: (int) $person->getKey(),
            slug: (string) $person->slug,
            name: (string) $translation->name,
            role: (string) $translation->role,
            category: $person->category,
            facultySlug: $person->faculty_scope_slug,
            bio: $translation->bio,
            quote: $translation->quote,
            image: $person->image,
            email: $person->email,
            profileUrl: '/'.$locale.'/about/profile/person/'.$person->slug,
        );
    }

    /** @param Collection<int, Person> $persons @return Collection<int, PersonDTO> */
    private function mapPersons(Collection $persons, string $locale): Collection
    {
        return $persons->map(fn (Person $person): PersonDTO => $this->mapPerson($person, $locale))->values();
    }

    /** @param Collection<int, Directorate> $directorates @return Collection<int, DirectorateDTO> */
    private function mapDirectorates(Collection $directorates, string $locale): Collection
    {
        return $directorates->map(fn (Directorate $directorate): DirectorateDTO => $this->mapDirectorate($directorate, $locale))->values();
    }

    private function mapDirectorate(Directorate $directorate, string $locale): DirectorateDTO
    {
        $translation = $this->directorateTranslation($directorate, $locale);

        return new DirectorateDTO(
            id: (int) $directorate->getKey(),
            slug: (string) $directorate->slug,
            title: (string) $translation->title,
            summary: (string) ($translation->summary ?? ''),
            description: (string) ($translation->description ?? ''),
            services: is_array($translation->services_json) ? $translation->services_json : [],
            links: is_array($translation->links_json) ? $translation->links_json : [],
            icon: $directorate->icon,
            email: $directorate->email,
            location: $directorate->location,
        );
    }

    /** @param Collection<int, Partnership> $partnerships @return Collection<int, PartnershipDTO> */
    private function mapPartnerships(Collection $partnerships, string $locale): Collection
    {
        return $partnerships->map(function (Partnership $partnership) use ($locale): PartnershipDTO {
            $translation = $this->partnershipTranslation($partnership, $locale);

            return new PartnershipDTO(
                id: (int) $partnership->getKey(),
                slug: (string) $partnership->slug,
                categoryKey: (string) ($partnership->category_key ?? ''),
                statusKey: (string) ($partnership->status_key ?? 'active'),
                name: (string) $translation->name,
                category: (string) ($translation->category ?? ''),
                status: (string) ($translation->status ?? ''),
                establishedLabel: (string) ($translation->established_label ?? ''),
                description: (string) ($translation->description ?? ''),
                logo: $partnership->logo,
                websiteUrl: $partnership->website_url,
                scope: $translation->scope,
                signedAt: $partnership->signed_at?->toDateString(),
            );
        })->values();
    }

    /** @param array<string, mixed> $content */
    private function landingDto(string $locale, array $content): AboutLandingDTO
    {
        return new AboutLandingDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            title: (string) ($content['title'] ?? ''),
            headline: (string) ($content['headline'] ?? ($content['title'] ?? '')),
            summary: (string) ($content['summary'] ?? ''),
            quote: (string) ($content['quote'] ?? ''),
            description: (string) ($content['description'] ?? ''),
            badge: (string) ($content['badge'] ?? ($content['title'] ?? '')),
            imagePrimary: (string) ($content['imagePrimary'] ?? '/images/about-hero-1.webp'),
            imageSecondary: (string) ($content['imageSecondary'] ?? '/images/about-hero-2.jpg'),
            imageOverview: (string) ($content['imageOverview'] ?? '/images/about/hero-img.jpg'),
            stats: $this->listValue($content, 'stats'),
            storyItems: $this->listValue($content, 'storyItems'),
            highlights: $this->listValue($content, 'highlights'),
            subPages: $this->buildAboutNavigationCards($locale),
            seoTitle: (string) ($content['seoTitle'] ?? ($content['title'] ?? '')),
            seoDescription: (string) ($content['seoDescription'] ?? ($content['summary'] ?? '')),
            seoImage: (string) ($content['seoImage'] ?? ($content['imagePrimary'] ?? '/images/about-hero-1.webp')),
        );
    }

    /** @return array<string, mixed> */
    private function landingPayloadFromPage(AboutPage $page, string $locale): array
    {
        $translation = $this->aboutTranslation($page, $locale);
        $payload = is_array($page->payload_json) ? $page->payload_json : [];

        return [
            'title' => (string) $translation->title,
            'headline' => (string) ($translation->headline ?: $translation->title),
            'summary' => (string) ($translation->summary ?? ''),
            'quote' => (string) ($payload[$locale]['quote'] ?? ''),
            'description' => (string) ($payload[$locale]['description'] ?? ''),
            'badge' => (string) ($payload[$locale]['badge'] ?? $translation->title),
            'imagePrimary' => (string) ($payload['images']['primary'] ?? '/images/about-hero-1.webp'),
            'imageSecondary' => (string) ($payload['images']['secondary'] ?? '/images/about-hero-2.jpg'),
            'imageOverview' => (string) ($payload['images']['overview'] ?? '/images/about/hero-img.jpg'),
            'stats' => $this->localizedArray($this->listValue($payload, 'stats'), $locale),
            'storyItems' => $this->localizedArray($this->listValue($payload, 'story_items'), $locale),
            'highlights' => $this->localizedArray($this->listValue($payload, 'highlights'), $locale),
            'subPages' => $this->localizedArray($this->listValue($payload, 'sub_pages'), $locale),
            'seoTitle' => (string) $translation->title,
            'seoDescription' => (string) ($translation->summary ?? ''),
            'seoImage' => (string) ($payload['images']['primary'] ?? '/images/about-hero-1.webp'),
        ];
    }

    /** @param array<string, mixed> $content */
    private function contentPageDto(string $slug, string $locale, array $content): AboutContentPageDTO
    {
        return new AboutContentPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            slug: $slug,
            title: (string) ($content['title'] ?? ''),
            headline: (string) ($content['headline'] ?? ($content['title'] ?? '')),
            summary: (string) ($content['summary'] ?? ''),
            heroImage: (string) ($content['heroImage'] ?? '/images/about-hero-2.webp'),
            sections: is_array($content['sections'] ?? null) ? $content['sections'] : [],
            badge: (string) ($content['badge'] ?? ''),
            intro: collect(is_array($content['intro'] ?? null) ? $content['intro'] : [])
                ->map(static fn (mixed $paragraph): string => is_string($paragraph)
                    ? $paragraph
                    : (is_array($paragraph) && is_string($paragraph['value'] ?? null) ? $paragraph['value'] : ''))
                ->filter()
                ->values()
                ->all(),
            stats: $this->listValue($content, 'stats'),
            contentImage: (string) ($content['contentImage'] ?? ''),
            seoTitle: (string) ($content['seoTitle'] ?? ($content['title'] ?? '')),
            seoDescription: (string) ($content['seoDescription'] ?? ($content['summary'] ?? '')),
            seoImage: (string) ($content['seoImage'] ?? ($content['heroImage'] ?? '/images/about/hero-img.jpg')),
        );
    }

    /** @param array<string, mixed> $content */
    private function visionMissionDto(string $locale, array $content): AboutVisionMissionDTO
    {
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $title = (string) ($content['title'] ?? '');
        $summary = (string) ($content['summary'] ?? '');
        $heroImage = (string) ($content['heroImage'] ?? '/images/about/hero-img.jpg');
        $cards = collect($this->listValue($sections, 'cards'))
            ->map(static fn (array $card): array => [
                'icon' => (string) ($card['icon'] ?? ''),
                'title' => (string) ($card['title'] ?? ''),
                'body' => (string) ($card['body'] ?? ''),
            ])
            ->values()
            ->all();
        $pillars = collect($this->listValue($sections, 'pillars'))
            ->map(static fn (array $pillar): array => [
                'title' => (string) ($pillar['title'] ?? ''),
                'summary' => (string) ($pillar['summary'] ?? ''),
            ])
            ->values()
            ->all();

        return new AboutVisionMissionDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            title: $title,
            summary: $summary,
            heroImage: $heroImage,
            cardsTitle: (string) ($sections['cardsTitle'] ?? ($locale === 'ar' ? 'توجه الجامعة' : 'Our Direction')),
            cards: $cards,
            pillarsTitle: (string) ($sections['pillarsTitle'] ?? ($locale === 'ar' ? 'الأعمدة الاستراتيجية' : 'Strategic Pillars')),
            pillars: $pillars,
            seoTitle: (string) ($content['seoTitle'] ?? $title),
            seoDescription: (string) ($content['seoDescription'] ?? $summary),
            seoImage: (string) ($content['seoImage'] ?? $heroImage),
        );
    }

    /** @return array<string, mixed> */
    private function contentPayloadFromPage(AboutPage $page, string $locale): array
    {
        $translation = $this->aboutTranslation($page, $locale);
        $slug = (string) $page->slug;

        return [
            'title' => (string) $translation->title,
            'headline' => (string) ($translation->headline ?: $translation->title),
            'summary' => (string) ($translation->summary ?? ''),
            'heroImage' => (string) ($page->hero_image ?: '/images/about-hero-2.webp'),
            'seoTitle' => (string) $translation->title,
            'seoDescription' => (string) ($translation->summary ?? ''),
            'seoImage' => (string) ($page->hero_image ?: '/images/about-hero-2.webp'),
            'badge' => '',
            'intro' => [],
            'stats' => [],
            'contentImage' => $slug === 'history' ? '/images/uni-main-place.JPG' : '',
            'sections' => $slug === 'history'
                ? $this->historySections($locale)
                : (is_array($translation->sections_json) ? $translation->sections_json : []),
        ];
    }

    private function slugFromTargetKey(string $targetKey): ?string
    {
        if (! str_starts_with($targetKey, 'about.') || $targetKey === 'about.landing') {
            return null;
        }

        return match (substr($targetKey, strlen('about.'))) {
            'history' => 'history',
            'vision-mission' => 'vision-mission',
            'leadership' => 'leadership',
            'directorates' => 'directorates',
            'directorates_staff' => 'directorates_staff',
            'partnerships' => 'partnerships',
            'quality-policy' => 'quality-policy',
            'ethical-charter' => 'ethical-charter',
            'organizational-structure' => 'organizational-structure',
            'accreditation' => 'accreditation',
            'why-spu' => 'why-spu',
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function importedContentPayload(string $slug): ?array
    {
        return match ($slug) {
            'quality-policy' => [
                'titleEn' => 'Quality Policy at SPU',
                'titleAr' => 'سياسة الجودة في الجامعة السورية الخاصة',
                'headlineEn' => 'Quality Policy at SPU',
                'headlineAr' => 'سياسة الجودة في الجامعة السورية الخاصة',
                'summaryEn' => 'SPU is committed to a comprehensive quality policy that supports continuous improvement across academic, administrative, and research activities.',
                'summaryAr' => 'تلتزم الجامعة السورية الخاصة بسياسة جودة شاملة تدعم التحسين المستمر في الأنشطة الأكاديمية والإدارية والبحثية.',
                'heroImage' => '/images/about/hero-img.jpg',
                'badgeEn' => 'Commitment to Excellence',
                'badgeAr' => 'الالتزام بالتميز',
                'intro' => [
                    ['valueEn' => 'SPU treats quality as a continuous institutional responsibility across teaching, administration, research, and student services.', 'valueAr' => 'تتعامل الجامعة السورية الخاصة مع الجودة بوصفها مسؤولية مؤسسية مستمرة تشمل التعليم والإدارة والبحث العلمي وخدمات الطلاب.'],
                    ['valueEn' => 'Academic and administrative processes are reviewed against approved regulations, measurable objectives, and feedback from the university community.', 'valueAr' => 'تُراجع العمليات الأكاديمية والإدارية وفق الأنظمة المعتمدة والأهداف القابلة للقياس وملاحظات مجتمع الجامعة.'],
                    ['valueEn' => 'Improvement priorities are translated into practical actions that strengthen learning outcomes and institutional performance.', 'valueAr' => 'تتحول أولويات التحسين إلى إجراءات عملية تعزز مخرجات التعلم والأداء المؤسسي.'],
                ],
                'sections' => [
                    ['titleEn' => 'Academic Excellence', 'titleAr' => 'التميز الأكاديمي', 'bodyEn' => 'Continuous review and development of curricula to meet evolving educational standards and labor market requirements.', 'bodyAr' => 'المراجعة والتطوير المستمر للمناهج لتلبية المعايير التعليمية المتطورة ومتطلبات سوق العمل.'],
                    ['titleEn' => 'Administrative Efficiency', 'titleAr' => 'الكفاءة الإدارية', 'bodyEn' => 'Streamlined administrative processes with clear procedures, accountability, and regular performance evaluation.', 'bodyAr' => 'عمليات إدارية مبسطة مع إجراءات واضحة ومساءلة وتقييم دوري للأداء.'],
                    ['titleEn' => 'Student Satisfaction', 'titleAr' => 'رضا الطلاب', 'bodyEn' => 'Regular assessment of student feedback to improve services, learning environments, and support systems.', 'bodyAr' => 'تقييم منتظم لملاحظات الطلاب لتحسين الخدمات وبيئات التعلم وأنظمة الدعم.'],
                    ['titleEn' => 'Continuous Improvement', 'titleAr' => 'التحسين المستمر', 'bodyEn' => 'Adoption of best practices and innovative approaches to enhance institutional performance and outcomes.', 'bodyAr' => 'اعتماد أفضل الممارسات والأساليب المبتكرة لتعزيز الأداء المؤسسي والنتائج.'],
                ],
            ],
            'ethical-charter' => [
                'titleEn' => 'Ethical Charter of SPU',
                'titleAr' => 'الميثاق الأخلاقي للجامعة السورية الخاصة',
                'headlineEn' => 'Ethical Charter of SPU',
                'headlineAr' => 'الميثاق الأخلاقي للجامعة السورية الخاصة',
                'summaryEn' => 'The Ethical Charter defines the values and principles that guide students, faculty, administrators, and staff.',
                'summaryAr' => 'يحدد الميثاق الأخلاقي القيم والمبادئ التي توجه الطلاب وأعضاء الهيئة التدريسية والإداريين والموظفين.',
                'heroImage' => '/images/about/hero-img.jpg',
                'badgeEn' => 'Integrity & Ethics',
                'badgeAr' => 'النزاهة والأخلاق',
                'intro' => [
                    ['valueEn' => 'The charter defines shared expectations for responsible conduct throughout the university community.', 'valueAr' => 'يحدد الميثاق التوقعات المشتركة للسلوك المسؤول في جميع مكونات مجتمع الجامعة.'],
                    ['valueEn' => 'It promotes honesty in learning and research, fairness in decisions, and respect in professional relationships.', 'valueAr' => 'ويعزز الصدق في التعلم والبحث والإنصاف في القرارات والاحترام في العلاقات المهنية.'],
                    ['valueEn' => 'These principles guide daily practice and support a safe, inclusive, and accountable academic environment.', 'valueAr' => 'توجه هذه المبادئ الممارسة اليومية وتدعم بيئة أكاديمية آمنة وشاملة وخاضعة للمساءلة.'],
                ],
                'sections' => [
                    ['titleEn' => 'Academic Integrity', 'titleAr' => 'النزاهة الأكاديمية', 'bodyEn' => 'Honesty and fairness in all academic work, including teaching, research, examinations, and grading.', 'bodyAr' => 'الصدق والإنصاف في جميع الأعمال الأكاديمية، بما في ذلك التدريس والبحث والامتحانات والتصحيح.'],
                    ['titleEn' => 'Transparency', 'titleAr' => 'الشفافية', 'bodyEn' => 'Open communication and clear disclosure of policies, procedures, and decisions affecting the university community.', 'bodyAr' => 'التواصل المفتوح والإفصاح الواضح عن السياسات والإجراءات والقرارات التي تؤثر على مجتمع الجامعة.'],
                    ['titleEn' => 'Respect & Diversity', 'titleAr' => 'الاحترام والتنوع', 'bodyEn' => 'Respect for the dignity, rights, and diversity of all individuals regardless of background, belief, or affiliation.', 'bodyAr' => 'احترام كرامة وحقوق وتنوع جميع الأفراد بغض النظر عن خلفياتهم أو معتقداتهم أو انتماءاتهم.'],
                    ['titleEn' => 'Social Responsibility', 'titleAr' => 'المسؤولية الاجتماعية', 'bodyEn' => 'Commitment to serving the community and contributing to societal development through knowledge and expertise.', 'bodyAr' => 'الالتزام بخدمة المجتمع والمساهمة في التنمية المجتمعية من خلال المعرفة والخبرة.'],
                ],
            ],
            'organizational-structure' => [
                'titleEn' => 'Organizational Structure of SPU',
                'titleAr' => 'الهيكل التنظيمي للجامعة السورية الخاصة',
                'headlineEn' => 'Organizational Structure of SPU',
                'headlineAr' => 'الهيكل التنظيمي للجامعة السورية الخاصة',
                'summaryEn' => 'SPU operates within a defined structure that supports governance, clear authority, and academic and administrative services.',
                'summaryAr' => 'تعمل الجامعة السورية الخاصة ضمن هيكل محدد يدعم الحوكمة ووضوح الصلاحيات والخدمات الأكاديمية والإدارية.',
                'heroImage' => '/images/about/hero-img.jpg',
                'badgeEn' => 'Institutional Framework',
                'badgeAr' => 'الإطار المؤسسي',
                'intro' => [
                    ['valueEn' => 'SPU organizes academic and administrative responsibilities through defined governance and operational levels.', 'valueAr' => 'تنظم الجامعة السورية الخاصة المسؤوليات الأكاديمية والإدارية من خلال مستويات حوكمة وتشغيل محددة.'],
                    ['valueEn' => 'The structure clarifies accountability while connecting university leadership, faculties, and central directorates.', 'valueAr' => 'يوضح الهيكل خطوط المساءلة ويربط قيادة الجامعة بالكليات والمديريات المركزية.'],
                ],
                'sections' => [
                    ['titleEn' => 'University Council', 'titleAr' => 'مجلس الجامعة', 'bodyEn' => 'The highest governing body responsible for strategic direction, policy approval, and institutional oversight.', 'bodyAr' => 'أعلى هيئة حاكمة مسؤولة عن التوجه الاستراتيجي واعتماد السياسات والإشراف المؤسسي.'],
                    ['titleEn' => 'University President', 'titleAr' => 'رئيس الجامعة', 'bodyEn' => 'The chief executive officer who leads the university\'s academic and administrative operations.', 'bodyAr' => 'الرئيس التنفيذي الذي يقود العمليات الأكاديمية والإدارية للجامعة.'],
                    ['titleEn' => 'Vice Presidents', 'titleAr' => 'نواب الرئيس', 'bodyEn' => 'Senior administrators overseeing academic affairs, administrative affairs, research, and student development.', 'bodyAr' => 'إداريون كبار يشرفون على الشؤون الأكاديمية والإدارية والبحث العلمي وتطوير الطلاب.'],
                    ['titleEn' => 'Faculties & Directorates', 'titleAr' => 'الكليات والمديريات', 'bodyEn' => 'Academic faculties deliver degree programs while central directorates provide administrative and support services.', 'bodyAr' => 'تقدم الكليات الأكاديمية برامج الدرجات العلمية بينما تقدم المديريات المركزية الخدمات الإدارية والداعمة.'],
                ],
            ],
            'accreditation' => [
                'titleEn' => 'Accreditation & Quality Assurance',
                'titleAr' => 'الاعتماد وضمان الجودة',
                'headlineEn' => 'Accreditation & Quality Assurance',
                'headlineAr' => 'الاعتماد وضمان الجودة',
                'summaryEn' => 'SPU holds official accreditation from the Syrian Ministry of Higher Education and Scientific Research, with licensed academic programs and ongoing quality review.',
                'summaryAr' => 'تحصل الجامعة السورية الخاصة على الاعتماد الرسمي من وزارة التعليم العالي والبحث العلمي، مع برامج أكاديمية مرخصة ومراجعة جودة مستمرة.',
                'heroImage' => '/images/about/hero-img.jpg',
                'badgeEn' => 'National Accreditation',
                'badgeAr' => 'الاعتماد الوطني',
                'intro' => [
                    ['valueEn' => 'Syrian Private University was established by Republican Decree No. 339 of 2005 and operates under the oversight of the Ministry of Higher Education and Scientific Research.', 'valueAr' => 'أُحدثت الجامعة السورية الخاصة بالمرسوم الجمهوري رقم 339 لعام 2005، وتعمل بإشراف وزارة التعليم العالي والبحث العلمي.'],
                    ['valueEn' => 'Academic programs and institutional regulations are published only after the applicable licensing and approval requirements are met.', 'valueAr' => 'تُطرح البرامج الأكاديمية والأنظمة المؤسسية بعد استيفاء متطلبات الترخيص والاعتماد المعمول بها.'],
                ],
                'stats' => [
                    ['value' => '2005', 'labelEn' => 'Year Established', 'labelAr' => 'سنة الإحداث'],
                    ['value' => '339', 'labelEn' => 'Republican Decree', 'labelAr' => 'المرسوم الجمهوري'],
                ],
                'sections' => [
                    ['titleEn' => 'Program Licensing', 'titleAr' => 'ترخيص البرامج', 'bodyEn' => 'Every academic program is licensed after review of curriculum, faculty qualifications, facilities, and learning outcomes.', 'bodyAr' => 'كل برنامج أكاديمي مرخص بعد مراجعة المنهاج ومؤهلات أعضاء الهيئة التدريسية والمرافق ومخرجات التعلم.'],
                    ['titleEn' => 'Periodic Review', 'titleAr' => 'المراجعة الدورية', 'bodyEn' => 'Programs undergo periodic review cycles to ensure continued compliance with national standards and best practices.', 'bodyAr' => 'تخضع البرامج لدورات مراجعة دورية لضمان الامتثال المستمر للمعايير الوطنية وأفضل الممارسات.'],
                    ['titleEn' => 'Faculty Qualifications', 'titleAr' => 'مؤهلات أعضاء الهيئة التدريسية', 'bodyEn' => 'Faculty appointment and promotion follow transparent criteria approved by the Ministry.', 'bodyAr' => 'يتبع تعيين وترقية أعضاء الهيئة التدريسية معايير شفافة معتمدة من الوزارة.'],
                    ['titleEn' => 'Student Assessment', 'titleAr' => 'تقييم الطلاب', 'bodyEn' => 'Assessment methods adhere to university-wide standards, examination regulations, and transparent grading policies.', 'bodyAr' => 'تلتزم طرق التقييم بمعايير الجامعة وأنظمة الامتحانات وسياسات التصحيح الشفافة.'],
                ],
            ],
            'why-spu' => [
                'titleEn' => 'Why Choose Syrian Private University?',
                'titleAr' => 'لماذا تختار الجامعة السورية الخاصة؟',
                'headlineEn' => 'Why Choose Syrian Private University?',
                'headlineAr' => 'لماذا تختار الجامعة السورية الخاصة؟',
                'summaryEn' => 'SPU offers a distinctive educational experience built on accreditation, clinical excellence, research, and student support.',
                'summaryAr' => 'تقدم الجامعة السورية الخاصة تجربة تعليمية متميزة قائمة على الاعتماد والتميز السريري والبحث العلمي ودعم الطلاب.',
                'heroImage' => '/images/about/hero-img.jpg',
                'badgeEn' => 'Choose Your Path',
                'badgeAr' => 'اختر مسارك',
                'intro' => [
                    ['valueEn' => 'SPU combines licensed academic programs with practical learning, student support, and connections to professional practice.', 'valueAr' => 'تجمع الجامعة السورية الخاصة بين البرامج الأكاديمية المرخصة والتعليم التطبيقي ودعم الطلاب والارتباط بالممارسة المهنية.'],
                ],
                'sections' => [
                    ['titleEn' => 'Accredited Programs', 'titleAr' => 'برامج معتمدة', 'bodyEn' => 'All SPU programs are licensed and periodically reviewed.', 'bodyAr' => 'جميع برامج SPU مرخصة وتخضع للمراجعة الدورية.'],
                    ['titleEn' => 'Clinical Excellence', 'titleAr' => 'التميز السريري', 'bodyEn' => 'SPU operates a university hospital and dental clinics that serve the community and train students.', 'bodyAr' => 'تدير SPU مستشفى جامعياً وعيادات سنية تخدم المجتمع وتدرب الطلاب.'],
                    ['titleEn' => 'Research & Innovation', 'titleAr' => 'البحث والابتكار', 'bodyEn' => 'Active research centers and publications support academic development.', 'bodyAr' => 'تدعم مراكز البحث والمنشورات النشطة التطوير الأكاديمي.'],
                    ['titleEn' => 'Student Support', 'titleAr' => 'دعم الطلاب', 'bodyEn' => 'Comprehensive services include advising, career development, insurance, transport, and activities.', 'bodyAr' => 'تشمل الخدمات الشاملة الإرشاد والتطوير المهني والتأمين والنقل والأنشطة.'],
                    ['titleEn' => 'Campus & Facilities', 'titleAr' => 'الحرم الجامعي والمرافق', 'bodyEn' => 'Learning spaces, laboratories, clinics, and digital services support academic and practical study.', 'bodyAr' => 'تدعم القاعات والمختبرات والعيادات والخدمات الرقمية الدراسة الأكاديمية والتطبيقية.'],
                    ['titleEn' => 'Community Engagement', 'titleAr' => 'المشاركة المجتمعية', 'bodyEn' => 'Academic and service activities connect university expertise with community needs.', 'bodyAr' => 'تربط الأنشطة الأكاديمية والخدمية خبرات الجامعة باحتياجات المجتمع.'],
                ],
            ],
            default => null,
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localizedImportedPayload(array $payload, string $locale): array
    {
        $localized = [];

        foreach ($payload as $key => $value) {
            if (str_ends_with((string) $key, 'En') || str_ends_with((string) $key, 'Ar')) {
                continue;
            }

            $localized[(string) $key] = is_array($value) ? $this->localizedImportedList($value, $locale) : $value;
        }

        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        foreach ($payload as $key => $value) {
            if (str_ends_with((string) $key, $suffix)) {
                $localized[lcfirst(substr((string) $key, 0, -2))] = $value;
            }
        }

        return $localized;
    }

    /** @param array<int|string, mixed> $items @return array<int|string, mixed> */
    private function localizedImportedList(array $items, string $locale): array
    {
        if (array_is_list($items)) {
            return array_map(fn (mixed $item): mixed => is_array($item) ? $this->localizedImportedPayload($item, $locale) : $item, $items);
        }

        return $this->localizedImportedPayload($items, $locale);
    }

    /** @return array<string, mixed> */
    private function staffDirectoryPayload(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory',
            'headline' => $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory',
            'summary' => $locale === 'ar' ? 'دليل أعضاء الهيئة الأكاديمية في الجامعة السورية الخاصة.' : 'Directory of SPU academic staff members.',
            'heroImage' => '/images/about-hero-2.webp',
            'sections' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function historySections(string $locale): array
    {
        return [
            'foundingTitle' => $locale === 'ar' ? 'رؤية التأسيس' : 'The Founding Vision',
            'quote' => $locale === 'ar'
                ? 'جامعة تأسست لتعزيز التميز الأكاديمي، والإعداد المهني، والمساهمة الفاعلة في خدمة المجتمع.'
                : 'A university founded to advance academic excellence, professional preparation, and meaningful contribution to society.',
            'body' => $locale === 'ar'
                ? [
                    'انطلقت الجامعة السورية الخاصة من التزام عميق بتطوير التعليم العالي وتعزيز الابتكار الأكاديمي في المنطقة. وقد أدرك المؤسسون الحاجة إلى مؤسسة لا تكتفي بنقل المعرفة، بل تنمي التفكير النقدي والقيادة الأخلاقية والمهارات العملية المتوافقة مع المعايير العالمية.',
                    'منذ نشأتها، صُممت الجامعة لتكون منارة للرصانة الأكاديمية، تجمع بين النظريات الأساسية والتطبيق العملي، بما يضمن إعداد خريجين قادرين على التعامل مع تحديات العالم الحديث وحلها بكفاءة.',
                ]
                : [
                    'Established with a profound commitment to educational innovation, Syrian Private University emerged from a collective vision to elevate the standards of higher education in the region. The founders recognized the critical need for an institution that not only imparted knowledge but also fostered critical thinking, ethical leadership, and practical skills aligned with global standards.',
                    'From its inception, the university was designed to be a beacon of academic rigor, integrating foundational theories with applied practice. This dual approach ensures that graduates are not merely degree holders, but competent professionals ready to engage with and solve the complex challenges of the modern world.',
                ],
            'timelineTitle' => $locale === 'ar' ? 'المسار المؤسسي' : 'Institutional Timeline',
            'timeline' => $locale === 'ar'
                ? [
                    ['year' => '2005', 'title' => 'تأسيس SPU', 'body' => 'افتتحت الجامعة أبوابها رسميا، وأسست كلياتها الأساسية، ووضعت قاعدة لمنهج أكاديمي شامل.'],
                    ['year' => '2009', 'title' => 'تخريج الدفعة الأولى', 'body' => 'شهدت الجامعة تخريج أول دفعة من طلابها بعد استكمال متطلبات البرامج الأكاديمية.'],
                    ['year' => '2012', 'title' => 'اعتماد اسم الجامعة السورية الخاصة', 'body' => 'اعتمد اسم الجامعة السورية الخاصة ضمن مسارها المؤسسي والأكاديمي.'],
                ]
                : [
                    ['year' => '2005', 'title' => 'Founding of SPU', 'body' => 'The university officially opened its doors, establishing core faculties and laying the groundwork for a comprehensive academic curriculum.'],
                    ['year' => '2009', 'title' => 'First Graduating Class', 'body' => 'The university celebrated its first graduating class after students completed their academic program requirements.'],
                    ['year' => '2012', 'title' => 'Syrian Private University Name Adopted', 'body' => 'The Syrian Private University name was adopted as part of the institution’s academic development.'],
                ],
            'narratives' => $locale === 'ar'
                ? [
                    ['title' => 'النمو الأكاديمي', 'eyebrow' => 'تطور البرامج', 'body' => 'واصلت الجامعة تطوير برامجها الطبية والهندسية والإدارية بما ينسجم مع أنظمة وزارة التعليم العالي والبحث العلمي واحتياجات الطلاب.'],
                    ['title' => 'التعليم التطبيقي', 'eyebrow' => 'تعلم مهني', 'body' => 'تجمع البرامج بين المعرفة الأكاديمية والتدريب العملي في المختبرات والعيادات والمشروعات التعليمية وفق طبيعة كل اختصاص.'],
                    ['title' => 'خدمة المجتمع', 'eyebrow' => 'مسؤولية مؤسسية', 'body' => 'ترتبط رسالة الجامعة بإعداد خريجين مؤهلين ودعم الأنشطة العلمية والخدمية التي تستجيب لاحتياجات المجتمع.'],
                ]
                : [
                    ['title' => 'Academic Growth', 'eyebrow' => 'Program Development', 'body' => 'SPU has continued developing its medical, engineering, and administrative programs in line with Ministry regulations and student needs.'],
                    ['title' => 'Applied Learning', 'eyebrow' => 'Professional Preparation', 'body' => 'Programs combine academic knowledge with practical training in laboratories, clinics, and educational projects according to each discipline.'],
                    ['title' => 'Community Service', 'eyebrow' => 'Institutional Responsibility', 'body' => 'The university’s mission connects graduate preparation with scientific and service activities responsive to community needs.'],
                ],
            'legacyTitle' => $locale === 'ar' ? 'استمرار الإرث' : 'Continuing the Legacy',
            'legacyBody' => $locale === 'ar'
                ? 'تواصل الجامعة السورية الخاصة البناء على رؤية تأسيسها من خلال تطوير البرامج الأكاديمية، ودعم الطلاب، وتعزيز التعليم التطبيقي، والمساهمة في مستقبل التعليم العالي.'
                : 'Syrian Private University continues to build on its founding vision by strengthening academic programs, supporting students, advancing applied learning, and contributing to the future of higher education.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function publishedLocalizedPayload(string $targetKey, string $locale): ?array
    {
        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);
        $localized = is_array($published['translations'][$locale] ?? null)
            ? $published['translations'][$locale]
            : null;

        return is_array($localized) ? $localized : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function listValue(array $payload, string $key): array
    {
        return array_values(array_filter(is_array($payload[$key] ?? null) ? $payload[$key] : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, string>> */
    private function localizedArray(array $items, string $locale): array
    {
        return collect($items)->map(static function (array $item) use ($locale): array {
            $localized = [];

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                if (str_ends_with((string) $key, '_ar') || str_ends_with((string) $key, '_en')) {
                    continue;
                }

                $localized[(string) $key] = (string) $value;
            }

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                $suffix = '_'.$locale;
                if (str_ends_with((string) $key, $suffix)) {
                    $localized[substr((string) $key, 0, -strlen($suffix))] = (string) $value;
                }
            }

            return $localized;
        })->values()->all();
    }

    private function aboutTranslation(AboutPage $page, string $locale): AboutPageTranslation
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', 'ar')
            ?? $page->translations->first();
    }

    /** @return array<int, array{title: string, link: string}> */
    private function buildAboutNavigationCards(string $locale, ?string $excludeTargetKey = null): array
    {
        $cards = $this->navigationCardService->getVisibleCards($locale);

        if ($excludeTargetKey !== null) {
            $cards = array_values(array_filter($cards, fn (array $card): bool => ($card['target_key'] ?? null) !== $excludeTargetKey));
        }

        return array_map(fn (array $card): array => [
            'title' => $card['title'],
            'link' => $card['link'],
        ], $cards);
    }

    private function personTranslation(Person $person, string $locale): PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->first();
    }

    private function facultyTranslation(Faculty $faculty, string $locale): FacultyTranslation
    {
        return $faculty->translations->firstWhere('locale', $locale)
            ?? $faculty->translations->firstWhere('locale', 'ar')
            ?? $faculty->translations->first();
    }

    private function facultyMemberTranslation(FacultyMember $member, string $locale): ?FacultyMemberTranslation
    {
        return $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', 'ar')
            ?? $member->translations->firstWhere('locale', 'en');
    }

    /** @return array<string, string> */
    private function staffFacultyLabels(string $locale): array
    {
        return Faculty::query()
            ->enabled()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (Faculty $faculty) use ($locale): array {
                $slug = (string) ($faculty->faculty_scope_slug ?: $faculty->public_slug ?: $faculty->slug);

                return [$slug => (string) $this->facultyTranslation($faculty, $locale)->name];
            })
            ->all();
    }

    /** @param array<int, mixed> $targetIds @return array<int, array<string, mixed>> */
    private function legacyMediaByTargetId(string $targetTable, array $targetIds): array
    {
        $targetIds = array_values(array_filter(array_map('intval', $targetIds)));
        if ($targetIds === []) {
            return [];
        }

        return MigrationLog::query()
            ->where('target_table', $targetTable)
            ->where('status', 'success')
            ->whereIn('target_id', $targetIds)
            ->orderByDesc('id')
            ->get(['target_id', 'metadata'])
            ->unique('target_id')
            ->mapWithKeys(fn (MigrationLog $log): array => [(int) $log->target_id => is_array($log->metadata) ? $log->metadata : []])
            ->all();
    }

    private function directorateTranslation(Directorate $directorate, string $locale): DirectorateTranslation
    {
        return $directorate->translations->firstWhere('locale', $locale)
            ?? $directorate->translations->firstWhere('locale', 'ar')
            ?? $directorate->translations->first();
    }

    private function partnershipTranslation(Partnership $partnership, string $locale): PartnershipTranslation
    {
        return $partnership->translations->firstWhere('locale', $locale)
            ?? $partnership->translations->firstWhere('locale', 'ar')
            ?? $partnership->translations->first();
    }
}
