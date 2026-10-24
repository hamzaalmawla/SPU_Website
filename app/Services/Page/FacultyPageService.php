<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\PersonIdentityServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Faculty\ProjectFieldBlockParserInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Contracts\Page\ProfilePageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\PersonDTO;
use App\DTOs\Content\ProfilePageDTO;
use App\DTOs\Faculty\FacultyCardDTO;
use App\DTOs\Faculty\FacultyDetailPageDTO;
use App\DTOs\Faculty\FacultyHighlightDTO;
use App\DTOs\Faculty\FacultyHubCardDTO;
use App\DTOs\Faculty\FacultyHubPageDTO;
use App\DTOs\Faculty\FacultyNavigationItemDTO;
use App\DTOs\Faculty\FacultyProjectDetailPageDTO;
use App\DTOs\Faculty\FacultySubpageCardDTO;
use App\DTOs\Faculty\FacultySubpageDTO;
use App\DTOs\Faculty\ProjectFieldBlockDTO;
use App\Enums\AcademicSemester;
use App\Enums\GraduationGrade;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Department;
use App\Models\Faculty\DepartmentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyHighlightTranslation;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyLabTranslation;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyPageTranslation;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Faculty\FacultyTranslation;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Shared\MigrationLog;
use App\Support\FacultyLogoSubtitle;
use App\Support\MediaUrlResolver;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class FacultyPageService implements FacultyPageServiceInterface
{
    private const FACULTY_SLUGS = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    /** @var array<string, string> */
    private const FACULTY_SLUG_ALIASES = [
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    /** Kinds of academic department: basic sciences (common years), academic and clinical. */
    private const DEPARTMENT_KINDS = ['basic_sciences', 'academic', 'clinical'];

    private const SUBPAGE_SLUGS = ['overview', 'departments', 'study-plan', 'study-plan-course', 'labs', 'projects', 'alumni', 'valedictorians', 'training', 'clinical-cases', 'final-exams', 'research', 'members'];

    private const ALUMNI_LIST_PER_PAGE = 12;

    private const HONOR_LIST_PER_PAGE = 6;

    private const FACULTY_LIST_PER_PAGE = 6;

    private const RESEARCH_LIST_PER_PAGE = 6;

    private const LATEST_RESEARCH_LIMIT = 3;

    private const MEMORIAL_HONOR_SOURCE_IDS = [134, 359, 390, 898, 1118];

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly FacultyStudyPlanLinkServiceInterface $studyPlanLinkService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly AboutPageServiceInterface $aboutPageService,
        private readonly ProfilePageServiceInterface $profilePageService,
        private readonly ProjectFieldBlockParserInterface $projectFieldBlockParser,
        private readonly FacultySubpageCardServiceInterface $subpageCardService,
        private readonly PersonIdentityServiceInterface $identityService,
    ) {}

    public function getHub(string $locale): FacultyHubPageDTO
    {
        return $this->facilitiesCache()->remember("public.faculties.hub.{$locale}", function () use ($locale): FacultyHubPageDTO {
            $faculties = $this->baseFacultyQuery()->get();
            $content = $this->publishedLocalizedPayload('facilities.landing', $locale) ?? $this->hubContent($locale);

            return $this->hubDto($locale, $content, $faculties);
        }, 1800);
    }

    public function buildPreviewHub(string $locale, array $content): FacultyHubPageDTO
    {
        return $this->hubDto($locale, $content, $this->baseFacultyQuery()->get());
    }

    public function buildPreviewFaculty(string $facultySlug, string $locale, array $content): ?FacultyDetailPageDTO
    {
        $faculty = $this->facultyByPublicSlug($this->canonicalFacultySlug($facultySlug));

        return $faculty instanceof Faculty ? $this->facultyDetailDto($faculty, $locale, $content) : null;
    }

    public function buildPreviewSubpage(string $facultySlug, string $subpageSlug, string $locale, array $content): ?FacultySubpageDTO
    {
        $faculty = $this->facultyByPublicSlug($this->canonicalFacultySlug($facultySlug));

        return $faculty instanceof Faculty ? $this->facultySubpageDto($faculty, $subpageSlug, $locale, $content) : null;
    }

    public function getEditablePayload(string $targetKey): array
    {
        $facultySlug = $this->facultySlugFromTarget($targetKey);
        $subpageTarget = $this->subpageFromTarget($targetKey);

        if ($targetKey !== 'facilities.landing' && $facultySlug === null && $subpageTarget === null) {
            throw new \InvalidArgumentException('Unsupported facilities target.');
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

        if ($facultySlug !== null) {
            return [
                'translations' => [
                    'ar' => $this->editableFacultyContent($facultySlug, 'ar'),
                    'en' => $this->editableFacultyContent($facultySlug, 'en'),
                ],
            ];
        }

        if ($subpageTarget !== null) {
            return [
                'translations' => [
                    'ar' => $this->editableSubpageContent($subpageTarget['faculty'], $subpageTarget['subpage'], 'ar'),
                    'en' => $this->editableSubpageContent($subpageTarget['faculty'], $subpageTarget['subpage'], 'en'),
                ],
            ];
        }

        return [
            'translations' => [
                'ar' => $this->editableHubContent('ar'),
                'en' => $this->editableHubContent('en'),
            ],
        ];
    }

    public function getFaculty(string $facultySlug, string $locale): ?FacultyDetailPageDTO
    {
        $facultySlug = $this->canonicalFacultySlug($facultySlug);

        return $this->facilitiesCache()->remember("public.faculties.{$facultySlug}.{$locale}", function () use ($facultySlug, $locale): ?FacultyDetailPageDTO {
            $faculty = $this->facultyByPublicSlug($facultySlug);

            if (! $faculty instanceof Faculty) {
                return null;
            }

            $content = $this->publishedLocalizedPayload('facilities.'.$this->publicSlug($faculty), $locale);

            return $this->facultyDetailDto($faculty, $locale, $content);
        }, 1800);
    }

    /** @param array<string, mixed>|null $cmsContent */
    private function facultyDetailDto(Faculty $faculty, string $locale, ?array $cmsContent = null): FacultyDetailPageDTO
    {
        $page = $this->pageForFaculty($faculty, 'overview') ?? $this->pageForFaculty($faculty, (string) ($faculty->pages->first()?->slug ?? 'overview'));
        $translation = $page instanceof FacultyPage ? $this->pageTranslation($page, $locale) : null;
        $facultyTranslation = $this->facultyTranslation($faculty, $locale);
        $pagePayload = $page instanceof FacultyPage && is_array($page->payload_json) ? $page->payload_json : [];
        $facultyPayload = $this->facultyPayload($faculty, $locale);
        $content = [
            'title' => (string) ($translation?->title ?? $facultyTranslation->name),
            'summary' => (string) ($translation?->summary ?? $facultyTranslation->short_description ?? ''),
            'body' => (string) ($translation?->body ?? $facultyTranslation->description ?? ''),
            'tabs' => $this->overviewTabs($translation, $facultyTranslation, $locale),
            'sections' => is_array($translation?->sections_json) ? $translation->sections_json : [],
            'dean' => is_array($pagePayload['dean'] ?? null) ? $pagePayload['dean'] : [],
            'gallery' => $this->gallery($faculty),
            'latestResearch' => $this->latestResearch($faculty, $locale, $this->selectedResearchSlugs($cmsContent ?? [])),
        ];
        $stats = $this->localizedPayloadList($pagePayload['stats'] ?? [], $locale);

        if (is_array($cmsContent)) {
            $facultyPayload = $this->cmsFacultyPayload($cmsContent, $facultyPayload, $locale);
            $content = $this->cmsFacultyContent($cmsContent, $content);
            $cmsStats = $this->arrayList($cmsContent['stats'] ?? []);

            if ($cmsStats !== []) {
                $stats = $cmsStats;
            }
        }

        if (is_array($content['dean'] ?? null) && $content['dean'] !== []) {
            $deanProfile = $this->deanProfileFor($faculty, $locale, $content['dean']);
            $content['dean']['profileUrl'] = $deanProfile?->path;
            // The dean's name and photo are the profile's, so an edit there shows here.
            if ($deanProfile instanceof ProfilePageDTO) {
                $content['dean']['name'] = $deanProfile->name;
                if (is_string($deanProfile->image) && $deanProfile->image !== '') {
                    $content['dean']['image'] = $deanProfile->image;
                }
            }
        }

        return new FacultyDetailPageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            slug: $this->publicSlug($faculty),
            faculty: $facultyPayload,
            content: $content,
            stats: $stats,
            navigation: $this->navigation($faculty, $locale, null),
            highlights: $this->highlights($faculty, $locale),
            seoTitle: $this->stringOrDefault($cmsContent['seoTitle'] ?? null, (string) ($facultyPayload['title'] ?? $facultyTranslation->catalog_title ?? $facultyTranslation->name).' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'SPU')),
            seoDescription: $this->stringOrDefault($cmsContent['seoDescription'] ?? null, (string) ($facultyPayload['summary'] ?? $translation?->summary ?? '')),
            seoImage: $this->stringOrDefault($facultyPayload['heroImage'] ?? null, '/images/uni-main-place.JPG'),
        );
    }

    public function getSubpage(string $facultySlug, string $subpageSlug, string $locale, array $filters = []): ?FacultySubpageDTO
    {
        if (! in_array($subpageSlug, self::SUBPAGE_SLUGS, true)) {
            return null;
        }

        $facultySlug = $this->canonicalFacultySlug($facultySlug);
        $filters = $this->normalizeSubpageFilters($subpageSlug, $filters);
        $filterKey = md5((string) json_encode($filters));

        return $this->facilitiesCache()->remember("public.faculties.{$facultySlug}.{$subpageSlug}.{$locale}.{$filterKey}", function () use ($facultySlug, $subpageSlug, $locale, $filters): ?FacultySubpageDTO {
            $faculty = $this->facultyByPublicSlug($facultySlug);

            if (! $faculty instanceof Faculty) {
                return null;
            }

            if ($subpageSlug === 'research' && ! $faculty->pages->contains(fn (FacultyPage $page): bool => $page->slug === 'research')) {
                return null;
            }

            if (! $this->isAvailableSubpage($faculty, $subpageSlug)) {
                return null;
            }

            $content = $this->publishedLocalizedPayload($this->targetKeyForSubpage($this->publicSlug($faculty), $subpageSlug), $locale);

            return $this->facultySubpageDto($faculty, $subpageSlug, $locale, $content, $filters);
        }, 1800);
    }

    public function getProject(string $facultySlug, string $projectSlug, string $locale): ?FacultyProjectDetailPageDTO
    {
        $facultySlug = $this->canonicalFacultySlug($facultySlug);

        return $this->facilitiesCache()->remember("public.faculties.{$facultySlug}.projects.{$projectSlug}.{$locale}", function () use ($facultySlug, $projectSlug, $locale): ?FacultyProjectDetailPageDTO {
            $faculty = $this->facultyByPublicSlug($facultySlug);

            if (! $faculty instanceof Faculty) {
                return null;
            }

            $projects = $this->projectDetailItems($faculty, $locale);
            $project = collect($projects)->firstWhere('slug', $projectSlug);

            if (! is_array($project)) {
                return null;
            }

            return $this->projectDetailDto($faculty, $project, $projects, $locale);
        }, 1800);
    }

    /** @param array<string, mixed>|null $cmsContent */
    private function facultySubpageDto(Faculty $faculty, string $subpageSlug, string $locale, ?array $cmsContent = null, array $filters = []): ?FacultySubpageDTO
    {
        $page = $this->pageForFaculty($faculty, $subpageSlug);

        if (! in_array($subpageSlug, self::SUBPAGE_SLUGS, true)) {
            return null;
        }

        $filters = $this->normalizeSubpageFilters($subpageSlug, $filters);

        $translation = $page instanceof FacultyPage ? $this->pageTranslation($page, $locale) : null;
        $pagePayload = $page instanceof FacultyPage && is_array($page->payload_json) ? $this->localizedPayload($page->payload_json, $locale) : [];
        $pageData = [
            'title' => (string) ($translation?->title ?? $this->subpageTitle($subpageSlug, $locale)),
            'summary' => (string) ($translation->summary ?? ''),
            'body' => (string) ($translation->body ?? ''),
            'heroImage' => (string) (($page instanceof FacultyPage ? $page->hero_image : null) ?: $faculty->hero_image ?: '/images/uni-main-place.JPG'),
            'sections' => is_array($translation?->sections_json) ? $translation->sections_json : [],
            'payload' => $pagePayload,
        ];
        $items = $this->subpageItems($faculty, $subpageSlug, $locale, $page);

        if (is_array($cmsContent)) {
            $pageData = $this->cmsSubpageData($cmsContent, $pageData);
            $cmsItems = $this->arrayList($cmsContent['items'] ?? []);

            if (array_key_exists('items', $cmsContent)) {
                $items = $subpageSlug === 'projects'
                    ? collect($cmsItems)->map(fn (array $item): array => [
                        ...$this->projectDetailPayload($faculty, $item, $locale),
                        'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) ($item['slug'] ?? '')),
                    ])->values()->all()
                    : $cmsItems;

                // An editor's published list is a copy of the records at the time;
                // students imported from a decision since then are added to it.
                if ($this->isStudentListSubpage($subpageSlug)) {
                    $items = [...$items, ...$this->importedStudentsMissingFrom($items, $faculty, $subpageSlug, $locale)];
                }
            }
        }

        $detail = [];

        if ($subpageSlug === 'departments') {
            $items = $this->withDepartmentHeads($this->studyPlanLinkService->enrichDepartmentItems($this->publicSlug($faculty), $locale, $items), $locale);
        }

        $filterOptions = [];
        $pagination = $this->emptyPagination(count($items));

        if (in_array($subpageSlug, ['study-plan', 'study-plan-course'], true)) {
            $pageData['payload']['plan']['pdfUrl'] = $this->studyPlanLinkService->sanitizeCourseMaterialPath(
                $pageData['payload']['plan']['pdfUrl'] ?? null,
            );
            $selection = $this->studyPlanSelection($pageData, $filters, $locale, $subpageSlug === 'study-plan-course');

            if ($selection === null) {
                return null;
            }

            $filters = $selection['filters'];
            $detail = $selection['detail'];
        }

        if ($this->isStudentListSubpage($subpageSlug)) {
            if ($subpageSlug === 'valedictorians') {
                $items = $this->withSharedHonorPhotos($items);
            }

            $filterOptions = $this->studentFilterOptions($items, $subpageSlug);
            $items = $this->filteredStudentItems($items, $subpageSlug, $filters);
            $items = $this->newestStudentItems($items, $subpageSlug);
            $items = $this->resolvedStudentImages($items);
            $perPage = $subpageSlug === 'alumni' ? self::ALUMNI_LIST_PER_PAGE : self::HONOR_LIST_PER_PAGE;
            [$items, $pagination] = $this->paginatedItems($items, $filters, $perPage);
        }

        if ($subpageSlug === 'projects') {
            $items = $this->withProjectSupervisors($faculty, $locale, $items);
            $items = $this->filteredProjectItems($items, $filters);
            [$items, $pagination] = $this->paginatedItems($items, $filters, self::FACULTY_LIST_PER_PAGE);
        }

        if ($subpageSlug === 'labs') {
            $selectedLab = collect($items)->firstWhere('slug', $filters['lab'] ?? '');

            if (is_array($selectedLab)) {
                $detail = [
                    'item' => $selectedLab,
                    'related' => collect($items)
                        ->reject(fn (array $item): bool => (string) ($item['slug'] ?? '') === (string) ($selectedLab['slug'] ?? ''))
                        ->take(3)
                        ->values()
                        ->all(),
                ];
            }

            [$items, $pagination] = $this->paginatedItems($items, $filters, self::FACULTY_LIST_PER_PAGE);
        }

        if ($subpageSlug === 'research') {
            [$items, $pagination] = $this->paginatedResearchItems($items, $filters);
        }

        $latestResearch = $subpageSlug === 'overview'
            ? $this->latestResearch($faculty, $locale, $this->selectedResearchSlugs($this->publishedLocalizedPayload('facilities.'.$this->publicSlug($faculty), $locale) ?? []))
            : [];
        $deanProfile = $subpageSlug === 'overview' ? $this->deanProfile($faculty, $locale, $pageData) : null;

        return new FacultySubpageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            facultySlug: $this->publicSlug($faculty),
            subpageSlug: $subpageSlug,
            faculty: $this->facultyPayload($faculty, $locale),
            page: $pageData,
            items: $items,
            filters: $filters,
            filterOptions: $filterOptions,
            pagination: $pagination,
            detail: $detail,
            latestResearch: $latestResearch,
            deanProfile: $deanProfile,
            navigation: $this->navigation($faculty, $locale, $subpageSlug),
            highlights: $this->highlights($faculty, $locale),
            seoTitle: $this->stringOrDefault($cmsContent['seoTitle'] ?? null, (string) ($pageData['title'] ?? $this->subpageTitle($subpageSlug, $locale)).' | '.(string) $this->facultyTranslation($faculty, $locale)->name),
            seoDescription: $this->stringOrDefault($cmsContent['seoDescription'] ?? null, (string) ($pageData['summary'] ?? '')),
            seoImage: $this->stringOrDefault($cmsContent['seoImage'] ?? null, $this->stringOrDefault($pageData['heroImage'] ?? null, '/images/uni-main-place.JPG')),
        );
    }

    public function facultySlugPattern(): string
    {
        return implode('|', [...self::FACULTY_SLUGS, ...array_keys(self::FACULTY_SLUG_ALIASES)]);
    }

    public function canonicalFacultySlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));

        return self::FACULTY_SLUG_ALIASES[$normalized] ?? $normalized;
    }

    public function resolveLegacyProjectUrl(string $projectId, string $locale): ?string
    {
        $project = FacultyStudentProject::query()->enabled()->where('slug', $projectId)->with('faculty')->first();

        if ($project instanceof FacultyStudentProject
            && $project->faculty instanceof Faculty
            && $project->faculty->is_enabled) {
            return '/'.$locale.'/faculties/'.$this->publicSlug($project->faculty).'/projects/'.rawurlencode($projectId);
        }

        return null;
    }

    public function subpageSlugPattern(): string
    {
        return implode('|', self::SUBPAGE_SLUGS);
    }

    /**
     * @param  Collection<int, Faculty>  $faculties
     * @param  array<string, mixed>  $content
     */
    private function hubDto(string $locale, array $content, Collection $faculties): FacultyHubPageDTO
    {
        $content['hero'] = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $content['hero']['campusMapUrl'] = $this->hubCampusMapUrl($content['hero']['campusMapUrl'] ?? null);

        return new FacultyHubPageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            content: $content,
            faculties: $this->hubCards($locale, $content, $faculties),
            seoTitle: (string) ($content['hero']['title'] ?? ($locale === 'ar' ? 'الكليات | الجامعة السورية الخاصة' : 'Faculties | Syrian Private University')),
            seoDescription: (string) ($content['hero']['summary'] ?? ''),
            seoImage: (string) ($content['hero']['image'] ?? '/images/campus-feature-01.webp'),
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  Collection<int, Faculty>  $faculties
     * @return Collection<int, FacultyHubCardDTO>
     */
    private function hubCards(string $locale, array $content, Collection $faculties): Collection
    {
        $facultyLinks = $this->arrayList($content['facultyLinks'] ?? []);

        if ($facultyLinks === []) {
            return $faculties
                ->map(fn (Faculty $faculty): FacultyHubCardDTO => $this->hubCardDto($faculty, $locale))
                ->values();
        }

        $facultiesBySlug = collect();

        foreach ($faculties as $faculty) {
            $facultiesBySlug->put($this->publicSlug($faculty), $faculty);
            $facultiesBySlug->put($this->canonicalFacultySlug($this->publicSlug($faculty)), $faculty);
        }

        return collect($facultyLinks)
            ->map(function (array $link) use ($facultiesBySlug, $locale): FacultyHubCardDTO {
                $url = (string) ($link['url'] ?? '#');
                $slug = $this->facultySlugFromUrl($url);
                $faculty = $slug !== '' ? $facultiesBySlug->get($slug) : null;
                $fallback = $faculty instanceof Faculty ? $this->hubCardDto($faculty, $locale) : null;

                return new FacultyHubCardDTO(
                    slug: $fallback?->slug ?? $slug,
                    title: (string) ($link['title'] ?? $fallback?->title ?? ''),
                    summary: (string) ($link['summary'] ?? $fallback?->summary ?? ''),
                    url: $url,
                    heroImage: $fallback?->heroImage,
                    logoImage: $fallback?->logoImage,
                    accentColor: isset($link['accentColor']) && is_string($link['accentColor'])
                        ? $link['accentColor']
                        : $fallback?->accentColor,
                );
            })
            ->values();
    }

    private function hubCardDto(Faculty $faculty, string $locale): FacultyHubCardDTO
    {
        $card = $this->cardDto($faculty, $locale);

        return new FacultyHubCardDTO(
            slug: $card->slug,
            title: $card->title,
            summary: $card->summary,
            url: $card->url,
            heroImage: $card->heroImage,
            logoImage: $card->logoImage,
            accentColor: $card->accentColor,
        );
    }

    private function facultySlugFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return '';
        }

        $slug = basename(rtrim($path, '/'));

        return $this->canonicalFacultySlug(rawurldecode($slug));
    }

    /**
     * The hero's campus-map button link: an internal path or in-page anchor
     * the editor picked, else the faculty list below, as in the reference.
     */
    private function hubCampusMapUrl(mixed $url): string
    {
        $url = is_string($url) ? trim($url) : '';

        $isInternal = (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\'))
            || preg_match('/^#[A-Za-z][\w-]*$/', $url) === 1;

        return $isInternal ? $url : '#faculties-overview';
    }

    /** @return array<string, mixed> */
    private function hubContent(string $locale): array
    {
        return [
            'hero' => [
                'title' => $locale === 'ar' ? 'المرافق الأكاديمية' : 'Academic Facilities',
                'summary' => $locale === 'ar'
                    ? ''
                    : '',
                'image' => '/images/campus-feature-01.webp',
                'applyLabel' => $locale === 'ar' ? 'قدم الآن' : 'Apply Now',
                'applyUrl' => "/{$locale}/admissions/how-to-apply",
                'campusMapLabel' => $locale === 'ar' ? 'استكشف خريطة الحرم' : 'Explore Campus Map',
            ],
            'facts' => [
                ['value' => '24', 'label' => $locale === 'ar' ? 'إجمالي البرامج' : 'Total Programs'],
                ['value' => '7', 'label' => $locale === 'ar' ? 'الأقسام الأكاديمية' : 'Academic Departments'],
                ['value' => '120', 'label' => $locale === 'ar' ? 'المختبرات' : 'Laboratories'],
                ['value' => '5k+', 'label' => $locale === 'ar' ? 'الطلاب المسجلون' : 'Enrolled Students'],
            ],
            'model' => [
                'title' => $locale === 'ar' ? 'نموذج أكاديمي مبني حول الممارسة والبحث' : 'An Academic Model Built Around Practice and Research',
                'cards' => [
                    ['title' => $locale === 'ar' ? 'التعلم السريري' : 'Clinical Learning', 'summary' => $locale === 'ar' ? 'تدريب عملي مدعوم ببيئات أكاديمية وسريرية ومهنية حقيقية.' : 'Hands-on training supported by real academic, clinical, and professional environments.', 'featured' => true],
                    ['title' => $locale === 'ar' ? 'التعليم التطبيقي' : 'Applied Education', 'summary' => $locale === 'ar' ? 'برامج مصممة لربط المعرفة النظرية بالمهارات العملية وحل المشكلات الواقعية.' : 'Programs designed to connect theoretical knowledge with practical skills and real-world problem solving.', 'featured' => false],
                    ['title' => $locale === 'ar' ? 'التدريس القائم على البحث' : 'Research-Led Teaching', 'summary' => $locale === 'ar' ? 'تعلم يتشكل من خلال البحث الأكاديمي والابتكار والمعرفة القائمة على الأدلة والمخرجات البحثية.' : 'Learning shaped by academic inquiry, innovation, evidence-based knowledge, and research output.', 'featured' => false],
                    ['title' => $locale === 'ar' ? 'الإعداد المهني' : 'Professional Preparation', 'summary' => $locale === 'ar' ? 'مسارات أكاديمية تعد الطلاب للمهن المستقبلية والتدريب والدراسات المتقدمة والممارسة المهنية.' : 'Academic pathways that prepare students for future careers, internships, advanced study, and professional practice.', 'featured' => false],
                    ['title' => $locale === 'ar' ? 'بيئات تعلم حديثة' : 'Modern Learning Environments', 'summary' => $locale === 'ar' ? 'فصول ومختبرات وعيادات وورش ومساحات رقمية تدعم التعلم النشط.' : 'Classrooms, laboratories, clinics, workshops, and digital spaces that support active learning.', 'featured' => false],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function editableHubContent(string $locale): array
    {
        $content = $this->hubContent($locale);
        $content['facultyLinks'] = $this->baseFacultyQuery()
            ->get()
            ->map(fn (Faculty $faculty): array => $this->facultyLinkPayload($faculty, $locale))
            ->values()
            ->all();

        return $content;
    }

    /** @return array<string, mixed> */
    private function facultyLinkPayload(Faculty $faculty, string $locale): array
    {
        $translation = $this->facultyTranslation($faculty, $locale);
        $slug = $this->publicSlug($faculty);

        return [
            'title' => (string) ($translation->catalog_title ?? $translation->name),
            'summary' => (string) ($translation->short_description ?? ''),
            'url' => $this->url($locale, "/faculties/{$slug}"),
            'accentColor' => $faculty->accent_color,
        ];
    }

    /** @return array<string, mixed> */
    private function editableFacultyContent(string $facultySlug, string $locale): array
    {
        $faculty = $this->facultyByPublicSlug($facultySlug);

        if (! $faculty instanceof Faculty) {
            throw new \InvalidArgumentException('Unknown facilities faculty target.');
        }

        $page = $this->pageForFaculty($faculty, 'overview') ?? $this->pageForFaculty($faculty, (string) ($faculty->pages->first()?->slug ?? 'overview'));
        $translation = $page instanceof FacultyPage ? $this->pageTranslation($page, $locale) : null;
        $facultyTranslation = $this->facultyTranslation($faculty, $locale);
        $pagePayload = $page instanceof FacultyPage && is_array($page->payload_json) ? $page->payload_json : [];

        return [
            'title' => (string) ($translation?->title ?? $facultyTranslation->name),
            'summary' => (string) ($translation?->summary ?? $facultyTranslation->short_description ?? ''),
            'body' => (string) ($translation?->body ?? $facultyTranslation->description ?? ''),
            'faculty' => [
                'name' => (string) $facultyTranslation->name,
                'title' => (string) ($facultyTranslation->catalog_title ?? $facultyTranslation->name),
                'summary' => (string) ($facultyTranslation->short_description ?? ''),
                'description' => (string) ($facultyTranslation->description ?? ''),
                'yearsLabel' => (string) ($facultyTranslation->years_label ?? ''),
                'accentColor' => (string) ($faculty->accent_color ?? ''),
                'heroImage' => (string) ($faculty->hero_image ?? ''),
                'logoImage' => (string) ($faculty->logo_image ?? ''),
            ],
            'tabs' => $this->overviewTabs($translation, $facultyTranslation, $locale),
            'dean' => $this->localizedDeanPayload(is_array($pagePayload['dean'] ?? null) ? $pagePayload['dean'] : [], $locale),
            'gallery' => collect($this->gallery($faculty))->map(fn (string $image): array => ['image' => $image])->values()->all(),
            'stats' => $this->localizedPayloadList($pagePayload['stats'] ?? [], $locale),
            // Research is picked by reference; prefilling the old manual list here
            // froze the automatic cards into copies on an editor's first save.
            'selectedResearchSlugs' => [],
            'latestResearch' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function editableSubpageContent(string $facultySlug, string $subpageSlug, string $locale): array
    {
        $faculty = $this->facultyByPublicSlug($facultySlug);

        if (! $faculty instanceof Faculty) {
            throw new \InvalidArgumentException('Unknown facilities faculty target.');
        }

        $page = $this->pageForFaculty($faculty, $subpageSlug);
        $translation = $page instanceof FacultyPage ? $this->pageTranslation($page, $locale) : null;
        $payload = $page instanceof FacultyPage && is_array($page->payload_json) ? $this->localizedPayload($page->payload_json, $locale) : [];

        $content = [
            'title' => (string) ($translation?->title ?? $this->subpageTitle($subpageSlug, $locale)),
            'summary' => (string) ($translation->summary ?? ''),
            'body' => (string) ($translation->body ?? ''),
            'heroImage' => (string) (($page instanceof FacultyPage ? $page->hero_image : null) ?: $faculty->hero_image ?: '/images/uni-main-place.JPG'),
            'sections' => is_array($translation?->sections_json) ? $translation->sections_json : [],
            'payload' => $payload,
            'items' => $this->subpageItems($faculty, $subpageSlug, $locale, $page),
        ];

        if ($subpageSlug === 'research') {
            $facultyName = (string) $this->facultyTranslation($faculty, $locale)->name;
            $content['eyebrow'] = $locale === 'ar' ? 'أبحاث الكلية' : 'Faculty Research';
            $content['emptyTitle'] = $locale === 'ar' ? 'لا توجد منشورات حالياً' : 'No publications available';
            $content['emptySummary'] = $locale === 'ar'
                ? 'ستظهر أحدث المنشورات العلمية الخاصة بالكلية هنا عند نشرها.'
                : 'The faculty latest scholarly publications will appear here when published.';
            $content['seoTitle'] = ($locale === 'ar' ? 'أبحاث ' : '').$facultyName.($locale === 'ar' ? ' | الجامعة السورية الخاصة' : ' Research | SPU');
            $content['seoDescription'] = (string) $content['summary'];
            $content['seoImage'] = (string) (($page instanceof FacultyPage ? $page->hero_image : null) ?: $faculty->hero_image ?: '/images/uni-main-place.JPG');
        }

        return $content;
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $modelPage @return array<string, mixed> */
    private function cmsSubpageData(array $content, array $modelPage): array
    {
        $payload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $sections = $this->arrayList($content['sections'] ?? []);

        if ($this->arrayList($content['stats'] ?? []) !== []) {
            $payload['stats'] = $this->arrayList($content['stats'] ?? []);
        }

        if (is_array($content['dean'] ?? null) && $content['dean'] !== []) {
            $payload['dean'] = $content['dean'];
        }

        $page = [
            ...$modelPage,
            'title' => $this->stringOrDefault($content['title'] ?? null, (string) ($modelPage['title'] ?? '')),
            'summary' => $this->stringOrDefault($content['summary'] ?? null, (string) ($modelPage['summary'] ?? '')),
            'body' => $this->stringOrDefault($content['body'] ?? null, (string) ($modelPage['body'] ?? '')),
            'heroImage' => $this->stringOrDefault($content['heroImage'] ?? null, (string) ($modelPage['heroImage'] ?? '/images/uni-main-place.JPG')),
            'sections' => $sections !== [] ? $sections : ($modelPage['sections'] ?? []),
            'payload' => $payload !== [] ? $payload : ($modelPage['payload'] ?? []),
        ];

        foreach (['eyebrow', 'emptyTitle', 'emptySummary'] as $field) {
            if (array_key_exists($field, $content)) {
                $page[$field] = $content[$field];
            }
        }

        return $page;
    }

    /** @param array<string, mixed> $dean @return array<string, mixed> */
    private function localizedDeanPayload(array $dean, string $locale): array
    {
        $suffix = ucfirst($locale);

        return [
            'image' => (string) ($dean['image'] ?? ''),
            'name' => (string) ($dean['name'.$suffix] ?? $dean['name'] ?? ''),
            'role' => (string) ($dean['role'.$suffix] ?? $dean['role'] ?? ''),
            'message' => (string) ($dean['message'.$suffix] ?? $dean['message'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $modelFaculty @return array<string, mixed> */
    private function cmsFacultyPayload(array $content, array $modelFaculty, string $locale): array
    {
        $faculty = is_array($content['faculty'] ?? null) ? $content['faculty'] : [];
        $name = $this->stringOrDefault($faculty['name'] ?? null, $this->stringOrDefault($content['title'] ?? null, (string) ($modelFaculty['name'] ?? '')));
        $nameEn = $locale === 'en' ? $name : (string) ($modelFaculty['nameEn'] ?? $name);

        return [
            ...$modelFaculty,
            'name' => $name,
            'nameAr' => $locale === 'ar' ? $name : (string) ($modelFaculty['nameAr'] ?? $name),
            'nameEn' => $nameEn,
            'title' => $this->stringOrDefault($faculty['title'] ?? null, $this->stringOrDefault($content['title'] ?? null, (string) ($modelFaculty['title'] ?? $name))),
            'summary' => $this->stringOrDefault($faculty['summary'] ?? null, $this->stringOrDefault($content['summary'] ?? null, (string) ($modelFaculty['summary'] ?? ''))),
            'description' => $this->stringOrDefault($faculty['description'] ?? null, $this->stringOrDefault($content['body'] ?? null, (string) ($modelFaculty['description'] ?? ''))),
            'yearsLabel' => $this->stringOrDefault($faculty['yearsLabel'] ?? null, (string) ($modelFaculty['yearsLabel'] ?? '')),
            'accentColor' => $this->stringOrDefault($faculty['accentColor'] ?? null, (string) ($modelFaculty['accentColor'] ?? '#202759')),
            'heroImage' => $this->stringOrDefault($faculty['heroImage'] ?? null, (string) ($modelFaculty['heroImage'] ?? '/images/uni-main-place.JPG')),
            'logoImage' => $this->stringOrDefault($faculty['logoImage'] ?? null, (string) ($modelFaculty['logoImage'] ?? '/images/logo-spu.png')),
            // A saved value wins even when empty: clearing the field hides the line.
            'logoSubtitle' => array_key_exists('logoSubtitle', $faculty)
                ? trim((string) ($faculty['logoSubtitle'] ?? ''))
                : FacultyLogoSubtitle::fromEnglishName($nameEn),
        ];
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $modelContent @return array<string, mixed> */
    private function cmsFacultyContent(array $content, array $modelContent): array
    {
        $tabs = $this->arrayList($content['tabs'] ?? []);
        $dean = is_array($content['dean'] ?? null) ? $content['dean'] : [];
        $gallery = $this->imageList($content['gallery'] ?? []);

        return [
            ...$modelContent,
            'title' => $this->stringOrDefault($content['title'] ?? null, (string) ($modelContent['title'] ?? '')),
            'summary' => $this->stringOrDefault($content['summary'] ?? null, (string) ($modelContent['summary'] ?? '')),
            'body' => $this->stringOrDefault($content['body'] ?? null, (string) ($modelContent['body'] ?? '')),
            'tabs' => $tabs !== [] ? $tabs : ($modelContent['tabs'] ?? []),
            'dean' => $dean !== [] ? $dean : ($modelContent['dean'] ?? []),
            'gallery' => $gallery !== [] ? $gallery : ($modelContent['gallery'] ?? []),
        ];
    }

    private function facultySlugFromTarget(string $targetKey): ?string
    {
        if (! str_starts_with($targetKey, 'facilities.')) {
            return null;
        }

        $suffix = substr($targetKey, strlen('facilities.'));

        if ($suffix === 'landing' || str_contains($suffix, '.')) {
            return null;
        }

        return in_array($suffix, self::FACULTY_SLUGS, true) ? $suffix : null;
    }

    /** @return array{faculty: string, subpage: string}|null */
    private function subpageFromTarget(string $targetKey): ?array
    {
        if (! str_starts_with($targetKey, 'facilities.')) {
            return null;
        }

        $parts = explode('.', $targetKey);

        if (count($parts) !== 3 || ! in_array($parts[1], self::FACULTY_SLUGS, true)) {
            return null;
        }

        $subpage = $parts[2] === 'study_plan' ? 'study-plan' : $parts[2];

        return in_array($subpage, self::SUBPAGE_SLUGS, true) ? ['faculty' => $parts[1], 'subpage' => $subpage] : null;
    }

    private function targetKeyForSubpage(string $facultySlug, string $subpageSlug): string
    {
        return 'facilities.'.$facultySlug.'.'.(in_array($subpageSlug, ['study-plan', 'study-plan-course'], true) ? 'study_plan' : $subpageSlug);
    }

    /** @return array<int, array<string, mixed>> */
    private function arrayList(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @return array<int, string> */
    private function imageList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn (mixed $item): ?string => is_array($item) ? ($item['image'] ?? null) : $item)
            ->filter(fn (mixed $image): bool => is_string($image) && trim($image) !== '')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function stringList(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $default;
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

    private function baseFacultyQuery(): Builder
    {
        return Faculty::query()
            ->enabled()
            ->whereIn('public_slug', self::FACULTY_SLUGS)
            ->with([
                'translations',
                'pages' => fn ($query) => $query->enabled()->select(['id', 'faculty_id', 'slug', 'kind', 'hero_image', 'sort_order', 'is_enabled'])->with('translations'),
                'highlights' => fn ($query) => $query->enabled()->with('translations'),
                'departments' => fn ($query) => $query->enabled()->with('translations'),
                'labs' => fn ($query) => $query->enabled()->with('translations'),
                'studentProjects' => fn ($query) => $query->enabled()->with('translations'),
                'alumni' => fn ($query) => $query->enabled()->with(['translations', 'department.translations', 'photoMedia'])->orderByDesc('graduation_year')->orderByDesc('id'),
                'honorStudents' => fn ($query) => $query->enabled()->with(['translations', 'department.translations', 'photoMedia']),
            ])
            ->orderBy('sort_order');
    }

    private function facultyByPublicSlug(string $slug): ?Faculty
    {
        return $this->baseFacultyQuery()->where('public_slug', $this->canonicalFacultySlug($slug))->first();
    }

    private function pageForFaculty(Faculty $faculty, string $slug): ?FacultyPage
    {
        return FacultyPage::query()
            ->enabled()
            ->where('faculty_id', $faculty->getKey())
            ->where('slug', $slug)
            ->with('translations')
            ->first();
    }

    private function cardDto(Faculty $faculty, string $locale): FacultyCardDTO
    {
        $translation = $this->facultyTranslation($faculty, $locale);
        $slug = $this->publicSlug($faculty);

        return new FacultyCardDTO(
            id: (int) $faculty->getKey(),
            slug: $slug,
            name: (string) $translation->name,
            title: (string) ($translation->catalog_title ?? $translation->name),
            summary: (string) ($translation->short_description ?? ''),
            url: $this->url($locale, "/faculties/{$slug}"),
            heroImage: $faculty->hero_image,
            logoImage: $faculty->logo_image,
            accentColor: $faculty->accent_color,
            yearsLabel: $translation->years_label,
        );
    }

    /** @return array<string, mixed> */
    private function facultyPayload(Faculty $faculty, string $locale): array
    {
        $translation = $this->facultyTranslation($faculty, $locale);
        $arabicTranslation = $this->facultyTranslation($faculty, 'ar');
        $englishTranslation = $this->facultyTranslation($faculty, 'en');
        $slug = $this->publicSlug($faculty);

        return [
            'id' => (int) $faculty->getKey(),
            'slug' => $slug,
            'name' => (string) $translation->name,
            'nameAr' => (string) $arabicTranslation->name,
            'nameEn' => (string) $englishTranslation->name,
            'title' => (string) ($translation->catalog_title ?? $translation->name),
            'summary' => (string) ($translation->short_description ?? ''),
            'description' => (string) ($translation->description ?? ''),
            'yearsLabel' => $translation->years_label,
            'accentColor' => $faculty->accent_color,
            'heroImage' => $faculty->hero_image,
            'logoImage' => $faculty->logo_image,
            'logoSubtitle' => FacultyLogoSubtitle::fromEnglishName((string) $englishTranslation->name),
            'url' => $this->url($locale, "/faculties/{$slug}"),
        ];
    }

    /**
     * The faculty's subpage cards, shared by every page that lists them (the
     * main page's highlights and the overview's pathways), so all of them show
     * the admin's list: its cards, its order and its title overrides. Without
     * any cards the faculty falls back to its enabled standard subpages.
     *
     * @return Collection<int, FacultyNavigationItemDTO>
     */
    private function navigation(Faculty $faculty, string $locale, ?string $active): Collection
    {
        $slug = $this->publicSlug($faculty);
        $labels = [
            'overview' => $locale === 'ar' ? 'لمحة عامة' : 'Overview',
            'departments' => $locale === 'ar' ? 'الأقسام' : 'Departments',
            'study-plan' => $locale === 'ar' ? 'الخطة الدراسية' : 'Study Plan',
            'labs' => $locale === 'ar' ? 'المخابر' : 'Laboratories',
            'projects' => $locale === 'ar' ? 'المشاريع' : 'Projects',
            'alumni' => $locale === 'ar' ? 'الخريجون' : 'Alumni',
            'valedictorians' => $locale === 'ar' ? 'قائمة الشرف' : 'Honor List',
            'training' => $locale === 'ar' ? 'التدريب' : 'Training',
            'clinical-cases' => $locale === 'ar' ? 'الحالات السريرية' : 'Clinical Cases',
            'final-exams' => $locale === 'ar' ? 'برنامج الامتحان النهائي' : 'Final Exam Schedule',
            'research' => $locale === 'ar' ? 'البحث العلمي' : 'Research',
            'members' => $locale === 'ar' ? 'أعضاء الهيئة الأكاديمية' : 'Faculty Members',
        ];
        $pages = $faculty->pages->keyBy('slug');
        $cards = $this->subpageCardService->getVisibleCards($slug);

        if ($cards->isNotEmpty()) {
            $entries = $cards->map(fn (FacultySubpageCardDTO $card): array => [
                'slug' => $card->subpageSlug,
                'title' => $locale === 'ar' ? $card->titleOverrideAr : $card->titleOverrideEn,
            ]);
        } elseif ($this->subpageCardService->hasAnyCards($slug)) {
            return collect();
        } else {
            $entries = $faculty->pages
                ->filter(fn (FacultyPage $page): bool => $this->facultyMayUseSubpage($slug, (string) $page->slug))
                ->sortBy('sort_order')
                ->map(fn (FacultyPage $page): array => ['slug' => (string) $page->slug, 'title' => null]);
        }

        // Only subpages that are enabled and have a public route: a card for
        // anything else (the course-lessons view, a custom page) would 404.
        return $entries
            ->filter(fn (array $entry): bool => array_key_exists($entry['slug'], $labels) && $pages->has($entry['slug']))
            ->unique('slug')
            ->map(fn (array $entry): FacultyNavigationItemDTO => new FacultyNavigationItemDTO(
                slug: $entry['slug'],
                label: is_string($entry['title']) && trim($entry['title']) !== '' ? trim($entry['title']) : $labels[$entry['slug']],
                url: $this->url($locale, "/faculties/{$slug}/{$entry['slug']}"),
                isActive: $active === $entry['slug'],
            ))
            ->values();
    }

    /** @return Collection<int, FacultyHighlightDTO> */
    private function highlights(Faculty $faculty, string $locale): Collection
    {
        return $faculty->highlights->map(function (FacultyHighlight $highlight) use ($locale): FacultyHighlightDTO {
            $translation = $this->highlightTranslation($highlight, $locale);

            return new FacultyHighlightDTO(
                key: (string) $highlight->key,
                title: (string) $translation->title,
                value: $highlight->value,
                summary: $translation->summary,
                icon: $highlight->icon,
                url: $highlight->url,
            );
        })->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function subpageItems(Faculty $faculty, string $subpageSlug, string $locale, ?FacultyPage $page): array
    {
        return match ($subpageSlug) {
            'departments' => $this->departmentItems($faculty, $locale, $page),
            'labs' => $this->labItems($faculty, $locale),
            'projects' => $this->projectItems($faculty, $locale),
            'alumni' => $this->alumniItems($faculty, $locale),
            'valedictorians' => $this->honorItems($faculty, $locale),
            'training' => $this->localizedPayloadList(($page instanceof FacultyPage && is_array($page->payload_json) ? ($page->payload_json['items'] ?? []) : []), $locale),
            'research' => $this->researchPageService->facultyPublications($this->publicSlug($faculty), $locale)->data['items'] ?? [],
            'members' => $this->memberItems($faculty, $locale),
            'study-plan', 'study-plan-course' => [],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function memberItems(Faculty $faculty, string $locale): array
    {
        $people = Person::query()
            ->public()
            ->whereHas('appointments', fn ($q) => $q->enabled()->where('type', 'faculty_member')->where('faculty_id', $faculty->getKey()))
            ->with([
                'translations',
                'appointments' => fn ($q) => $q->enabled()->where('type', 'faculty_member')->where('faculty_id', $faculty->getKey())->with(['translations', 'department.translations']),
                'photoMedia',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $facultyMembers = FacultyMember::query()
            ->public()
            ->where('faculty_id', $faculty->getKey())
            ->with(['translations', 'department.translations', 'photoMedia'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // A leader with a faculty profile is that profile's card: same name,
        // photo and address, with the role as the position.
        $identities = $this->identityService->publicIdentities(array_map('intval', $people->modelKeys()), $locale);

        $personItems = $people
            ->map(function (Person $person) use ($locale, $identities): ?array {
                $identity = $identities[(int) $person->getKey()] ?? null;
                $slug = $identity !== null ? $identity->slug : (string) $person->slug;
                if (preg_match('/^[A-Za-z0-9-]+$/', $slug) !== 1) {
                    return null;
                }

                $translation = $person->translations->firstWhere('locale', $locale)
                    ?? $person->translations->firstWhere('locale', 'ar')
                    ?? $person->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

                $apt = $person->appointments->first();
                $role = $identity?->position ?: $identity?->title ?: $translation->position ?: $translation->title ?: '';
                if ($apt !== null) {
                    $aptTrans = $apt->translations->firstWhere('locale', $locale)
                        ?? $apt->translations->firstWhere('locale', 'ar')
                        ?? $apt->translations->firstWhere('locale', 'en');
                    if ($aptTrans?->role_override) {
                        $role = $aptTrans->role_override;
                    }
                }

                $department = null;
                if ($apt?->department instanceof Department) {
                    $department = $this->departmentTranslation($apt->department, $locale)->name ?? null;
                }

                $photo = $person->photoMedia;
                $image = $identity?->image ?? ($photo instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($photo->webp_path, $photo->path, $photo->disk)
                    : (is_string($person->image) && $person->image !== '' ? $person->image : MediaUrlResolver::resolveLegacy($person->legacy_photo_path)));

                return [
                    'slug' => $slug,
                    'name' => $identity !== null ? $identity->name : (string) $translation->name,
                    'position' => $role,
                    'department' => $department,
                    'image' => $image,
                    'profileUrl' => $this->url($locale, '/about/profile/'.$slug),
                    'sort_order' => (int) $person->sort_order,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null);

        // A profile already listed above as a leader is one card, not two:
        // unique('slug') below keeps the leader's card, with the role.
        $facultyMemberItems = $facultyMembers
            ->map(function (FacultyMember $member) use ($locale): ?array {
                $slug = (string) $member->slug;
                if (preg_match('/^[A-Za-z0-9-]+$/', $slug) !== 1) {
                    return null;
                }

                $translation = $member->translations->firstWhere('locale', $locale)
                    ?? $member->translations->firstWhere('locale', 'ar')
                    ?? $member->translations->firstWhere('locale', 'en');

                if ($translation === null) {
                    return null;
                }

                $department = null;
                if ($member->department instanceof Department) {
                    $department = $this->departmentTranslation($member->department, $locale)->name ?? null;
                }

                $image = $member->photoMedia instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
                    : MediaUrlResolver::resolveLegacy($member->legacy_photo_path);

                return [
                    'slug' => $slug,
                    'name' => (string) $translation->full_name,
                    'position' => $translation->position ?: $translation->title ?: '',
                    'department' => $department,
                    'image' => $image,
                    'profileUrl' => $this->url($locale, '/about/profile/'.$slug),
                    'sort_order' => (int) $member->sort_order,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null);

        return collect($personItems->values()->all())
            ->merge($facultyMemberItems->values()->all())
            ->unique('slug')
            ->sortBy('sort_order')
            ->values()
            ->map(fn (array $item): array => [
                'slug' => $item['slug'],
                'name' => $item['name'],
                'position' => $item['position'],
                'department' => $item['department'],
                'image' => $item['image'],
                'profileUrl' => $item['profileUrl'],
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function departmentItems(Faculty $faculty, string $locale, ?FacultyPage $page): array
    {
        $departmentPayloads = collect($page instanceof FacultyPage && is_array($page->payload_json) ? ($page->payload_json['departments'] ?? []) : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['slug']))
            ->keyBy('slug');

        return $faculty->departments->map(function (Department $department) use ($locale, $departmentPayloads): array {
            $translation = $this->departmentTranslation($department, $locale);
            $payload = $departmentPayloads->get((string) $department->slug, []);
            $localeSuffix = ucfirst($locale);
            $explicitDepartmentId = is_array($payload) ? trim((string) ($payload['studyPlanDepartmentId'] ?? '')) : '';

            return [
                'slug' => (string) $department->slug,
                'kind' => (string) $department->kind,
                'code' => is_array($payload) ? ($payload['code'] ?? null) : null,
                'title' => (string) $translation->name,
                'summary' => (string) ($translation->description ?? ''),
                'degrees' => is_array($payload) ? ($payload['degrees'.$localeSuffix] ?? null) : null,
                'tags' => is_array($payload) && is_array($payload['tags'] ?? null) ? $payload['tags'] : [],
                'studyPlanDepartmentId' => $explicitDepartmentId,
            ];
        })->values()->all();
    }

    /**
     * Normalizes each department's kind and adds its head (name, photo and profile link) from the
     * public faculty profile. A head who is not public is left out.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withDepartmentHeads(array $items, string $locale): array
    {
        $memberIds = collect($items)
            ->map(static fn (array $item): int => is_numeric($item['headMemberId'] ?? null) ? (int) $item['headMemberId'] : 0)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $heads = $this->publicMemberCards($locale, $memberIds);

        return array_map(static function (array $item) use ($heads): array {
            $kind = (string) ($item['kind'] ?? '');
            $headId = is_numeric($item['headMemberId'] ?? null) ? (int) $item['headMemberId'] : 0;

            return [
                ...$item,
                'kind' => in_array($kind, self::DEPARTMENT_KINDS, true) ? $kind : 'academic',
                'head' => $heads[$headId] ?? null,
            ];
        }, $items);
    }

    /** @return array<int, array<string, mixed>> */
    private function labItems(Faculty $faculty, string $locale): array
    {
        return $faculty->labs->map(function (FacultyLab $lab) use ($locale): array {
            $translation = $this->labTranslation($lab, $locale);

            return [
                'slug' => (string) $lab->slug,
                'title' => (string) $translation->title,
                'summary' => (string) ($translation->description ?? ''),
                'department' => $translation->department,
                'instructor' => $translation->instructor,
                'image' => $lab->image,
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function projectItems(Faculty $faculty, string $locale): array
    {
        $facultyName = (string) $this->facultyTranslation($faculty, $locale)->name;

        return $faculty->studentProjects
            ->map(function (FacultyStudentProject $project) use ($faculty, $locale, $facultyName): array {
                $translation = $this->projectTranslation($project, $locale);
                $fields = $this->projectFieldBlockParser->parse(is_array($translation->body_json) ? $translation->body_json : []);
                $people = $this->projectPeopleFields($project, $locale, $fields);
                $team = $this->projectTeam($translation->team, $people->team);
                $summary = $this->projectSummary((string) ($translation->summary ?? ''), $fields);
                $description = $this->projectDescription($summary, $fields->description);
                $supervisor = $this->stringOrDefault($translation->supervisor, (string) $people->supervisor);

                return [
                    'slug' => (string) $project->slug,
                    'title' => (string) $translation->title,
                    'summary' => $summary,
                    'tag' => $translation->tag,
                    'team' => $team === [] ? null : implode('، ', $team),
                    'supervisor' => $supervisor ?: null,
                    'academicYear' => (string) $people->year,
                    'image' => $this->resolveProjectMedia($project->image),
                    'longDescription' => $description,
                    'overview' => $summary === '' && $description === []
                        ? $this->projectOverview($locale, $facultyName, (string) $people->year, $team, $supervisor)
                        : '',
                    'gallery' => is_array($project->gallery_json) ? $project->gallery_json : [],
                    'teamMembers' => array_map(static fn (string $name): array => ['name' => $name, 'role' => ''], $team),
                    'documents' => collect(is_array($project->documents_json) ? $project->documents_json : [])
                        ->map(fn (array $document): array => [
                            'label' => $locale === 'ar' ? 'تحميل ملف المشروع' : 'Download project file',
                            'file' => (string) ($document['file'] ?? ''),
                        ])
                        ->filter(fn (array $document): bool => $document['file'] !== '')
                        ->values()
                        ->all(),
                    'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) $project->slug),
                ];
            })->values()->all();
    }

    /** @param array<string, mixed> $project @param array<int, array<string, mixed>> $projects */
    private function projectDetailDto(Faculty $faculty, array $project, array $projects, string $locale): FacultyProjectDetailPageDTO
    {
        $project = $this->projectDetailPayload($faculty, $project, $locale);
        $projectSlug = (string) ($project['slug'] ?? '');
        $index = collect($projects)->search(fn (array $item): bool => (string) ($item['slug'] ?? '') === $projectSlug);
        $index = is_int($index) ? $index : 0;
        $count = count($projects);
        $previous = $count > 0 ? $projects[($index - 1 + $count) % $count] : $project;
        $next = $count > 0 ? $projects[($index + 1) % $count] : $project;
        $related = collect($projects)
            ->filter(fn (array $item): bool => (string) ($item['slug'] ?? '') !== $projectSlug)
            ->take(3)
            ->values()
            ->all();

        return new FacultyProjectDetailPageDTO(
            locale: $locale,
            direction: $this->direction($locale),
            facultySlug: $this->publicSlug($faculty),
            faculty: $this->facultyPayload($faculty, $locale),
            project: $project,
            relatedProjects: $related,
            previousProject: $previous,
            nextProject: $next,
            navigation: $this->navigation($faculty, $locale, 'projects'),
            highlights: $this->highlights($faculty, $locale),
            seoTitle: (string) ($project['title'] ?? '').' | '.(string) $this->facultyTranslation($faculty, $locale)->name,
            seoDescription: $this->stringOrDefault($project['summary'] ?? null, $this->stringOrDefault($project['overview'] ?? null, (string) ($project['title'] ?? ''))),
            seoImage: $this->stringOrDefault($project['image'] ?? null, '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp'),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function projectDetailItems(Faculty $faculty, string $locale): array
    {
        $cmsContent = $this->publishedLocalizedPayload($this->targetKeyForSubpage($this->publicSlug($faculty), 'projects'), $locale);

        $projects = array_key_exists('items', $cmsContent ?? [])
            ? collect($this->arrayList($cmsContent['items'] ?? []))->keyBy('slug')
            : collect($this->projectItems($faculty, $locale))->keyBy('slug');

        return $this->withProjectSupervisors($faculty, $locale, $projects
            ->map(fn (array $project): array => [
                ...$this->projectDetailPayload($faculty, $project, $locale),
                'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) ($project['slug'] ?? '')),
            ])
            ->values()
            ->all());
    }

    /**
     * Adds each project's supervisors (name, photo and profile link) from
     * their public faculty profiles. Editors pick them in each language's
     * form; a project whose version in this language has none uses the
     * other language's. A project with no linked supervisor keeps the older
     * free-text `supervisor`.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withProjectSupervisors(Faculty $faculty, string $locale, array $items): array
    {
        $otherLocaleItems = null;
        $wanted = [];
        foreach ($items as $index => $item) {
            $ids = $this->memberIdList($item['supervisorMemberIds'] ?? null);
            if ($ids === []) {
                $otherLocaleItems ??= $this->projectItemsBySlug($faculty, $this->otherLocale($locale));
                $ids = $this->memberIdList($otherLocaleItems[(string) ($item['slug'] ?? '')]['supervisorMemberIds'] ?? null);
            }
            $wanted[$index] = $ids;
        }

        $cards = $this->publicMemberCards($locale, array_values(array_unique(array_merge([], ...array_values($wanted)))));
        foreach ($items as $index => $item) {
            $supervisors = [];
            foreach ($wanted[$index] ?? [] as $memberId) {
                if (isset($cards[$memberId])) {
                    $supervisors[] = $cards[$memberId];
                }
            }
            $items[$index]['supervisors'] = $supervisors;
            $items[$index]['supervisorMemberIds'] = $wanted[$index] ?? [];
            if ($supervisors !== []) {
                $items[$index]['supervisor'] = implode($locale === 'ar' ? '، ' : ', ', array_column($supervisors, 'name'));
            }
        }

        return $items;
    }

    /**
     * The public projects in one language, keyed by slug: the published list,
     * or the imported records while nothing has been published.
     *
     * @return array<string, array<string, mixed>>
     */
    private function projectItemsBySlug(Faculty $faculty, string $locale): array
    {
        $content = $this->publishedLocalizedPayload($this->targetKeyForSubpage($this->publicSlug($faculty), 'projects'), $locale);
        $items = array_key_exists('items', $content ?? [])
            ? $this->arrayList($content['items'] ?? [])
            : $this->projectItems($faculty, $locale);

        return collect($items)
            ->filter(static fn (array $item): bool => (string) ($item['slug'] ?? '') !== '')
            ->keyBy(static fn (array $item): string => (string) $item['slug'])
            ->all();
    }

    private function otherLocale(string $locale): string
    {
        return $locale === 'ar' ? 'en' : 'ar';
    }

    /** @return list<int> */
    private function memberIdList(mixed $value): array
    {
        $ids = [];
        foreach (is_array($value) ? $value : [] as $memberId) {
            if (is_numeric($memberId) && (int) $memberId > 0 && ! in_array((int) $memberId, $ids, true)) {
                $ids[] = (int) $memberId;
            }
        }

        return $ids;
    }

    /** @param array<string, mixed> $project @return array<string, mixed> */
    private function projectDetailPayload(Faculty $faculty, array $project, string $locale): array
    {
        $facultyTitle = (string) $this->facultyTranslation($faculty, $locale)->name;

        return $this->resolveProjectImages([
            ...$project,
            'facultyTitle' => $facultyTitle,
            'facultySlug' => $this->publicSlug($faculty),
            'facultyColor' => $faculty->accent_color ?: '#202759',
            'year' => (string) ($project['year'] ?? $project['academicYear'] ?? ''),
            'longDescription' => $this->stringList($project['longDescription'] ?? []),
            'gallery' => $this->stringList($project['gallery'] ?? []),
            'technologies' => $this->stringList($project['technologies'] ?? []),
            'teamMembers' => $this->arrayList($project['teamMembers'] ?? []),
            'documents' => $this->arrayList($project['documents'] ?? []),
        ]);
    }

    /** @param array<string, mixed> $project @return array<string, mixed> */
    private function resolveProjectImages(array $project): array
    {
        foreach (['image'] as $key) {
            if (is_string($project[$key] ?? null)) {
                $project[$key] = $this->resolveProjectMedia($project[$key]);
            }
        }

        if (is_array($project['gallery'] ?? null)) {
            $project['gallery'] = array_values(array_filter(array_map(
                fn (mixed $image): ?string => is_string($image) ? $this->resolveProjectMedia($image) : null,
                $project['gallery'],
            )));
        }

        if (is_array($project['documents'] ?? null)) {
            $project['documents'] = array_values(array_filter(array_map(
                fn (mixed $doc): ?array => is_array($doc) && is_string($doc['file'] ?? null) && $this->legacyFileIsServed($doc['file'])
                    ? ['label' => (string) ($doc['label'] ?? ''), 'file' => $this->resolveProjectMedia($doc['file'])]
                    : null,
                $project['documents'],
            ), static fn (?array $doc): bool => $doc !== null && $doc['file'] !== null));
        }

        return $project;
    }

    /**
     * Twenty imported project reports point at downloads/files entries that
     * never existed - the old site 404'd them too - so the page offered a
     * download that broke. Where the legacy tree is mounted, a report is
     * offered only if its file is really there. Without the mount (local
     * development, tests) nothing can be verified, so nothing is hidden.
     */
    private function legacyFileIsServed(string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if (! str_starts_with($normalized, 'downloads/files/') || str_contains($normalized, '..')) {
            return true;
        }

        $mount = public_path('_legacy/downloads/files');

        if (! is_dir($mount)) {
            return true;
        }

        $file = public_path('_legacy/'.$normalized);

        return is_file($file) && filesize($file) > 0;
    }

    private function resolveProjectMedia(?string $path): ?string
    {
        $normalized = is_string($path) ? ltrim(str_replace('\\', '/', trim($path)), '/') : '';

        return str_starts_with($normalized, 'downloads/files/')
            ? MediaUrlResolver::resolveLegacy($normalized)
            : MediaUrlResolver::resolve($path);
    }

    private function projectDetailRoute(Faculty $faculty, string $locale, string $projectSlug): string
    {
        return $this->url($locale, '/faculties/'.$this->publicSlug($faculty).'/projects/'.$projectSlug);
    }

    /**
     * Team, supervisor and year are locale-neutral, but the importer kept the
     * legacy field block only in the Arabic body. An English page whose own
     * body has no block reads them from the Arabic one.
     */
    private function projectPeopleFields(FacultyStudentProject $project, string $locale, ProjectFieldBlockDTO $fields): ProjectFieldBlockDTO
    {
        if ($locale === 'ar' || $fields->fieldLines !== []) {
            return $fields;
        }

        $arabic = $project->translations->firstWhere('locale', 'ar');

        return $this->projectFieldBlockParser->parse(is_array($arabic?->body_json) ? $arabic->body_json : []);
    }

    /**
     * The stored team, plus any name the body lists that it is missing. Imports
     * kept only the first name under a multi-line team label, so the body is
     * the more complete record; the stored value stays first because editors
     * maintain it.
     *
     * @param  list<string>  $bodyTeam
     * @return list<string>
     */
    private function projectTeam(?string $storedTeam, array $bodyTeam): array
    {
        $team = [];
        foreach ([...$this->projectFieldBlockParser->splitNames($storedTeam), ...$bodyTeam] as $name) {
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? $name);
            $team[$key] ??= $name;
        }

        return array_values($team);
    }

    /**
     * The description without its opening paragraph when the summary already
     * shows it. Summaries are that paragraph cut to 240 characters, so the page
     * otherwise printed it twice.
     *
     * @param  list<string>  $description
     * @return list<string>
     */
    private function projectDescription(string $summary, array $description): array
    {
        $summary = trim($summary);
        if ($summary !== '' && isset($description[0]) && str_starts_with(trim($description[0]), $summary)) {
            array_shift($description);
        }

        return $description;
    }

    /**
     * Most imported projects have no description: on the legacy site, as here,
     * the page held only the team, the year and the attached report. Rather
     * than an empty column, the page states what is known, composed only from
     * the project's own fields.
     *
     * @param  list<string>  $team
     */
    private function projectOverview(string $locale, string $facultyName, string $year, array $team, string $supervisor): string
    {
        $isAr = $locale === 'ar';
        $sentence = $isAr ? 'مشروع طلابي' : 'A student project';

        if ($facultyName !== '') {
            // Arabic names all open with كلية; three English ones are bare
            // ("Petroleum Engineering"), which reads wrong after "at the".
            $sentence .= $isAr
                ? ' في '.$facultyName
                : ' at the '.(str_starts_with(mb_strtolower($facultyName), 'faculty') ? $facultyName : 'Faculty of '.$facultyName);
        }
        if ($year !== '') {
            $sentence .= $isAr ? ' للعام الدراسي '.$year : ' in the '.$year.' academic year';
        }
        if ($team !== []) {
            $last = (string) end($team);
            // و attaches to an Arabic name ("وروان") but must stand apart before a
            // Latin one, or the bidi algorithm renders "Ahmad shekhaو".
            $conjunction = $isAr ? (preg_match('/^\p{Arabic}/u', $last) === 1 ? ' و' : ' و ') : ' and ';
            $names = count($team) > 1
                ? implode($isAr ? '، ' : ', ', array_slice($team, 0, -1)).$conjunction.$last
                : $team[0];
            $sentence .= $isAr ? '، من إعداد '.$names : ', prepared by '.$names;
        }
        if ($supervisor !== '') {
            $sentence .= $isAr ? ' بإشراف '.$supervisor : ', supervised by '.$supervisor;
        }

        return $sentence.'.';
    }

    /**
     * A stored summary that is really a field value - a name, a year, or a
     * label such as "العام الدراسي:" - is replaced by the first description
     * paragraph, or dropped when the body has none.
     */
    private function projectSummary(string $storedSummary, ProjectFieldBlockDTO $fields): string
    {
        $summary = trim($storedSummary);
        $isFieldValue = $summary !== '' && collect($fields->fieldLines)
            ->contains(static fn (string $line): bool => str_starts_with($line, $summary));

        if ($summary !== '' && ! $isFieldValue) {
            return $storedSummary;
        }

        return Str::limit($fields->description[0] ?? '', 240, '');
    }

    /** @return array<int, array<string, mixed>> */
    private function alumniItems(Faculty $faculty, string $locale): array
    {
        $metadataByTargetId = $this->migrationMetadataByTargetId('alumni', $faculty->alumni->pluck('id')->all());

        return $faculty->alumni
            ->map(function (Alumni $alumni) use ($faculty, $locale, $metadataByTargetId): array {
                $translation = $this->alumniTranslation($alumni, $locale);
                $department = $alumni->department instanceof Department ? $this->departmentTranslation($alumni->department, $locale)->name : null;
                $legacyPhoto = $alumni->legacy_photo_path
                    ?? ($metadataByTargetId[(int) $alumni->getKey()]['legacy_photo'] ?? null);

                return [
                    'id' => (int) $alumni->getKey(),
                    'title' => (string) $translation->full_name,
                    'graduationYear' => $alumni->graduation_year,
                    'department' => $department,
                    'faculty' => $this->facultyTranslation($faculty, $locale)->name,
                    'degree' => $alumni->degree,
                    'grade' => GraduationGrade::tryFrom((string) $alumni->grade)?->label($locale),
                    'gpaPercent' => $alumni->gpa_percent,
                    'academicYear' => $alumni->academic_year,
                    'semester' => AcademicSemester::tryFrom((string) $alumni->semester)?->label($locale),
                    'semesterKey' => AcademicSemester::tryFrom((string) $alumni->semester)?->value,
                    'importedFromDecision' => $alumni->student_list_import_id !== null,
                    'academicPhase' => $locale === 'ar' ? 'خريج' : 'Graduate',
                    'image' => $alumni->photoMedia === null
                        ? MediaUrlResolver::resolveLegacy($legacyPhoto)
                        : MediaUrlResolver::resolveImage($alumni->photoMedia->webp_path, $alumni->photoMedia->path, $alumni->photoMedia->disk),
                ];
            })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function honorItems(Faculty $faculty, string $locale): array
    {
        $metadataByTargetId = $this->migrationMetadataByTargetId('honor_students', $faculty->honorStudents->pluck('id')->all());

        return $faculty->honorStudents
            ->map(function (HonorStudent $student) use ($faculty, $locale, $metadataByTargetId): array {
                $translation = $this->honorTranslation($student, $locale);
                $department = $student->department instanceof Department ? $this->departmentTranslation($student->department, $locale)->name : null;
                $metadata = $metadataByTargetId[(int) $student->getKey()] ?? [];
                $legacyPhoto = $student->legacy_photo_path ?? ($metadata['legacy_photo'] ?? null);

                return [
                    'id' => (int) $student->getKey(),
                    'title' => (string) $translation->full_name,
                    'academicYear' => $student->academic_year,
                    'department' => $department,
                    'gpa' => $student->gpa,
                    'semester' => AcademicSemester::tryFrom((string) $student->semester)?->label($locale),
                    'semesterKey' => AcademicSemester::tryFrom((string) $student->semester)?->value,
                    'studyYear' => $student->study_year !== null ? __('student_lists.study_years.'.$student->study_year, [], $locale) : null,
                    'studyYearNumber' => $student->study_year,
                    'rank' => $student->student_list_import_id !== null ? $student->sort_order : null,
                    'importedFromDecision' => $student->student_list_import_id !== null,
                    'faculty' => $this->facultyTranslation($faculty, $locale)->name,
                    'image' => $student->photoMedia === null
                        ? MediaUrlResolver::resolveLegacy($legacyPhoto)
                        : MediaUrlResolver::resolveImage($student->photoMedia->webp_path, $student->photoMedia->path, $student->photoMedia->disk),
                    'isMemorial' => $this->isMemorialHonorStudent($metadata),
                ];
            })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function importedStudentsMissingFrom(array $items, Faculty $faculty, string $subpageSlug, string $locale): array
    {
        $listed = array_flip(array_map('intval', array_filter(array_column($items, 'id'))));
        $records = $subpageSlug === 'alumni' ? $this->alumniItems($faculty, $locale) : $this->honorItems($faculty, $locale);

        return array_values(array_filter(
            $records,
            static fn (array $record): bool => ($record['importedFromDecision'] ?? false) === true && ! isset($listed[(int) $record['id']]),
        ));
    }

    /** @param array<string, mixed> $metadata */
    private function isMemorialHonorStudent(array $metadata): bool
    {
        return in_array((int) ($metadata['source_id'] ?? 0), self::MEMORIAL_HONOR_SOURCE_IDS, true);
    }

    /** @param array<int, mixed> $targetIds @return array<int, array<string, mixed>> */
    private function migrationMetadataByTargetId(string $targetTable, array $targetIds): array
    {
        $targetIds = array_values(array_filter(array_map('intval', $targetIds)));

        if ($targetIds === []) {
            return [];
        }

        return MigrationLog::query()
            ->where('target_table', $targetTable)
            ->where('status', 'success')
            ->whereIn('target_id', $targetIds)
            ->get(['target_id', 'source_id', 'metadata'])
            ->mapWithKeys(fn (MigrationLog $log): array => [(int) $log->target_id => ['source_id' => (int) $log->source_id, ...(is_array($log->metadata) ? $log->metadata : [])]])
            ->all();
    }

    private function isStudentListSubpage(string $subpageSlug): bool
    {
        return in_array($subpageSlug, ['alumni', 'valedictorians'], true);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeSubpageFilters(string $subpageSlug, array $filters): array
    {
        if (in_array($subpageSlug, ['study-plan', 'study-plan-course'], true) && array_key_exists('_invalid', $filters)) {
            return $filters;
        }

        return match ($subpageSlug) {
            'alumni', 'valedictorians' => $this->studentListFilters($filters),
            'projects' => $this->projectFilters($filters),
            'labs' => $this->labFilters($filters),
            'research' => $this->researchFilters($filters),
            'study-plan', 'study-plan-course' => $this->studyPlanFilters($filters, $subpageSlug === 'study-plan-course'),
            default => [],
        };
    }

    /** @param array<string, mixed> $filters @return array{q: string, year: string, department: string, faculty: string, semester: string, academic_phase: string, page: int} */
    private function studentListFilters(array $filters): array
    {
        $search = $this->filterString($filters['q'] ?? $filters['search'] ?? '');

        return [
            'q' => mb_substr($search, 0, 120),
            'year' => mb_substr($this->filterString($filters['year'] ?? ''), 0, 40),
            'department' => mb_substr($this->filterString($filters['department'] ?? ''), 0, 160),
            'faculty' => mb_substr($this->filterString($filters['faculty'] ?? ''), 0, 180),
            'semester' => in_array($filters['semester'] ?? '', ['first', 'second'], true) ? (string) $filters['semester'] : '',
            'academic_phase' => mb_substr($this->filterString($filters['academic_phase'] ?? ''), 0, 120),
            'page' => max(1, min(500, (int) $this->filterString($filters['page'] ?? 1))),
        ];
    }

    private function filterString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $filters @return array{page: int} */
    private function researchFilters(array $filters): array
    {
        return $this->paginationFilters($filters);
    }

    /** @param array<string, mixed> $filters @return array{page: int} */
    private function paginationFilters(array $filters): array
    {
        return ['page' => max(1, min(500, (int) $this->filterString($filters['page'] ?? 1)))];
    }

    /** @param array<string, mixed> $filters @return array{q: string, page: int} */
    private function projectFilters(array $filters): array
    {
        return [
            'q' => mb_substr($this->filterString($filters['q'] ?? $filters['search'] ?? ''), 0, 120),
            ...$this->paginationFilters($filters),
        ];
    }

    /** @param array<string, mixed> $filters @return array{lab: string, page: int} */
    private function labFilters(array $filters): array
    {
        return [
            'lab' => mb_substr($this->filterString($filters['lab'] ?? ''), 0, 160),
            ...$this->paginationFilters($filters),
        ];
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function studentFilterOptions(array $items, string $subpageSlug): array
    {
        return [
            'years' => collect($items)
                ->map(fn (array $item): ?string => $subpageSlug === 'alumni'
                    ? $this->nullableString($item['graduationYear'] ?? null)
                    : $this->academicYearFilterValue($item['academicYear'] ?? null))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
            'departments' => collect($items)->pluck('department')->filter()->unique()->sort()->values()->all(),
            'faculties' => collect($items)->pluck('faculty')->filter()->unique()->sort()->values()->all(),
            'semesters' => collect($items)
                ->filter(fn (array $item): bool => is_string($item['semesterKey'] ?? null) && is_string($item['semester'] ?? null))
                ->map(fn (array $item): array => ['key' => (string) $item['semesterKey'], 'label' => (string) $item['semester']])
                ->unique('key')
                ->values()
                ->all(),
            'academicPhases' => collect($items)->pluck('academicPhase')->filter()->unique()->values()->all(),
        ];
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
    private function filteredStudentItems(array $items, string $subpageSlug, array $filters): array
    {
        return collect($items)
            ->filter(function (array $item) use ($subpageSlug, $filters): bool {
                if ($filters['q'] !== '' && ! str_contains(mb_strtolower($this->studentSearchText($item)), mb_strtolower((string) $filters['q']))) {
                    return false;
                }

                if ($filters['year'] !== '') {
                    $itemYear = $subpageSlug === 'alumni'
                        ? $this->nullableString($item['graduationYear'] ?? null)
                        : $this->academicYearFilterValue($item['academicYear'] ?? null);

                    if ($itemYear !== (string) $filters['year']) {
                        return false;
                    }
                }

                if ($filters['department'] !== '' && (string) ($item['department'] ?? '') !== (string) $filters['department']) {
                    return false;
                }

                if ($filters['faculty'] !== '' && (string) ($item['faculty'] ?? '') !== (string) $filters['faculty']) {
                    return false;
                }

                if ($filters['semester'] !== '' && (string) ($item['semesterKey'] ?? '') !== (string) $filters['semester']) {
                    return false;
                }

                return $filters['academic_phase'] === '' || (string) ($item['academicPhase'] ?? '') === (string) $filters['academic_phase'];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filteredProjectItems(array $items, array $filters): array
    {
        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        if ($query === '') {
            return $items;
        }

        return collect($items)
            ->filter(function (array $item) use ($query): bool {
                $searchable = mb_strtolower(implode(' ', [
                    (string) ($item['title'] ?? ''),
                    (string) ($item['team'] ?? ''),
                    (string) ($item['supervisor'] ?? ''),
                ]));

                return str_contains($searchable, $query);
            })
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function newestStudentItems(array $items, string $subpageSlug): array
    {
        usort($items, function (array $left, array $right) use ($subpageSlug): int {
            $leftYear = $subpageSlug === 'alumni'
                ? (int) ($left['graduationYear'] ?? 0)
                : $this->academicYearSortValue($left['academicYear'] ?? null);
            $rightYear = $subpageSlug === 'alumni'
                ? (int) ($right['graduationYear'] ?? 0)
                : $this->academicYearSortValue($right['academicYear'] ?? null);

            return ($rightYear <=> $leftYear)
                ?: ($this->semesterOrder($right) <=> $this->semesterOrder($left))
                ?: $this->decisionOrder($left, $right)
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0))
                ?: strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $items;
    }

    /** @param array<string, mixed> $item */
    private function semesterOrder(array $item): int
    {
        return AcademicSemester::tryFrom((string) ($item['semesterKey'] ?? ''))?->order() ?? 0;
    }

    /**
     * Within one imported semester: top students by study year then rank,
     * graduates by GPA, as the faculty decision lists them.
     *
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function decisionOrder(array $left, array $right): int
    {
        if (($left['importedFromDecision'] ?? false) !== true || ($right['importedFromDecision'] ?? false) !== true) {
            return 0;
        }

        return ((int) ($left['studyYearNumber'] ?? 0) <=> (int) ($right['studyYearNumber'] ?? 0))
            ?: ((int) ($left['rank'] ?? 0) <=> (int) ($right['rank'] ?? 0))
            ?: ((float) ($right['gpaPercent'] ?? 0) <=> (float) ($left['gpaPercent'] ?? 0));
    }

    private function academicYearSortValue(mixed $academicYear): int
    {
        $value = $this->nullableString($academicYear);
        if ($value === null || preg_match('/\b(19|20|21)\d{2}\b/', $value, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[0];
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $filters @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>} */
    private function paginatedItems(array $items, array $filters, int $perPage): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, (int) ($filters['page'] ?? 1)), $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        return [
            array_slice($items, $offset, $perPage),
            [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => min($total, $offset + $perPage),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    /**
     * A student on the honor list in several semesters often has a photo on
     * only one record; the others reuse it. Students match by full name
     * within the faculty's list. A record's own photo wins, except that a
     * media library photo (one an editor chose and framed) replaces an
     * old-site photo on the student's other records, so they all show it.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withSharedHonorPhotos(array $items): array
    {
        $libraryImages = $this->libraryImageUrls(array_column($items, 'image'));
        $photos = [];

        foreach ($items as $item) {
            $key = $this->studentNameKey($item['title'] ?? null);
            $image = $item['image'] ?? null;

            if ($key === '' || ! is_string($image) || trim($image) === '') {
                continue;
            }

            $isLibrary = isset($libraryImages[$image]);

            if (! isset($photos[$key]) || ($isLibrary && ! $photos[$key]['library'])) {
                $photos[$key] = ['image' => $image, 'library' => $isLibrary];
            }
        }

        return array_map(function (array $item) use ($photos, $libraryImages): array {
            $image = $item['image'] ?? null;
            $shared = $photos[$this->studentNameKey($item['title'] ?? null)] ?? null;

            if ($shared === null) {
                return $item;
            }

            $hasOwn = is_string($image) && trim($image) !== '';

            if (! $hasOwn || ($shared['library'] && ! isset($libraryImages[$image]))) {
                $item['image'] = $shared['image'];
            }

            return $item;
        }, $items);
    }

    /**
     * The given image URLs that are main media library images, as a set.
     *
     * @param  array<int, mixed>  $urls
     * @return array<string, true>
     */
    private function libraryImageUrls(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls, static fn (mixed $url): bool => is_string($url) && trim($url) !== '')));

        if ($urls === []) {
            return [];
        }

        $candidates = [];

        foreach ($urls as $url) {
            $path = ltrim(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?: $url)), '/');
            $candidates[] = $path;
            // A public-disk URL is "/storage/<path>"; the media row keeps "<path>".
            $candidates[] = (string) preg_replace('#^storage/#', '', $path);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        $library = [];

        MediaAsset::query()
            ->where('library_scope', 'main')
            ->where('media_type', 'image')
            ->where(fn (Builder $query): Builder => $query->whereIn('path', $candidates)->orWhereIn('webp_path', $candidates))
            ->get(['path', 'webp_path', 'disk'])
            ->each(function (MediaAsset $asset) use (&$library): void {
                foreach ([$asset->path, $asset->webp_path] as $path) {
                    $url = MediaUrlResolver::resolve($path, $asset->disk);

                    if (is_string($url) && $url !== '') {
                        $library[$url] = true;
                    }
                }
            });

        return array_intersect_key($library, array_flip($urls));
    }

    private function studentNameKey(mixed $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        // Spellings of the same Arabic name drift between semesters.
        $name = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ـ'], ['ا', 'ا', 'ا', 'ه', 'ي', ''], $name);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }

    private function resolvedStudentImages(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $image = $item['image'] ?? null;
                $item['image'] = is_string($image) ? MediaUrlResolver::resolve($image) : null;

                return $item;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptyPagination(int $total): array
    {
        return [
            'current_page' => 1,
            'per_page' => $total,
            'total_items' => $total,
            'total_pages' => 1,
            'from' => $total > 0 ? 1 : 0,
            'to' => $total,
        ];
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $filters @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>} */
    private function paginatedResearchItems(array $items, array $filters): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / self::RESEARCH_LIST_PER_PAGE));
        $currentPage = min(max(1, (int) ($filters['page'] ?? 1)), $totalPages);
        $offset = ($currentPage - 1) * self::RESEARCH_LIST_PER_PAGE;

        return [
            array_slice($items, $offset, self::RESEARCH_LIST_PER_PAGE),
            [
                'current_page' => $currentPage,
                'per_page' => self::RESEARCH_LIST_PER_PAGE,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => min($total, $offset + self::RESEARCH_LIST_PER_PAGE),
            ],
        ];
    }

    /** @param array<string, mixed> $item */
    private function studentSearchText(array $item): string
    {
        return implode(' ', array_map('strval', array_filter([
            $item['title'] ?? null,
            $item['graduationYear'] ?? null,
            $item['academicYear'] ?? null,
            $item['department'] ?? null,
            $item['faculty'] ?? null,
            $item['degree'] ?? null,
            $item['gpa'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '')));
    }

    private function academicYearFilterValue(mixed $academicYear): ?string
    {
        $value = $this->nullableString($academicYear);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', $value, $match) === 1 && (int) $match[2] === (int) $match[1] + 1) {
            return $match[1].'/'.$match[2];
        }

        $parts = array_map('trim', explode('/', $value));

        return $parts[0] !== '' ? $parts[0] : $value;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function studyPlanFilters(array $filters, bool $coursePage): array
    {
        $normalized = ['_invalid' => false, '_type_provided' => array_key_exists('type', $filters)];

        foreach ($coursePage ? ['department', 'course', 'type'] : ['department'] as $key) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];
            if (! is_string($value) || $value === '' || strlen($value) > 160 || preg_match('/^[A-Za-z0-9-]+$/', $value) !== 1) {
                $normalized['_invalid'] = true;

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $pageData
     * @param  array<string, mixed>  $filters
     * @return array{filters: array<string, string>, detail: array<string, mixed>}|null
     */
    private function studyPlanSelection(array $pageData, array $filters, string $locale, bool $coursePage): ?array
    {
        if (($filters['_invalid'] ?? false) === true) {
            return null;
        }

        $payload = is_array($pageData['payload'] ?? null) ? $pageData['payload'] : [];
        $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : [];
        $departments = collect(is_array($plan['departments'] ?? null) ? $plan['departments'] : [])
            ->filter(fn (mixed $department): bool => is_array($department) && trim((string) ($department['id'] ?? '')) !== '')
            ->values();
        $departmentId = $filters['department'] ?? null;
        $activeDepartment = is_string($departmentId)
            ? $departments->firstWhere('id', $departmentId)
            : $departments->first();

        if (! is_array($activeDepartment)) {
            return null;
        }

        $activeDepartment = $this->sanitizedStudyPlanDepartment($activeDepartment);

        $validatedFilters = [];
        if (is_string($departmentId)) {
            $validatedFilters['department'] = $departmentId;
        }

        if (! $coursePage) {
            return [
                'filters' => $validatedFilters,
                'detail' => ['activeDepartment' => $activeDepartment],
            ];
        }

        $courseId = $filters['course'] ?? null;
        if (! is_string($departmentId) || ! is_string($courseId)) {
            return null;
        }

        $courses = collect(is_array($activeDepartment['terms'] ?? null) ? $activeDepartment['terms'] : [])
            ->filter(fn (mixed $term): bool => is_array($term))
            ->flatMap(fn (array $term): array => is_array($term['courses'] ?? null) ? $term['courses'] : [])
            ->filter(fn (mixed $course): bool => is_array($course))
            ->values();
        $course = $courses->firstWhere('id', $courseId);

        if (! is_array($course)) {
            return null;
        }

        $lessons = collect(is_array($course['lessons'] ?? null) ? $course['lessons'] : [])
            ->filter(fn (mixed $lesson): bool => is_array($lesson))
            ->map(function (array $lesson): array {
                return [
                    ...$lesson,
                    'pdfUrl' => $this->studyPlanLinkService->sanitizeCourseMaterialPath($lesson['pdfUrl'] ?? null),
                ];
            })
            ->values();
        $availableTypes = $lessons->pluck('type')->filter(fn (mixed $type): bool => is_string($type) && $type !== '')->unique()->values();
        $selectedType = is_string($filters['type'] ?? null) ? $filters['type'] : 'all';

        if ($selectedType !== 'all' && ! $availableTypes->contains($selectedType)) {
            return null;
        }

        $instructor = is_array($course['instructor'] ?? null) ? $course['instructor'] : null;
        $course['instructorUrl'] = $this->courseInstructorUrl($locale, $instructor);
        $course['teachers'] = $this->courseTeachers($locale, $course['teachers'] ?? null);
        $course['lessons'] = $lessons->all();
        $course['specificationUrl'] = $this->studyPlanLinkService->sanitizeCourseMaterialPath($course['specificationUrl'] ?? null);
        $validatedFilters = ['department' => $departmentId, 'course' => $courseId];

        if (($filters['_type_provided'] ?? false) === true) {
            $validatedFilters['type'] = $selectedType;
        }

        return [
            'filters' => $validatedFilters,
            'detail' => [
                'activeDepartment' => $activeDepartment,
                'course' => $course,
                'lessons' => $lessons->when($selectedType !== 'all', fn (Collection $items): Collection => $items->where('type', $selectedType))->values()->all(),
                'selectedType' => $selectedType,
                'availableTypes' => $availableTypes->all(),
                'prerequisites' => collect($course['prerequisites'] ?? [])->map(fn (mixed $id): mixed => $courses->firstWhere('id', $id))->filter()->values()->all(),
                'openedCourses' => $courses->filter(fn (array $item): bool => in_array($courseId, is_array($item['prerequisites'] ?? null) ? $item['prerequisites'] : [], true))->values()->all(),
            ],
        ];
    }

    /**
     * The theory and practical teachers shown on a course page, read from
     * their faculty profiles so the name and photo are the profile's own.
     * A part can have several teachers (`memberIds`); plans saved before that
     * hold one `memberId`. A part switched off, or a profile that is not
     * public, is left out.
     *
     * @return list<array{part: string, name: string, image: string|null, url: string}>
     */
    private function courseTeachers(string $locale, mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $wanted = [];
        foreach (['theory', 'practical'] as $part) {
            $entry = is_array($stored[$part] ?? null) ? $stored[$part] : [];
            if (($entry['enabled'] ?? true) === false) {
                continue;
            }
            $ids = is_array($entry['memberIds'] ?? null) ? $entry['memberIds'] : [$entry['memberId'] ?? null];
            foreach ($ids as $memberId) {
                if (is_numeric($memberId) && (int) $memberId > 0) {
                    $wanted[$part][(int) $memberId] = (int) $memberId;
                }
            }
        }

        if ($wanted === []) {
            return [];
        }

        $cards = $this->publicMemberCards($locale, array_values(array_unique(array_merge(...array_map('array_values', array_values($wanted))))));
        $teachers = [];
        foreach ($wanted as $part => $memberIds) {
            foreach ($memberIds as $memberId) {
                if (isset($cards[$memberId])) {
                    $teachers[] = ['part' => $part, ...$cards[$memberId]];
                }
            }
        }

        return $teachers;
    }

    /**
     * Name, photo and profile link of each public faculty profile, keyed by its id.
     *
     * @param  array<int, int>  $memberIds
     * @return array<int, array{name: string, image: string, url: string}>
     */
    private function publicMemberCards(string $locale, array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $cards = [];
        foreach (FacultyMember::query()->public()->whereKey($memberIds)->with(['translations', 'photoMedia'])->get() as $member) {
            $translation = $member->translations->firstWhere('locale', $locale)
                ?? $member->translations->firstWhere('locale', 'ar')
                ?? $member->translations->firstWhere('locale', 'en');
            $name = trim((string) ($translation?->full_name ?? ''));
            if ($name === '') {
                continue;
            }
            $cards[(int) $member->getKey()] = [
                'name' => $name,
                'image' => $member->photoMedia instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
                    : MediaUrlResolver::resolveLegacy($member->legacy_photo_path),
                'url' => $this->url($locale, '/about/profile/'.$member->slug),
            ];
        }

        return $cards;
    }

    /** @param array<string, mixed>|null $instructor */
    private function courseInstructorUrl(string $locale, ?array $instructor): ?string
    {
        $slug = trim((string) ($instructor['staffSlug'] ?? ''));

        if ($slug === '' || preg_match('/^[A-Za-z0-9-]+$/', $slug) !== 1) {
            return null;
        }

        $profile = $this->profilePageService->getProfile($locale, 'faculty-member', $slug);

        return $profile instanceof ProfilePageDTO && str_starts_with($profile->path, '/'.$locale.'/about/profile/')
            ? $profile->path
            : null;
    }

    /** @param array<string, mixed> $department @return array<string, mixed> */
    private function sanitizedStudyPlanDepartment(array $department): array
    {
        $department['terms'] = collect(is_array($department['terms'] ?? null) ? $department['terms'] : [])
            ->filter(fn (mixed $term): bool => is_array($term))
            ->map(function (array $term): array {
                $term['courses'] = collect(is_array($term['courses'] ?? null) ? $term['courses'] : [])
                    ->filter(fn (mixed $course): bool => is_array($course))
                    ->map(function (array $course): array {
                        $course['lessons'] = collect(is_array($course['lessons'] ?? null) ? $course['lessons'] : [])
                            ->filter(fn (mixed $lesson): bool => is_array($lesson))
                            ->map(fn (array $lesson): array => [
                                ...$lesson,
                                'pdfUrl' => $this->studyPlanLinkService->sanitizeCourseMaterialPath($lesson['pdfUrl'] ?? null),
                            ])
                            ->values()
                            ->all();
                        $course['specificationUrl'] = $this->studyPlanLinkService->sanitizeCourseMaterialPath($course['specificationUrl'] ?? null);

                        return $course;
                    })
                    ->values()
                    ->all();

                return $term;
            })
            ->values()
            ->all();

        return $department;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function subpageTitle(string $subpageSlug, string $locale): string
    {
        $titles = [
            'overview' => $locale === 'ar' ? 'لمحة عامة' : 'Overview',
            'departments' => $locale === 'ar' ? 'الأقسام' : 'Departments',
            'study-plan' => $locale === 'ar' ? 'الخطة الدراسية' : 'Study Plan',
            'study-plan-course' => $locale === 'ar' ? 'محاضرات المقرر' : 'Course Lessons',
            'labs' => $locale === 'ar' ? 'المخابر' : 'Laboratories',
            'projects' => $locale === 'ar' ? 'المشاريع' : 'Projects',
            'alumni' => $locale === 'ar' ? 'الخريجون' : 'Alumni',
            'valedictorians' => $locale === 'ar' ? 'قائمة الشرف' : 'Honor List',
            'training' => $locale === 'ar' ? 'التدريب' : 'Training',
            'clinical-cases' => $locale === 'ar' ? 'الحالات السريرية' : 'Clinical Cases',
            'final-exams' => $locale === 'ar' ? 'برنامج الامتحان النهائي' : 'Final Exam Schedule',
            'research' => $locale === 'ar' ? 'أحدث الأبحاث' : 'Latest Research',
            'members' => $locale === 'ar' ? 'أعضاء الهيئة الأكاديمية' : 'Faculty Members',
        ];

        return $titles[$subpageSlug] ?? $subpageSlug;
    }

    /**
     * The research a faculty features, picked by slug from the site-wide
     * publications. Content saved before the picker existed has no
     * selectedResearchSlugs key; its old manual cards that point at a
     * publication count as picks, and cards without one never rendered.
     *
     * @param  array<string, mixed>  $content
     * @return array<int, string>
     */
    private function selectedResearchSlugs(array $content): array
    {
        $values = array_key_exists('selectedResearchSlugs', $content)
            ? $content['selectedResearchSlugs']
            : array_map(static fn (mixed $item): mixed => is_array($item) ? ($item['slug'] ?? null) : null, $this->arrayList($content['latestResearch'] ?? []));

        return array_slice(array_values(array_unique(array_filter(
            is_array($values) ? $values : [],
            static fn (mixed $slug): bool => is_string($slug) && preg_match('/^[A-Za-z0-9-]+$/', $slug) === 1,
        ))), 0, self::LATEST_RESEARCH_LIMIT);
    }

    /** @param array<int, string> $selectedSlugs @return array<int, array<string, mixed>> */
    private function latestResearch(Faculty $faculty, string $locale, array $selectedSlugs = []): array
    {
        $researchPage = $this->researchPageService->facultyFeaturedPublications($this->publicSlug($faculty), $locale, $selectedSlugs, self::LATEST_RESEARCH_LIMIT);

        return collect($researchPage->data['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && preg_match('/^[A-Za-z0-9-]+$/', (string) ($item['slug'] ?? '')) === 1
                && trim((string) ($item['title'] ?? '')) !== '')
            ->take(self::LATEST_RESEARCH_LIMIT)
            ->map(fn (array $item): array => [
                ...$item,
                'url' => $this->url($locale, '/research/publications/'.(string) $item['slug']),
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $pageData */
    private function deanProfile(Faculty $faculty, string $locale, array $pageData): ?ProfilePageDTO
    {
        return $this->deanProfileFor($faculty, $locale, is_array($pageData['payload']['dean'] ?? null) ? $pageData['payload']['dean'] : []);
    }

    /**
     * The faculty's dean profile, when the dean shown in the message is the
     * same person as the published dean profile for this faculty.
     *
     * @param  array<string, mixed>  $deanPayload
     */
    private function deanProfileFor(Faculty $faculty, string $locale, array $deanPayload): ?ProfilePageDTO
    {
        $displayName = (string) ($deanPayload['name'] ?? $deanPayload['name'.ucfirst($locale)] ?? '');
        if ($displayName === '') {
            return null;
        }

        // The name in the dean message decides who is linked. A dean is not
        // always filed with the "dean" category (some are council members with
        // a dean appointment), so every leadership profile with that name is a
        // candidate, and the one filed as this faculty's dean wins. When the
        // profile's name has since been edited and no longer matches the
        // message, the one dean filed for this faculty is still the dean.
        $facultySlug = $this->publicSlug($faculty);
        $leaders = $this->aboutPageService->getLeadershipProfiles($locale);
        $dean = $leaders
            ->filter(fn (PersonDTO $person): bool => $this->samePersonName($displayName, $person->name))
            ->sortByDesc(fn (PersonDTO $person): int => ($person->category === 'dean' ? 2 : 0)
                + ($this->canonicalFacultySlug((string) $person->facultySlug) === $facultySlug ? 1 : 0))
            ->first();

        if (! $dean instanceof PersonDTO) {
            $facultyDeans = $leaders->filter(fn (PersonDTO $person): bool => $person->category === 'dean'
                && $this->canonicalFacultySlug((string) $person->facultySlug) === $facultySlug);
            $dean = $facultyDeans->count() === 1 ? $facultyDeans->first() : null;
        }

        if (! $dean instanceof PersonDTO) {
            return null;
        }

        // The card's slug is the faculty profile's when there is one.
        $profile = $this->profilePageService->getProfile($locale, 'unified', $dean->slug);

        return $profile instanceof ProfilePageDTO ? $profile : null;
    }

    private function samePersonName(string $first, string $second): bool
    {
        return PersonName::same($first, $second);
    }

    /** @return array<int, array{id: string, label: string, body: string}> */
    private function overviewTabs(?FacultyPageTranslation $translation, FacultyTranslation $facultyTranslation, string $locale): array
    {
        $tabs = [[
            'id' => 'overview',
            'label' => $locale === 'ar' ? 'لمحة عن الكلية' : 'Overview',
            'body' => (string) ($translation?->body ?? $facultyTranslation->description ?? ''),
        ]];

        $sections = $translation?->getAttribute('sections_json');

        if (! is_iterable($sections)) {
            return $tabs;
        }

        foreach ($sections as $index => $section) {
            if (! is_array($section) || empty($section['body'])) {
                continue;
            }

            $tabs[] = [
                'id' => (string) ($section['id'] ?? 'section-'.$index),
                'label' => (string) ($section['title'] ?? ''),
                'body' => (string) $section['body'],
            ];
        }

        return $tabs;
    }

    /** @return array<int, string> */
    private function gallery(Faculty $faculty): array
    {
        $gallery = is_array($faculty->gallery_json) ? $faculty->gallery_json : [];

        return collect([$faculty->hero_image, ...$gallery])
            ->filter(fn (mixed $image): bool => is_string($image)
                && trim($image) !== ''
                && (! str_starts_with($image, '/') || is_file(public_path(ltrim((string) parse_url($image, PHP_URL_PATH), '/')))))
            ->unique()
            ->values()
            ->all();
    }

    private function facultyTranslation(Faculty $faculty, string $locale): FacultyTranslation
    {
        return $faculty->translations->firstWhere('locale', $locale)
            ?? $faculty->translations->firstWhere('locale', 'ar')
            ?? $faculty->translations->first();
    }

    private function pageTranslation(FacultyPage $page, string $locale): FacultyPageTranslation
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', 'ar')
            ?? $page->translations->first();
    }

    private function highlightTranslation(FacultyHighlight $highlight, string $locale): FacultyHighlightTranslation
    {
        return $highlight->translations->firstWhere('locale', $locale)
            ?? $highlight->translations->firstWhere('locale', 'ar')
            ?? $highlight->translations->first();
    }

    private function departmentTranslation(Department $department, string $locale): DepartmentTranslation
    {
        return $department->translations->firstWhere('locale', $locale)
            ?? $department->translations->firstWhere('locale', 'ar')
            ?? $department->translations->first();
    }

    private function labTranslation(FacultyLab $lab, string $locale): FacultyLabTranslation
    {
        return $lab->translations->firstWhere('locale', $locale)
            ?? $lab->translations->firstWhere('locale', 'ar')
            ?? $lab->translations->first();
    }

    private function projectTranslation(FacultyStudentProject $project, string $locale): FacultyStudentProjectTranslation
    {
        return $project->translations->firstWhere('locale', $locale)
            ?? $project->translations->firstWhere('locale', 'ar')
            ?? $project->translations->first();
    }

    private function alumniTranslation(Alumni $alumni, string $locale): AlumniTranslation
    {
        return $alumni->translations->firstWhere('locale', $locale)
            ?? $alumni->translations->firstWhere('locale', 'ar')
            ?? $alumni->translations->first();
    }

    private function honorTranslation(HonorStudent $student, string $locale): HonorStudentTranslation
    {
        return $student->translations->firstWhere('locale', $locale)
            ?? $student->translations->firstWhere('locale', 'ar')
            ?? $student->translations->first();
    }

    private function memberTranslation(FacultyMember $member, string $locale): ?FacultyMemberTranslation
    {
        return $member->translations->firstWhere('locale', $locale)
            ?? $member->translations->firstWhere('locale', 'ar')
            ?? $member->translations->firstWhere('locale', 'en')
            ?? null;
    }

    private function publicSlug(Faculty $faculty): string
    {
        return (string) ($faculty->public_slug ?: $faculty->slug);
    }

    private function isAvailableSubpage(Faculty|string $faculty, string $subpageSlug): bool
    {
        if (is_string($faculty)) {
            $faculty = $this->facultyByPublicSlug($faculty);
        }

        if (! $faculty instanceof Faculty) {
            return false;
        }

        $facultySlug = $this->publicSlug($faculty);

        if (! $this->facultyMayUseSubpage($facultySlug, $subpageSlug)) {
            return false;
        }

        $visibleSlugs = $this->subpageCardService->getVisibleSubpageSlugs($facultySlug);

        if ($visibleSlugs === []) {
            if ($this->subpageCardService->hasAnyCards($facultySlug)) {
                return false;
            }

            return ! $this->isRestrictedSubpage($subpageSlug) || $faculty->pages->contains(fn (FacultyPage $page): bool => $page->slug === $subpageSlug);
        }

        return in_array($subpageSlug, $visibleSlugs, true);
    }

    /** A faculty-specific subpage (config faculty_subpages.restricted) is only for the faculties listed with it. */
    private function facultyMayUseSubpage(string $facultySlug, string $subpageSlug): bool
    {
        $allowed = config('faculty_subpages.restricted.'.$subpageSlug);

        return ! is_array($allowed) || in_array($facultySlug, $allowed, true);
    }

    private function isRestrictedSubpage(string $subpageSlug): bool
    {
        return is_array(config('faculty_subpages.restricted.'.$subpageSlug));
    }

    private function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function url(string $locale, string $path): string
    {
        return '/'.$locale.'/'.ltrim($path, '/');
    }

    private function facilitiesCache(): CacheServiceInterface
    {
        return $this->cacheService->tags(['facilities', 'public-pages', 'seo', 'sitemap']);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localizedPayload(array $payload, string $locale): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->localizedPayload($value, $locale);
            }
        }

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, ucfirst($locale))) {
                continue;
            }

            $base = substr($key, 0, -2);
            $base = lcfirst($base);
            $payload[$base] = $value;
        }

        return $payload;
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function localizedPayloadList(array $items, string $locale): array
    {
        return collect($items)->map(fn (array $item): array => $this->localizedPayload($item, $locale))->values()->all();
    }
}
