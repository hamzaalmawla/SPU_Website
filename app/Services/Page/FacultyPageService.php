<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
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
use App\DTOs\Faculty\FacultySubpageDTO;
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
use App\Models\Shared\MigrationLog;
use App\Support\MediaUrlResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FacultyPageService implements FacultyPageServiceInterface
{
    private const FACULTY_SLUGS = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    /** @var array<string, string> */
    private const FACULTY_SLUG_ALIASES = [
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    private const SUBPAGE_SLUGS = ['overview', 'departments', 'study-plan', 'study-plan-course', 'labs', 'projects', 'alumni', 'valedictorians', 'training', 'research'];

    private const ALUMNI_LIST_PER_PAGE = 12;

    private const HONOR_LIST_PER_PAGE = 6;

    private const FACULTY_LIST_PER_PAGE = 6;

    private const RESEARCH_LIST_PER_PAGE = 6;

    private const MEMORIAL_HONOR_SOURCE_IDS = [134, 359, 390, 898, 1118];

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly FacultyStudyPlanLinkServiceInterface $studyPlanLinkService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly AboutPageServiceInterface $aboutPageService,
        private readonly ProfilePageServiceInterface $profilePageService,
    ) {}

    public function getHub(string $locale): FacultyHubPageDTO
    {
        return $this->facilitiesCache()->remember("public.facilities.hub.{$locale}", function () use ($locale): FacultyHubPageDTO {
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

        return $this->facilitiesCache()->remember("public.facilities.{$facultySlug}.{$locale}", function () use ($facultySlug, $locale): ?FacultyDetailPageDTO {
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
            'latestResearch' => $this->latestResearch($faculty, $locale),
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
        $filters = match ($subpageSlug) {
            'alumni', 'valedictorians' => $this->studentListFilters($filters),
            'projects' => $this->paginationFilters($filters),
            'labs' => $this->labFilters($filters),
            'research' => $this->researchFilters($filters),
            'study-plan', 'study-plan-course' => $this->studyPlanFilters($filters, $subpageSlug === 'study-plan-course'),
            default => [],
        };
        $filterKey = md5((string) json_encode($filters));

        return $this->facilitiesCache()->remember("public.facilities.{$facultySlug}.{$subpageSlug}.{$locale}.{$filterKey}", function () use ($facultySlug, $subpageSlug, $locale, $filters): ?FacultySubpageDTO {
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

        return $this->facilitiesCache()->remember("public.facilities.{$facultySlug}.projects.{$projectSlug}.{$locale}", function () use ($facultySlug, $projectSlug, $locale): ?FacultyProjectDetailPageDTO {
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
            }
        }

        if ($subpageSlug === 'departments') {
            $items = $this->studyPlanLinkService->enrichDepartmentItems($this->publicSlug($faculty), $locale, $items);
        }

        $filterOptions = [];
        $pagination = $this->emptyPagination(count($items));
        $detail = [];

        if (in_array($subpageSlug, ['study-plan', 'study-plan-course'], true)) {
            $selection = $this->studyPlanSelection($pageData, $filters, $locale, $subpageSlug === 'study-plan-course');

            if ($selection === null) {
                return null;
            }

            $filters = $selection['filters'];
            $detail = $selection['detail'];
        }

        if ($this->isStudentListSubpage($subpageSlug)) {
            $filterOptions = $this->studentFilterOptions($items, $subpageSlug);
            $items = $this->filteredStudentItems($items, $subpageSlug, $filters);
            $items = $this->newestStudentItems($items, $subpageSlug);
            $items = $this->resolvedStudentImages($items);
            $perPage = $subpageSlug === 'alumni' ? self::ALUMNI_LIST_PER_PAGE : self::HONOR_LIST_PER_PAGE;
            [$items, $pagination] = $this->paginatedItems($items, $filters, $perPage);
        }

        if ($subpageSlug === 'projects') {
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

        $latestResearch = $subpageSlug === 'overview' ? $this->latestResearch($faculty, $locale) : [];
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
        foreach ($this->frontendProjectData() as $facultySlug => $projects) {
            if (collect($projects)->contains(fn (array $project): bool => ($project['id'] ?? null) === $projectId)) {
                return '/'.$locale.'/facilities/'.$facultySlug.'/projects/'.rawurlencode($projectId);
            }
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
            'url' => $this->url($locale, "/facilities/{$slug}"),
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
            'latestResearch' => $this->latestResearch($faculty, $locale),
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

        return [
            ...$modelFaculty,
            'name' => $name,
            'nameAr' => $locale === 'ar' ? $name : (string) ($modelFaculty['nameAr'] ?? $name),
            'nameEn' => $locale === 'en' ? $name : (string) ($modelFaculty['nameEn'] ?? $name),
            'title' => $this->stringOrDefault($faculty['title'] ?? null, $this->stringOrDefault($content['title'] ?? null, (string) ($modelFaculty['title'] ?? $name))),
            'summary' => $this->stringOrDefault($faculty['summary'] ?? null, $this->stringOrDefault($content['summary'] ?? null, (string) ($modelFaculty['summary'] ?? ''))),
            'description' => $this->stringOrDefault($faculty['description'] ?? null, $this->stringOrDefault($content['body'] ?? null, (string) ($modelFaculty['description'] ?? ''))),
            'yearsLabel' => $this->stringOrDefault($faculty['yearsLabel'] ?? null, (string) ($modelFaculty['yearsLabel'] ?? '')),
            'accentColor' => $this->stringOrDefault($faculty['accentColor'] ?? null, (string) ($modelFaculty['accentColor'] ?? '#202759')),
            'heroImage' => $this->stringOrDefault($faculty['heroImage'] ?? null, (string) ($modelFaculty['heroImage'] ?? '/images/uni-main-place.JPG')),
            'logoImage' => $this->stringOrDefault($faculty['logoImage'] ?? null, (string) ($modelFaculty['logoImage'] ?? '/images/logo-spu.png')),
        ];
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $modelContent @return array<string, mixed> */
    private function cmsFacultyContent(array $content, array $modelContent): array
    {
        $tabs = $this->arrayList($content['tabs'] ?? []);
        $dean = is_array($content['dean'] ?? null) ? $content['dean'] : [];
        $gallery = $this->imageList($content['gallery'] ?? []);
        $latestResearch = $this->arrayList($content['latestResearch'] ?? []);

        return [
            ...$modelContent,
            'title' => $this->stringOrDefault($content['title'] ?? null, (string) ($modelContent['title'] ?? '')),
            'summary' => $this->stringOrDefault($content['summary'] ?? null, (string) ($modelContent['summary'] ?? '')),
            'body' => $this->stringOrDefault($content['body'] ?? null, (string) ($modelContent['body'] ?? '')),
            'tabs' => $tabs !== [] ? $tabs : ($modelContent['tabs'] ?? []),
            'dean' => $dean !== [] ? $dean : ($modelContent['dean'] ?? []),
            'gallery' => $gallery !== [] ? $gallery : ($modelContent['gallery'] ?? []),
            'latestResearch' => $latestResearch !== [] ? $latestResearch : ($modelContent['latestResearch'] ?? []),
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
            url: $this->url($locale, "/facilities/{$slug}"),
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
            'url' => $this->url($locale, "/facilities/{$slug}"),
        ];
    }

    /** @return Collection<int, FacultyNavigationItemDTO> */
    private function navigation(Faculty $faculty, string $locale, ?string $active): Collection
    {
        $slug = $this->publicSlug($faculty);
        $labels = [
            'overview' => $locale === 'ar' ? 'لمحة عامة' : 'Overview',
            'departments' => $locale === 'ar' ? 'الأقسام' : 'Departments',
            'study-plan' => $locale === 'ar' ? 'الخطة الدراسية' : 'Study Plan',
            'study-plan-course' => $locale === 'ar' ? 'محاضرات المقرر' : 'Course Lessons',
            'labs' => $locale === 'ar' ? 'المخابر' : 'Laboratories',
            'projects' => $locale === 'ar' ? 'المشاريع' : 'Projects',
            'alumni' => $locale === 'ar' ? 'الخريجون' : 'Alumni',
            'valedictorians' => $locale === 'ar' ? 'قائمة الشرف' : 'Honor List',
            'training' => $locale === 'ar' ? 'التدريب' : 'Training',
            'research' => $locale === 'ar' ? 'البحث العلمي' : 'Research',
        ];

        return $faculty->pages
            ->filter(fn (FacultyPage $page): bool => in_array($page->slug, self::SUBPAGE_SLUGS, true) && $page->slug !== 'study-plan-course')
            ->filter(fn (FacultyPage $page): bool => $this->isAvailableSubpage($faculty, (string) $page->slug))
            ->sortBy('sort_order')
            ->map(fn (FacultyPage $page): FacultyNavigationItemDTO => new FacultyNavigationItemDTO(
                slug: (string) $page->slug,
                label: $labels[$page->slug] ?? (string) $page->slug,
                url: $page->slug === 'overview' ? $this->url($locale, "/facilities/{$slug}/overview") : $this->url($locale, "/facilities/{$slug}/{$page->slug}"),
                isActive: $active === $page->slug,
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
            'study-plan', 'study-plan-course' => [],
            default => [],
        };
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
                'code' => is_array($payload) ? ($payload['code'] ?? null) : null,
                'title' => (string) $translation->name,
                'summary' => (string) ($translation->description ?? ''),
                'degrees' => is_array($payload) ? ($payload['degrees'.$localeSuffix] ?? null) : null,
                'tags' => is_array($payload) && is_array($payload['tags'] ?? null) ? $payload['tags'] : [],
                'studyPlanDepartmentId' => $explicitDepartmentId,
            ];
        })->values()->all();
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
        $frontendProjects = $this->frontendProjectItems($this->publicSlug($faculty), $locale);

        if ($frontendProjects !== []) {
            return collect($frontendProjects)
                ->map(fn (array $project): array => [
                    ...$project,
                    'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) ($project['slug'] ?? '')),
                ])
                ->values()
                ->all();
        }

        return $faculty->studentProjects->map(function (FacultyStudentProject $project) use ($faculty, $locale): array {
            $translation = $this->projectTranslation($project, $locale);

            return [
                'slug' => (string) $project->slug,
                'title' => (string) $translation->title,
                'summary' => (string) ($translation->summary ?? ''),
                'tag' => $translation->tag,
                'team' => $translation->team,
                'supervisor' => $translation->supervisor,
                'image' => $project->image,
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
            seoDescription: (string) ($project['summary'] ?? ''),
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

        return $projects
            ->map(fn (array $project): array => [
                ...$this->projectDetailPayload($faculty, $project, $locale),
                'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) ($project['slug'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $project @return array<string, mixed> */
    private function projectDetailPayload(Faculty $faculty, array $project, string $locale): array
    {
        $frontendProject = $this->frontendProjectDetail($this->publicSlug($faculty), (string) ($project['slug'] ?? ''), $locale);

        if ($frontendProject !== []) {
            $payload = [
                ...$frontendProject,
                ...$project,
            ];
            $payload['year'] = (string) ($project['academicYear'] ?? $project['year'] ?? $payload['year'] ?? '');

            return [
                ...$payload,
                'detailRoute' => $this->projectDetailRoute($faculty, $locale, (string) ($project['slug'] ?? '')),
            ];
        }

        $facultyTitle = (string) $this->facultyTranslation($faculty, $locale)->name;

        return [
            ...$project,
            'facultyTitle' => $facultyTitle,
            'facultySlug' => $this->publicSlug($faculty),
            'facultyColor' => $faculty->accent_color ?: '#202759',
            'year' => (string) ($project['year'] ?? $project['academicYear'] ?? ''),
            'createdBy' => (string) ($project['createdBy'] ?? ''),
            'longDescription' => $this->stringList($project['longDescription'] ?? []),
            'gallery' => $this->stringList($project['gallery'] ?? []),
            'technologies' => $this->stringList($project['technologies'] ?? []),
            'teamMembers' => $this->arrayList($project['teamMembers'] ?? []),
        ];
    }

    /** @return array<string, mixed> */
    private function frontendProjectDetail(string $facultySlug, string $projectSlug, string $locale): array
    {
        $project = collect($this->frontendProjectData()[$facultySlug] ?? [])->firstWhere('id', $projectSlug);

        if (! is_array($project)) {
            return [];
        }

        return $this->localizedFrontendProject($project, $locale);
    }

    /** @return array<int, array<string, mixed>> */
    private function frontendProjectItems(string $facultySlug, string $locale): array
    {
        return collect($this->frontendProjectData()[$facultySlug] ?? [])
            ->map(fn (array $project): array => $this->localizedFrontendProject($project, $locale))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $project @return array<string, mixed> */
    private function localizedFrontendProject(array $project, string $locale): array
    {
        $isAr = $locale === 'ar';
        $teamMembers = collect($project['teamMembers'] ?? [])->map(fn (array $member): array => [
            'name' => (string) ($isAr ? $member['nameAr'] : $member['nameEn']),
            'role' => (string) ($isAr ? $member['roleAr'] : $member['roleEn']),
        ])->values()->all();

        return [
            'slug' => (string) ($project['id'] ?? ''),
            'title' => (string) ($isAr ? $project['titleAr'] : $project['titleEn']),
            'summary' => (string) ($isAr ? $project['summaryAr'] : $project['summaryEn']),
            'tag' => (string) ($isAr ? $project['tagAr'] : $project['tagEn']),
            'team' => (string) ($isAr ? $project['createdByAr'] : $project['createdByEn']),
            'supervisor' => (string) ($isAr ? $project['supervisorAr'] : $project['supervisorEn']),
            'image' => $project['image'],
            'facultyTitle' => (string) ($isAr ? $project['facultyAr'] : $project['facultyEn']),
            'facultySlug' => (string) $project['facultySlug'],
            'facultyColor' => (string) $project['facultyColor'],
            'year' => (string) $project['year'],
            'createdBy' => (string) ($isAr ? $project['createdByAr'] : $project['createdByEn']),
            'createdById' => (string) ($project['createdById'] ?? ''),
            'status' => (string) ($isAr ? ($project['statusAr'] ?? $project['status'] ?? 'مكتمل') : ($project['statusEn'] ?? $project['status'] ?? 'Completed')),
            'longDescription' => $this->stringList($isAr ? ($project['longDescAr'] ?? []) : ($project['longDescEn'] ?? [])),
            'gallery' => $project['gallery'],
            'technologies' => $project['technologies'],
            'teamMembers' => $teamMembers,
            'supervisorId' => (string) ($project['supervisorId'] ?? ''),
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function frontendProjectData(): array
    {
        static $data = null;

        if (is_array($data)) {
            return $data;
        }

        $path = resource_path('data/frontend-faculty-projects.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $data = is_array($decoded) ? $decoded : [];

        return $data;
    }

    private function projectDetailRoute(Faculty $faculty, string $locale, string $projectSlug): string
    {
        return $this->url($locale, '/facilities/'.$this->publicSlug($faculty).'/projects/'.$projectSlug);
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
                    'semester' => null,
                    'semesterKey' => null,
                    'academicPhase' => $locale === 'ar' ? 'خريج' : 'Graduate',
                    'image' => $alumni->photoMedia === null
                        ? MediaUrlResolver::resolveLegacy($legacyPhoto)
                        : MediaUrlResolver::resolve($alumni->photoMedia->path, $alumni->photoMedia->disk),
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
                    'semester' => null,
                    'semesterKey' => null,
                    'faculty' => $this->facultyTranslation($faculty, $locale)->name,
                    'image' => $student->photoMedia === null
                        ? MediaUrlResolver::resolveLegacy($legacyPhoto)
                        : MediaUrlResolver::resolve($student->photoMedia->path, $student->photoMedia->disk),
                    'isMemorial' => $this->isMemorialHonorStudent($metadata),
                ];
            })->values()->all();
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

                return $filters['academic_phase'] === '' || (string) ($item['academicPhase'] ?? '') === (string) $filters['academic_phase'];
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
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0))
                ?: strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $items;
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
        $course['lessons'] = $lessons->all();
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
            'research' => $locale === 'ar' ? 'أحدث الأبحاث' : 'Latest Research',
        ];

        return $titles[$subpageSlug] ?? $subpageSlug;
    }

    /** @return array<int, array<string, mixed>> */
    private function latestResearch(Faculty $faculty, string $locale): array
    {
        $researchPage = $this->researchPageService->facultyPublications($this->publicSlug($faculty), $locale);

        return collect($researchPage->data['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && preg_match('/^[A-Za-z0-9-]+$/', (string) ($item['slug'] ?? '')) === 1
                && trim((string) ($item['title'] ?? '')) !== '')
            ->take(3)
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
        $facultySlug = $this->publicSlug($faculty);
        $dean = $this->aboutPageService->getLeadershipProfiles($locale)
            ->first(fn (PersonDTO $person): bool => $person->category === 'dean'
                && $this->canonicalFacultySlug((string) $person->facultySlug) === $facultySlug);

        if (! $dean instanceof PersonDTO) {
            return null;
        }

        $profile = $this->profilePageService->getProfile($locale, 'person', $dean->slug);
        $deanPayload = is_array($pageData['payload']['dean'] ?? null) ? $pageData['payload']['dean'] : [];
        $displayName = (string) ($deanPayload['name'] ?? $deanPayload['name'.ucfirst($locale)] ?? '');

        if (! $profile instanceof ProfilePageDTO || $displayName === '' || ! $this->samePersonName($displayName, $profile->name)) {
            return null;
        }

        return $profile;
    }

    private function samePersonName(string $first, string $second): bool
    {
        $normalize = static function (string $name): string {
            $name = mb_strtolower(trim($name));
            $name = str_replace(['الأستاذ الدكتور', 'الأستاذة الدكتورة', 'الدكتور', 'الدكتورة', 'أ.د.', 'د.', 'professor', 'prof.', 'dr.'], '', $name);

            return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
        };

        return $normalize($first) !== '' && $normalize($first) === $normalize($second);
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
        $cardService = app(\App\Contracts\Page\FacultySubpageCardServiceInterface::class);
        $visibleSlugs = $cardService->getVisibleSubpageSlugs($facultySlug);

        if ($visibleSlugs === []) {
            if ($cardService->hasAnyCards($facultySlug)) {
                return false;
            }

            return $subpageSlug !== 'training' || $this->publicSlug($faculty) === 'pharmacy';
        }

        return in_array($subpageSlug, $visibleSlugs, true);
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
