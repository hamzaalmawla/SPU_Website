<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;
use App\Enums\PublicationStatus;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\MediaUrlResolver;
use Illuminate\Support\Str;

final class ResearchPageService implements ResearchPageServiceInterface
{
    private const PUBLICATIONS_PER_PAGE = 6;

    private const PROJECTS_PER_PAGE = 6;

    private const RESEARCHERS_PER_PAGE = 10;

    /** @var array<string, mixed>|null */
    private ?array $content = null;

    /** @var array<string, mixed>|null */
    private ?array $detailContent = null;

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
    ) {}

    public function landing(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.index', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->sanitizeLandingContent($cmsContent);

            return $this->pageDto($locale, 'landing', $cmsContent, '/research', $cmsContent['hero'] ?? []);
        }

        $data = $this->localized($this->content(), $locale);
        $data = is_array($data) ? $this->sanitizeLandingContent($data) : [];

        return $this->pageDto($locale, 'landing', $data, '/research', $data['hero'] ?? []);
    }

    public function repository(string $locale, array $filters = []): ResearchPageDTO
    {
        $publications = $this->publications($locale, $filters);

        return new ResearchPageDTO(
            locale: $publications->locale,
            direction: $publications->direction,
            type: 'repository',
            data: $publications->data,
            seoTitle: $publications->seoTitle,
            seoDescription: $publications->seoDescription,
            seoImage: $publications->seoImage,
            path: '/'.$locale.'/research/repository',
        );
    }

    public function publications(string $locale, array $filters = []): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.publications', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->withDatabasePublications($cmsContent, $locale);
            $cmsContent = $this->withFilteredPublications($cmsContent, $filters);

            return $this->pageDto($locale, 'publications', $cmsContent, '/research/publications', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['publications'] ?? [];
        $localized = $this->localized($data, $locale);
        $localized = is_array($localized) ? $this->withDatabasePublications($localized, $locale) : [];
        $localized = $this->withFilteredPublications($localized, $filters);

        return $this->pageDto($locale, 'publications', $localized, '/research/publications', $data['hero'] ?? []);
    }

    public function facultyPublications(string $facultySlug, string $locale): ResearchPageDTO
    {
        $page = $this->publications($locale);
        $data = $page->data;
        $canonicalFacultySlug = $this->canonicalFacultySlug($facultySlug);
        $items = $this->publicPublicationItems($locale);
        $data['items'] = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->canonicalFacultySlug((string) ($item['facultySlug'] ?? '')) === $canonicalFacultySlug,
        ));
        $data['totalItems'] = count($data['items']);
        $data['resultCount'] = count($data['items']);
        unset($data['pagination'], $data['activeFilters']);

        return new ResearchPageDTO(
            locale: $page->locale,
            direction: $page->direction,
            type: 'faculty-publications',
            data: $data,
            seoTitle: $page->seoTitle,
            seoDescription: $page->seoDescription,
            seoImage: $page->seoImage,
            path: '/facilities/'.$canonicalFacultySlug.'/research',
        );
    }

    public function publication(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $cmsPage = $this->cmsPublication($locale, $slug);

        if ($cmsPage instanceof ResearchDetailPageDTO) {
            return $cmsPage;
        }

        $databasePage = $this->databasePublication($locale, $slug);

        if ($databasePage instanceof ResearchDetailPageDTO) {
            return $databasePage;
        }

        $items = $this->detailContent()['publications'] ?? [];
        $item = $this->firstBySlug($items, $slug);

        if ($item === null) {
            return null;
        }

        $index = $this->indexBySlug($items, $slug);
        $previous = $items[($index - 1 + count($items)) % count($items)] ?? null;
        $next = $items[($index + 1) % count($items)] ?? null;
        $sameFaculty = array_values(array_filter($items, static fn (array $publication): bool => ($publication['slug'] ?? null) !== $slug && ($publication['facultyEn'] ?? null) === ($item['facultyEn'] ?? null)
        ));
        $fallback = array_values(array_filter($items, static fn (array $publication): bool => ($publication['slug'] ?? null) !== $slug));
        $related = array_slice($this->uniqueBySlug([...$sameFaculty, ...$fallback]), 0, 3);

        return $this->detailDto($locale, 'publication', $slug, $item, [
            'item' => $item,
            'labels' => $this->detailContent()['labels'] ?? [],
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'themes' => $this->content()['themes']['items'] ?? [],
        ], '/research/publications/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function getEditablePayload(string $targetKey): array
    {
        if (! in_array($targetKey, ['research.index', 'research.publications', 'research.centers', 'research.projects', 'research.themes', 'research.experts', 'research.conferences', 'research.library', 'research.office', 'research.policies'], true)) {
            throw new \InvalidArgumentException('Unsupported research target.');
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

        return [
            'translations' => [
                'ar' => match ($targetKey) {
                    'research.index' => $this->editableLandingContent('ar'),
                    'research.publications' => $this->editablePublicationsContent('ar'),
                    'research.centers' => $this->editableCentersContent('ar'),
                    'research.projects' => $this->editableProjectsContent('ar'),
                    'research.themes' => $this->editableThemesContent('ar'),
                    'research.experts' => $this->editableExpertsContent('ar'),
                    'research.conferences' => $this->editableTargetContent('conferences', 'ar'),
                    'research.library' => $this->editableTargetContent('library', 'ar'),
                    'research.office' => $this->editableTargetContent('office', 'ar'),
                    'research.policies' => $this->editableTargetContent('policies', 'ar'),
                },
                'en' => match ($targetKey) {
                    'research.index' => $this->editableLandingContent('en'),
                    'research.publications' => $this->editablePublicationsContent('en'),
                    'research.centers' => $this->editableCentersContent('en'),
                    'research.projects' => $this->editableProjectsContent('en'),
                    'research.themes' => $this->editableThemesContent('en'),
                    'research.experts' => $this->editableExpertsContent('en'),
                    'research.conferences' => $this->editableTargetContent('conferences', 'en'),
                    'research.library' => $this->editableTargetContent('library', 'en'),
                    'research.office' => $this->editableTargetContent('office', 'en'),
                    'research.policies' => $this->editableTargetContent('policies', 'en'),
                },
            ],
        ];
    }

    public function buildPreviewLanding(string $locale, array $content): ResearchPageDTO
    {
        $content = $this->sanitizeLandingContent($content);

        return $this->pageDto($locale, 'landing', $content, '/research', $content['hero'] ?? []);
    }

    public function buildPreviewPublications(string $locale, array $content): ResearchPageDTO
    {
        return $this->pageDto($locale, 'publications', $content, '/research/publications', $content['hero'] ?? []);
    }

    public function buildPreviewExperts(string $locale, array $content): ResearchPageDTO
    {
        $content = $this->normalizedCmsExpertsContent($content);

        return $this->pageDto($locale, 'expert-finder', $content, '/research/expert-finder', $content['hero'] ?? []);
    }

    public function buildPreviewTarget(string $targetKey, string $locale, array $content): ResearchPageDTO
    {
        [$type, $path] = match ($targetKey) {
            'research.conferences' => ['conferences', '/research/conferences'],
            'research.centers' => ['centers', '/research/centers'],
            'research.themes' => ['themes', '/research/themes'],
            'research.library' => ['library', '/research/library'],
            'research.office' => ['office', '/research/office'],
            'research.policies' => ['policies', '/research/policies'],
            default => throw new \InvalidArgumentException('Unsupported research preview target.'),
        };

        $content = match ($targetKey) {
            'research.conferences' => $this->normalizedConferencesContent($content),
            'research.policies' => $this->normalizedPoliciesContent($content),
            default => $content,
        };

        return $this->pageDto($locale, $type, $content, $path, $content['hero'] ?? []);
    }

    public function centers(string $locale): ResearchPageDTO
    {
        $data = $this->publishedLocalizedPayload('research.centers', $locale);

        if (! is_array($data)) {
            $source = $this->content()['centers'] ?? [];
            $data = $this->localized($source, $locale);
        }

        $data = is_array($data) ? $this->normalizedCentersContent($data) : [];

        return $this->pageDto($locale, 'centers', $data, '/research/centers', is_array($data['hero'] ?? null) ? $data['hero'] : []);
    }

    public function center(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        return $this->centerFromContent($locale, $this->centers($locale)->data, $slug);
    }

    public function buildPreviewCenter(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        return $this->centerFromContent($locale, $this->normalizedCentersContent($content), $slug);
    }

    /** @param array<string, mixed> $content */
    private function centerFromContent(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        $item = $this->firstBySlug($this->arrayList($content['items'] ?? []), $slug);

        if ($item === null) {
            return null;
        }

        $facultySlug = $this->facultySlugFromCenter($item);
        $publicationSlugs = $this->scalarList($item['publicationSlugs'] ?? []);
        $projectSlugs = $this->scalarList($item['projectSlugs'] ?? []);
        $researcherSlugs = $this->scalarList($item['researcherSlugs'] ?? []);
        $publications = array_values(array_filter(
            $this->publicPublicationItems($locale),
            fn (array $publication): bool => $publicationSlugs !== []
                ? in_array((string) ($publication['slug'] ?? ''), $publicationSlugs, true)
                : $this->canonicalFacultySlug((string) ($publication['facultySlug'] ?? '')) === $facultySlug,
        ));
        $projects = array_values(array_filter(
            $this->publicProjectItems($locale),
            fn (array $project): bool => $projectSlugs !== []
                ? in_array((string) ($project['slug'] ?? ''), $projectSlugs, true)
                : $this->canonicalFacultySlug((string) ($project['facultySlug'] ?? '')) === $facultySlug,
        ));
        $researchers = array_values(array_filter(
            $this->publicResearcherItems($locale),
            fn (array $researcher): bool => $researcherSlugs !== []
                ? in_array((string) ($researcher['slug'] ?? ''), $researcherSlugs, true)
                : $this->canonicalFacultySlug((string) ($researcher['facultySlug'] ?? $researcher['facultyId'] ?? '')) === $facultySlug,
        ));

        return $this->detailDto($locale, 'center', $slug, $item, [
            'item' => $item,
            'publications' => array_slice($publications, 0, 4),
            'projects' => array_slice($projects, 0, 3),
            'faculty' => $researchers,
        ], '/research/centers/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function projects(string $locale, array $filters = []): ResearchPageDTO
    {
        $localized = $this->publishedLocalizedPayload('research.projects', $locale);

        if (! is_array($localized)) {
            $localized = $this->localized($this->content()['projects'] ?? [], $locale);
        }

        $localized = is_array($localized) ? $this->normalizedProjectsContent($localized) : [];
        $localized = $this->withFilteredProjects($localized, $filters);

        return $this->pageDto($locale, 'projects', $localized, '/research/projects', is_array($localized['hero'] ?? null) ? $localized['hero'] : []);
    }

    public function project(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        return $this->projectFromContent($locale, ['items' => $this->publicProjectItems($locale)], $slug);
    }

    public function buildPreviewProjects(string $locale, array $content, array $filters = []): ResearchPageDTO
    {
        $content = $this->withFilteredProjects($this->normalizedProjectsContent($content), $filters);

        return $this->pageDto($locale, 'projects', $content, '/research/projects', $content['hero'] ?? []);
    }

    public function buildPreviewProject(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        return $this->projectFromContent($locale, $this->normalizedProjectsContent($content), $slug);
    }

    /** @param array<string, mixed> $content */
    private function projectFromContent(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        $item = $this->firstBySlug($this->arrayList($content['items'] ?? []), $slug);

        if ($item === null) {
            return null;
        }

        return $this->detailDto($locale, 'project', $slug, $item, ['item' => $item], '/research/projects/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function themes(string $locale): ResearchPageDTO
    {
        $data = $this->publishedLocalizedPayload('research.themes', $locale);

        if (! is_array($data)) {
            $data = $this->localized($this->content()['themes'] ?? [], $locale);
        }

        $data = is_array($data) ? $this->normalizedThemesContent($data) : [];

        return $this->pageDto($locale, 'themes', $data, '/research/themes', is_array($data['hero'] ?? null) ? $data['hero'] : []);
    }

    public function theme(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        return $this->themeFromContent($locale, $this->themes($locale)->data, $slug);
    }

    public function buildPreviewTheme(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        return $this->themeFromContent($locale, $this->normalizedThemesContent($content), $slug);
    }

    /** @param array<string, mixed> $content */
    private function themeFromContent(string $locale, array $content, string $slug): ?ResearchDetailPageDTO
    {
        $item = $this->firstBySlug($this->arrayList($content['items'] ?? []), $slug);

        if ($item === null) {
            return null;
        }

        $publications = array_values(array_filter(
            $this->publicPublicationItems($locale),
            fn (array $publication): bool => in_array($slug, $this->scalarList($publication['themes'] ?? []), true),
        ));
        $projects = array_values(array_filter(
            $this->publicProjectItems($locale),
            static fn (array $project): bool => ($project['themeSlug'] ?? '') === $slug,
        ));
        $item['publicationCount'] = count($publications);
        $item['projectCount'] = count($projects);

        return $this->detailDto($locale, 'theme', $slug, $item, [
            'item' => $item,
            'publications' => $publications,
            'projects' => $projects,
        ], '/research/themes/'.$slug, '/images/uni-main-place.JPG');
    }

    public function researchers(string $locale, array $filters = []): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.experts', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedCmsExpertsContent($cmsContent);

            return $this->pageDto($locale, 'researchers', $this->withFilteredResearchers($cmsContent, $filters), '/research/researchers', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['researchers'] ?? [];

        $localized = $this->localized($data, $locale);

        return $this->pageDto($locale, 'researchers', is_array($localized) ? $this->withFilteredResearchers($localized, $filters) : [], '/research/researchers', $data['hero'] ?? []);
    }

    public function researcher(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $cmsPage = $this->cmsResearcher($locale, $slug);

        if ($cmsPage instanceof ResearchDetailPageDTO) {
            return $cmsPage;
        }

        $item = $this->researcherSourceItem($slug);

        if ($item === null) {
            return null;
        }

        $publications = $this->researcherPublications($item);
        $profile = $this->researcherProfile($item, $publications);

        return $this->detailDto($locale, 'researcher', $slug, $item, [
            'item' => $item,
            'profile' => $profile,
            'publications' => $publications,
        ], '/research/researchers/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function expertFinder(string $locale, array $filters = []): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.experts', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedCmsExpertsContent($cmsContent);

            return $this->pageDto($locale, 'expert-finder', $this->withFilteredResearchers($cmsContent, $filters, false), '/research/expert-finder', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['expertFinder'] ?? [];
        $data['items'] = $data['researchers'] ?? [];

        $localized = $this->localized($data, $locale);

        return $this->pageDto($locale, 'expert-finder', is_array($localized) ? $this->withFilteredResearchers($localized, $filters, false) : [], '/research/expert-finder', $data['hero'] ?? []);
    }

    public function conferences(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.conferences', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedConferencesContent($cmsContent);

            return $this->pageDto($locale, 'conferences', $cmsContent, '/research/conferences', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['conferences'] ?? [];
        $data = $this->localized($data, $locale);
        $data = is_array($data) ? $this->normalizedConferencesContent($data) : [];

        return $this->pageDto($locale, 'conferences', $data, '/research/conferences', $data['hero'] ?? []);
    }

    public function conferenceRegistration(string $locale, ?string $eventId): ResearchPageDTO
    {
        $conferences = $this->conferences($locale);
        $data = $conferences->data;
        $events = [...$this->arrayList($data['upcoming'] ?? []), ...$this->arrayList($data['past'] ?? [])];
        $registerEvent = null;

        foreach ($events as $event) {
            if (($event['id'] ?? null) === $eventId) {
                $registerEvent = $event;
                break;
            }
        }

        if (is_array($registerEvent)) {
            $registerEvent['formId'] = (string) ($registerEvent['formId'] ?? (($registerEvent['id'] ?? '') === 'conf-002' ? 'symposium-registration' : 'conference-registration'));
        }

        $data['registerEvent'] = $registerEvent;

        return new ResearchPageDTO(
            locale: $locale,
            direction: $conferences->direction,
            type: 'conference-registration',
            data: $data,
            seoTitle: (is_array($registerEvent) ? (string) ($registerEvent['title'] ?? '') : ($locale === 'ar' ? 'التسجيل' : 'Register')).' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'Syrian Private University'),
            seoDescription: is_array($registerEvent) ? (string) ($registerEvent['description'] ?? '') : ($locale === 'ar' ? 'التسجيل في فعاليات البحث العلمي.' : 'Register for SPU research events.'),
            seoImage: is_array($registerEvent) ? (string) ($registerEvent['image'] ?? '/images/uni-main-place.JPG') : '/images/uni-main-place.JPG',
            path: '/'.$locale.'/research/conferences/register',
        );
    }

    public function library(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.library', $locale);

        if (is_array($cmsContent)) {
            return $this->pageDto($locale, 'library', $cmsContent, '/research/library', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['library'] ?? [];

        return $this->pageDto($locale, 'library', $this->localized($data, $locale), '/research/library', $data['hero'] ?? []);
    }

    public function office(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.office', $locale);

        if (is_array($cmsContent)) {
            return $this->pageDto($locale, 'office', $cmsContent, '/research/office', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['office'] ?? [];

        return $this->pageDto($locale, 'office', $this->localized($data, $locale), '/research/office', $data['hero'] ?? []);
    }

    public function policies(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.policies', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedPoliciesContent($cmsContent);

            return $this->pageDto($locale, 'policies', $cmsContent, '/research/policies', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['policies'] ?? [];
        $data = $this->localized($data, $locale);
        $data = is_array($data) ? $this->normalizedPoliciesContent($data) : [];

        return $this->pageDto($locale, 'policies', $data, '/research/policies', $data['hero'] ?? []);
    }

    public function publicationSlugForLegacyId(string $id): ?string
    {
        if (ctype_digit($id)) {
            $sourceId = (int) $id;
            $targetId = MigrationLog::query()
                ->where('module', 'research')
                ->where('source_table', 'jx_member_categories')
                ->where('source_id', $sourceId)
                ->where('target_table', 'research_publications')
                ->where('status', 'success')
                ->value('target_id');

            if (is_numeric($targetId)) {
                $publication = ResearchPublication::query()
                    ->enabled()
                    ->with('translations')
                    ->find((int) $targetId);

                if ($publication instanceof ResearchPublication) {
                    return $this->databasePublicationSlug($publication, $sourceId);
                }
            }
        }

        foreach ($this->detailContent()['publications'] ?? [] as $publication) {
            if (($publication['id'] ?? null) === $id) {
                return is_string($publication['slug'] ?? null) ? $publication['slug'] : null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        if ($this->content === null) {
            $this->content = $this->readJson(resource_path('data/research-content.json'));
        }

        return $this->content;
    }

    /** @return array<string, mixed> */
    private function detailContent(): array
    {
        if ($this->detailContent === null) {
            $this->detailContent = $this->readJson(resource_path('data/research-detail-content.json'));
        }

        return $this->detailContent;
    }

    /** @return array<string, mixed>|null */
    private function publishedLocalizedPayload(string $targetKey, string $locale): ?array
    {
        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);

        return is_array($published['translations'][$locale] ?? null)
            ? $published['translations'][$locale]
            : null;
    }

    /** @return array<string, mixed> */
    private function editablePublicationsContent(string $locale): array
    {
        return $this->localized($this->publicationCmsSource(), $locale);
    }

    /** @return array<string, mixed> */
    private function editableCentersContent(string $locale): array
    {
        $content = $this->localized($this->content()['centers'] ?? [], $locale);

        return is_array($content) ? $this->normalizedCentersContent($content) : [];
    }

    /** @return array<string, mixed> */
    private function editableProjectsContent(string $locale): array
    {
        $content = $this->localized($this->content()['projects'] ?? [], $locale);

        return is_array($content) ? $this->normalizedProjectsContent($content) : [];
    }

    /** @return array<string, mixed> */
    private function editableThemesContent(string $locale): array
    {
        $content = $this->localized($this->content()['themes'] ?? [], $locale);

        return is_array($content) ? $this->normalizedThemesContent($content) : [];
    }

    /** @return array<string, mixed> */
    private function editableLandingContent(string $locale): array
    {
        return $this->localized($this->landingCmsSource(), $locale);
    }

    /** @return array<string, mixed> */
    private function editableTargetContent(string $sourceKey, string $locale): array
    {
        return $this->localized($this->content()[$sourceKey] ?? [], $locale);
    }

    /** @return array<string, mixed> */
    private function landingCmsSource(): array
    {
        $content = $this->content();

        return [
            'hero' => $content['hero'] ?? [],
            'stats' => $content['stats'] ?? [],
            'featuredPublication' => $content['featuredPublication'] ?? [],
            'gateway' => $content['gateway'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function editableExpertsContent(string $locale): array
    {
        return $this->localized($this->expertCmsSource(), $locale);
    }

    /** @return array<string, mixed> */
    private function expertCmsSource(): array
    {
        $data = $this->content()['expertFinder'] ?? [];
        $researchers = [];

        foreach ($data['researchers'] ?? [] as $researcher) {
            if (! is_array($researcher)) {
                continue;
            }

            $publications = $this->researcherPublications($researcher);
            $profile = $this->researcherProfile($researcher, $publications);
            $researchers[] = array_merge($researcher, [
                'roleEn' => $researcher['titleEn'] ?? '',
                'roleAr' => $researcher['titleAr'] ?? '',
                'descriptionEn' => $researcher['bioEn'] ?? '',
                'descriptionAr' => $researcher['bioAr'] ?? '',
                'biographyEn' => $profile['biographyEn'] ?? [],
                'biographyAr' => $profile['biographyAr'] ?? [],
                'educationEn' => $profile['educationEn'] ?? [],
                'educationAr' => $profile['educationAr'] ?? [],
                'courses' => $profile['courses'] ?? [],
                'office' => $profile['office'] ?? null,
                'researchStats' => $profile['researchStats'] ?? [],
                'profilePublications' => $profile['publications'] ?? [],
            ]);
        }

        $data['researchers'] = $researchers;
        $data['items'] = $researchers;

        return $data;
    }

    /** @return array<string, mixed> */
    private function publicationCmsSource(): array
    {
        $data = $this->content()['publications'] ?? [];
        $detailBySlug = [];

        foreach ($this->detailContent()['publications'] ?? [] as $publication) {
            if (is_array($publication) && is_string($publication['slug'] ?? null)) {
                $detailBySlug[$publication['slug']] = $publication;
            }
        }

        $items = [];

        foreach ($data['items'] ?? [] as $publication) {
            if (! is_array($publication)) {
                continue;
            }

            $slug = (string) ($publication['slug'] ?? '');
            $items[] = array_merge($publication, is_array($detailBySlug[$slug] ?? null) ? $detailBySlug[$slug] : []);
        }

        $data['items'] = $items;

        return $data;
    }

    private function cmsPublication(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $content = $this->publishedLocalizedPayload('research.publications', $locale);

        if (! is_array($content)) {
            return null;
        }

        $items = array_map(fn (array $item): array => $this->sanitizePublication($item), $this->arrayList($content['items'] ?? []));
        $item = $this->firstBySlug($items, $slug);

        if ($item === null) {
            return null;
        }

        $index = $this->indexBySlug($items, $slug);
        $previous = $items[($index - 1 + count($items)) % count($items)] ?? null;
        $next = $items[($index + 1) % count($items)] ?? null;
        $sameFaculty = array_values(array_filter($items, static fn (array $publication): bool => ($publication['slug'] ?? null) !== $slug && ($publication['facultySlug'] ?? null) === ($item['facultySlug'] ?? null)
        ));
        $fallback = array_values(array_filter($items, static fn (array $publication): bool => ($publication['slug'] ?? null) !== $slug));
        $related = array_slice($this->uniqueBySlug([...$sameFaculty, ...$fallback]), 0, 3);

        return $this->detailDto($locale, 'publication', $slug, $item, [
            'item' => $item,
            'labels' => $this->detailContent()['labels'] ?? [],
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'themes' => $this->content()['themes']['items'] ?? [],
        ], '/research/publications/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    private function databasePublication(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $items = $this->databasePublicationItems($locale);
        $item = $this->firstBySlug($items, $slug);

        if ($item === null) {
            return null;
        }

        $index = $this->indexBySlug($items, $slug);
        $previous = $items[($index - 1 + count($items)) % count($items)] ?? null;
        $next = $items[($index + 1) % count($items)] ?? null;
        $related = array_slice(array_values(array_filter($items, static fn (array $publication): bool => ($publication['slug'] ?? null) !== $slug)), 0, 3);

        return $this->detailDto($locale, 'publication', $slug, $item, [
            'item' => $item,
            'labels' => $this->detailContent()['labels'] ?? [],
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'themes' => $this->content()['themes']['items'] ?? [],
        ], '/research/publications/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function withDatabasePublications(array $content, string $locale): array
    {
        $databaseItems = $this->databasePublicationItems($locale);

        if ($databaseItems === []) {
            return $content;
        }

        $existingItems = $this->arrayList($content['items'] ?? []);
        $content['items'] = $this->uniqueBySlug([...$databaseItems, ...$existingItems]);
        $content['filters'] = $this->withDatabasePublicationFilterOptions(is_array($content['filters'] ?? null) ? $content['filters'] : [], $content['items'], $locale);

        return $content;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizedPublicationFilters(array $filters): array
    {
        return [
            'q' => $this->filterValue($filters['q'] ?? null),
            'faculty' => $this->canonicalFacultySlug($this->filterValue($filters['faculty'] ?? null)),
            'type' => $this->filterValue($filters['type'] ?? null),
            'year' => $this->filterValue($filters['year'] ?? null),
            'page' => $this->filterPage($filters['page'] ?? null),
        ];
    }

    private function filterValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        return $value === 'all' ? '' : $value;
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $filters @return array<string, mixed> */
    private function withFilteredPublications(array $content, array $filters): array
    {
        $activeFilters = $this->normalizedPublicationFilters($filters);
        $items = $this->arrayList($content['items'] ?? []);
        $content['totalItems'] = count($items);
        $content['activeFilters'] = $activeFilters;

        $filtered = array_values(array_filter($items, fn (array $item): bool => $this->publicationMatchesFilters($item, $activeFilters)));
        $content = $this->withPagination($content, $filtered, $activeFilters, self::PUBLICATIONS_PER_PAGE);

        return $content;
    }

    /** @param array<string, mixed> $item @param array<string, string> $filters */
    private function publicationMatchesFilters(array $item, array $filters): bool
    {
        if ($filters['faculty'] !== '' && $filters['faculty'] !== $this->canonicalFacultySlug((string) ($item['facultySlug'] ?? ''))) {
            return false;
        }

        if ($filters['type'] !== '' && $filters['type'] !== (string) ($item['typeSlug'] ?? '')) {
            return false;
        }

        if ($filters['year'] !== '' && $filters['year'] !== (string) ($item['year'] ?? '')) {
            return false;
        }

        if ($filters['q'] === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $item['title'] ?? null,
            $item['summary'] ?? null,
            $item['lead'] ?? null,
            $item['category'] ?? null,
            $item['type'] ?? null,
            $item['faculty'] ?? null,
            $item['author'] ?? null,
            $item['publisher'] ?? null,
            $item['year'] ?? null,
            implode(' ', array_filter($this->scalarList($item['keywords'] ?? []))),
        ], static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')));

        return str_contains($haystack, strtolower($filters['q']));
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $filters @return array<string, mixed> */
    private function withFilteredProjects(array $content, array $filters): array
    {
        $activeFilters = [
            'q' => $this->filterValue($filters['q'] ?? null),
            'status' => $this->filterValue($filters['status'] ?? null),
            'faculty' => $this->canonicalFacultySlug($this->filterValue($filters['faculty'] ?? null)),
            'theme' => $this->filterValue($filters['theme'] ?? null),
            'page' => $this->filterPage($filters['page'] ?? null),
        ];
        $items = $this->arrayList($content['items'] ?? []);
        $filtered = array_values(array_filter($items, function (array $item) use ($activeFilters): bool {
            if ($activeFilters['status'] !== '' && $activeFilters['status'] !== (string) ($item['status'] ?? '')) {
                return false;
            }

            if ($activeFilters['faculty'] !== '' && $activeFilters['faculty'] !== $this->canonicalFacultySlug((string) ($item['facultySlug'] ?? ''))) {
                return false;
            }

            if ($activeFilters['theme'] !== '' && $activeFilters['theme'] !== (string) ($item['themeSlug'] ?? '')) {
                return false;
            }

            if ($activeFilters['q'] === '') {
                return true;
            }

            return $this->containsSearchTerm([
                $item['title'] ?? null,
                $item['summary'] ?? null,
                $item['faculty'] ?? null,
                $item['theme'] ?? null,
            ], $activeFilters['q']);
        }));

        return $this->withPagination($content, $filtered, $activeFilters, self::PROJECTS_PER_PAGE);
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $filters @return array<string, mixed> */
    private function withFilteredResearchers(array $content, array $filters, bool $withExpertise = true): array
    {
        $activeFilters = [
            'q' => $this->filterValue($filters['q'] ?? null),
            'faculty' => $this->canonicalFacultySlug($this->filterValue($filters['faculty'] ?? null)),
            'expertise' => $withExpertise ? $this->filterValue($filters['expertise'] ?? null) : '',
            'page' => $this->filterPage($filters['page'] ?? null),
        ];
        $items = $this->arrayList($content['items'] ?? $content['researchers'] ?? []);
        $filtered = array_values(array_filter($items, function (array $item) use ($activeFilters): bool {
            $facultySlug = (string) ($item['facultySlug'] ?? $item['facultyId'] ?? '');
            if ($activeFilters['faculty'] !== '' && $activeFilters['faculty'] !== $this->canonicalFacultySlug($facultySlug)) {
                return false;
            }

            $expertiseSlugs = $this->scalarList($item['expertiseSlugs'] ?? []);
            if ($activeFilters['expertise'] !== '' && ! in_array($activeFilters['expertise'], $expertiseSlugs, true)) {
                return false;
            }

            if ($activeFilters['q'] === '') {
                return true;
            }

            return $this->containsSearchTerm([
                $item['name'] ?? null,
                $item['faculty'] ?? null,
                $item['department'] ?? null,
                ...$this->scalarList($item['expertise'] ?? []),
            ], $activeFilters['q']);
        }));

        $content['items'] = $items;

        return $this->withPagination($content, $filtered, $activeFilters, self::RESEARCHERS_PER_PAGE);
    }

    /** @param array<int, mixed> $values */
    private function containsSearchTerm(array $values, string $term): bool
    {
        $haystack = mb_strtolower(implode(' ', array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $values,
        )));

        return str_contains($haystack, mb_strtolower($term));
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $activeFilters
     * @return array<string, mixed>
     */
    private function withPagination(array $content, array $items, array $activeFilters, int $perPage): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min((int) $activeFilters['page'], $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $activeFilters['page'] = $currentPage;
        $content['items'] = array_slice($items, $offset, $perPage);
        $content['resultCount'] = $total;
        $content['activeFilters'] = $activeFilters;
        $content['pagination'] = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_items' => $total,
            'total_pages' => $totalPages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + $perPage),
        ];

        return $content;
    }

    private function filterPage(mixed $value): int
    {
        return is_scalar($value) ? max(1, min(500, (int) $value)) : 1;
    }

    /** @return list<string> */
    private function scalarList(mixed $items): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_scalar($item) && trim((string) $item) !== '')
        ));
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    private function withDatabasePublicationFilterOptions(array $filters, array $items, string $locale): array
    {
        $years = [];
        $types = [];
        $faculties = [];

        foreach ($this->arrayList($filters['years'] ?? []) as $option) {
            $value = (string) ($option['value'] ?? '');

            if ($value !== '') {
                $years[$value] = $option;
            }
        }

        foreach ($this->arrayList($filters['publicationTypes'] ?? []) as $option) {
            $value = (string) ($option['value'] ?? '');

            if ($value !== '') {
                $types[$value] = $option;
            }
        }

        foreach ($this->arrayList($filters['faculties'] ?? []) as $option) {
            $value = (string) ($option['value'] ?? '');

            if ($value !== '') {
                $faculties[$value] = $option;
            }
        }

        foreach ($items as $item) {
            $year = (string) ($item['year'] ?? '');

            if ($year !== '' && ! isset($years[$year])) {
                $years[$year] = ['value' => $year, 'label' => $year];
            }

            $typeSlug = (string) ($item['typeSlug'] ?? '');

            if ($typeSlug !== '' && ! isset($types[$typeSlug])) {
                $types[$typeSlug] = ['value' => $typeSlug, 'label' => (string) ($item['type'] ?? $typeSlug)];
            }

            $facultySlug = (string) ($item['facultySlug'] ?? '');

            if ($facultySlug !== '' && ! isset($faculties[$facultySlug])) {
                $faculties[$facultySlug] = ['value' => $facultySlug, 'label' => (string) ($item['faculty'] ?? $facultySlug)];
            }
        }

        if ($years !== []) {
            krsort($years);
            $filters['years'] = array_values(['' => ['value' => '', 'label' => $locale === 'ar' ? 'كل السنوات' : 'All Years']] + $years);
        }

        if ($types !== []) {
            ksort($types);
            $filters['publicationTypes'] = array_values(['' => ['value' => '', 'label' => $locale === 'ar' ? 'كل الأنواع' : 'All Types']] + $types);
        }

        if ($faculties !== []) {
            ksort($faculties);
            $filters['faculties'] = array_values(['' => ['value' => '', 'label' => $locale === 'ar' ? 'كل الكليات' : 'All Faculties']] + $faculties);
        }

        return $filters;
    }

    /** @return array<int, array<string, mixed>> */
    private function databasePublicationItems(string $locale): array
    {
        $publications = ResearchPublication::query()
            ->enabled()
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', today())
            ->where(function ($query): void {
                $query->whereNull('faculty_member_id')
                    ->orWhereHas('facultyMember', function ($memberQuery): void {
                        $memberQuery
                            ->where('is_enabled', true)
                            ->where('publication_status', PublicationStatus::Published->value)
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            })
            ->with(['translations', 'facultyMember.faculty.translations', 'fileMedia', 'files.mediaAsset'])
            ->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        if ($publications->isEmpty()) {
            return [];
        }

        $sourceIds = MigrationLog::query()
            ->where('module', 'research')
            ->where('source_table', 'jx_member_categories')
            ->where('target_table', 'research_publications')
            ->where('status', 'success')
            ->whereIn('target_id', $publications->pluck('id')->all())
            ->pluck('source_id', 'target_id')
            ->map(fn (mixed $sourceId): int => (int) $sourceId)
            ->all();

        return $publications
            ->map(fn (ResearchPublication $publication): array => $this->databasePublicationItem($publication, $locale, $sourceIds[(int) $publication->getKey()] ?? null))
            ->values()
            ->all();
    }

    private function databasePublicationItem(ResearchPublication $publication, string $locale, ?int $sourceId): array
    {
        $translations = $publication->translations->keyBy('locale');
        $translation = $translations->get($locale) ?? $translations->get('en') ?? $translations->get('ar');
        $fallbackTitle = $sourceId !== null ? 'Legacy research publication '.$sourceId : 'Research publication '.$publication->getKey();
        $title = $translation instanceof ResearchPublicationTranslation ? (string) $translation->title : $fallbackTitle;
        $metadata = $this->legacyPublicationMetadata($translation instanceof ResearchPublicationTranslation ? $translation->abstract : null);
        $metadata = $this->withFallbackLegacyPublicationMetadata($metadata, $translations->all());
        $abstract = $metadata['abstract'] !== '' ? $metadata['abstract'] : ($translation instanceof ResearchPublicationTranslation ? $this->plainText($translation->abstract) : '');
        $summary = $translation instanceof ResearchPublicationTranslation ? $this->plainText($translation->excerpt) : '';
        $publisher = $translation instanceof ResearchPublicationTranslation ? $this->plainText($translation->publisher) : '';

        if ($publisher === '') {
            $publisher = $metadata['publisher'];
        }

        if ($summary === '' && $abstract !== '') {
            $summary = Str::limit($abstract, 260);
        }

        $year = $publication->published_at !== null ? $publication->published_at->format('Y') : '';
        $externalUrl = is_string($publication->external_url) && filter_var($publication->external_url, FILTER_VALIDATE_URL) !== false
            ? $publication->external_url
            : null;
        $faculty = $publication->facultyMember?->faculty;
        $facultyTranslation = $faculty?->translations->firstWhere('locale', $locale)
            ?? $faculty?->translations->firstWhere('locale', 'en')
            ?? $faculty?->translations->firstWhere('locale', 'ar');

        return [
            'id' => $sourceId !== null ? 'legacy-'.$sourceId : 'research-publication-'.$publication->getKey(),
            'slug' => $this->databasePublicationSlug($publication, $sourceId),
            'title' => $title,
            'summary' => $summary,
            'lead' => $summary,
            'paragraphs' => $abstract !== '' ? [$abstract] : [],
            'category' => $locale === 'ar' ? 'بحث منشور' : 'Published Research',
            'type' => $locale === 'ar' ? 'منشور بحثي' : 'Publication',
            'typeSlug' => 'published-research',
            'year' => $year,
            'publisher' => $publisher,
            'faculty' => (string) ($facultyTranslation?->name ?? ''),
            'facultySlug' => $faculty !== null ? $this->canonicalFacultySlug((string) ($faculty->public_slug ?: $faculty->slug)) : '',
            'author' => $metadata['author'],
            'doi' => null,
            'keywords' => $metadata['keywords'],
            'resolvedThemes' => [],
            'scholarUrl' => $externalUrl,
            'scopusUrl' => null,
            'image' => '/images/uni-main-place.JPG',
            'isOpenAccess' => $publication->file_media_id !== null,
            'downloads' => $this->publicationDownloads($publication, $locale),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function publicPublicationItems(string $locale): array
    {
        $content = $this->publishedLocalizedPayload('research.publications', $locale);

        if (! is_array($content)) {
            $localized = $this->localized($this->content()['publications'] ?? [], $locale);
            $content = is_array($localized) ? $localized : [];
        }

        $content = $this->withDatabasePublications($content, $locale);

        return array_map(
            fn (array $item): array => $this->sanitizePublication($item),
            $this->arrayList($content['items'] ?? []),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function publicProjectItems(string $locale): array
    {
        $content = $this->publishedLocalizedPayload('research.projects', $locale);

        if (! is_array($content)) {
            $localized = $this->localized($this->content()['projects'] ?? [], $locale);
            $content = is_array($localized) ? $localized : [];
        }

        return $this->normalizedProjectsContent($content)['items'];
    }

    /** @return array<int, array<string, mixed>> */
    private function publicResearcherItems(string $locale): array
    {
        $content = $this->publishedLocalizedPayload('research.experts', $locale);

        if (is_array($content)) {
            $content = $this->normalizedCmsExpertsContent($content);

            return $this->arrayList($content['researchers'] ?? $content['items'] ?? []);
        }

        $localized = $this->localized($this->content()['researchers'] ?? [], $locale);

        return is_array($localized) ? $this->arrayList($localized['items'] ?? []) : [];
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedCentersContent(array $content): array
    {
        $content['hero'] = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $content['intro'] = is_array($content['intro'] ?? null) ? $content['intro'] : [];
        $content['hero']['summary'] = $content['hero']['summary'] ?? $content['intro']['summary'] ?? '';
        $content['hero']['breadcrumbs'] = $this->arrayList($content['hero']['breadcrumbs'] ?? []);
        $content['intro']['highlights'] = $this->arrayList($content['intro']['highlights'] ?? []);
        $content['laboratories'] = is_array($content['laboratories'] ?? null) ? $content['laboratories'] : [];
        $content['laboratories']['items'] = $this->arrayList($content['laboratories']['items'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            $item['facultySlug'] = $this->facultySlugFromCenter($item);
            $item['publicationSlugs'] = $this->scalarList($item['publicationSlugs'] ?? []);
            $item['projectSlugs'] = $this->scalarList($item['projectSlugs'] ?? []);
            $item['researcherSlugs'] = $this->scalarList($item['researcherSlugs'] ?? []);

            foreach (['labs', 'projects', 'publications', 'researchers'] as $field) {
                $item[$field] = is_numeric($item[$field] ?? null) ? max(0, (int) $item[$field]) : 0;
            }

            return $item;
        }, $this->arrayList($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedProjectsContent(array $content): array
    {
        $content['hero'] = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $content['hero']['breadcrumbs'] = $this->arrayList($content['hero']['breadcrumbs'] ?? []);
        $content['filters'] = is_array($content['filters'] ?? null) ? $content['filters'] : [];

        foreach (['statuses', 'faculties', 'themes'] as $optionGroup) {
            $content['filters'][$optionGroup] = array_map(function (array $option) use ($optionGroup): array {
                if ($optionGroup === 'faculties') {
                    $option['value'] = $this->canonicalFacultySlug(trim((string) ($option['value'] ?? '')));
                }

                return $option;
            }, $this->arrayList($content['filters'][$optionGroup] ?? []));
        }

        $content['cardLabels'] = is_array($content['cardLabels'] ?? null) ? $content['cardLabels'] : [];
        $content['items'] = array_map(function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['facultySlug'] = $this->canonicalFacultySlug(strtolower(trim((string) ($item['facultySlug'] ?? ''))));
            $item['themeSlug'] = strtolower(trim((string) ($item['themeSlug'] ?? '')));
            $item['status'] = strtolower(trim((string) ($item['status'] ?? '')));

            return $item;
        }, $this->arrayList($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedThemesContent(array $content): array
    {
        $content['hero'] = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $content['hero']['breadcrumbs'] = $this->arrayList($content['hero']['breadcrumbs'] ?? []);
        $content['items'] = array_map(static function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['publicationCount'] = is_numeric($item['publicationCount'] ?? null) ? max(0, (int) $item['publicationCount']) : 0;
            $item['projectCount'] = is_numeric($item['projectCount'] ?? null) ? max(0, (int) $item['projectCount']) : 0;

            return $item;
        }, $this->arrayList($content['items'] ?? []));

        return $content;
    }

    /** @return array<int, array{label: string, url: string, type: string}> */
    private function publicationDownloads(ResearchPublication $publication, string $locale): array
    {
        $downloads = [];
        $media = $publication->fileMedia;

        if ($media !== null) {
            $url = MediaUrlResolver::resolve($media->path, $media->disk);

            if ($url !== null) {
                $downloads[] = [
                    'label' => $locale === 'ar' ? 'ملف المنشور' : 'Publication file',
                    'url' => $url,
                    'type' => strtoupper((string) ($media->extension ?: 'FILE')),
                ];
            }
        }

        foreach ($publication->files as $file) {
            $fileMedia = $file->mediaAsset;

            if ($fileMedia === null) {
                continue;
            }

            $url = MediaUrlResolver::resolve($fileMedia->path, $fileMedia->disk);

            if ($url === null || collect($downloads)->contains('url', $url)) {
                continue;
            }

            $downloads[] = [
                'label' => (string) ($file->label ?: ($locale === 'ar' ? 'ملف إضافي' : 'Additional file')),
                'url' => $url,
                'type' => strtoupper((string) ($fileMedia->extension ?: 'FILE')),
            ];
        }

        return $downloads;
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function sanitizePublication(array $item): array
    {
        $doi = trim((string) ($item['doi'] ?? ''));

        if (! $this->isPublishableDoi($doi)) {
            $item['doi'] = null;
        }

        $rate = trim((string) ($item['rate'] ?? ''));
        if ($rate !== '' && str_contains(mb_strtolower($rate), 'verify')) {
            $item['rate'] = null;
        }

        foreach (['scholarUrl', 'scopusUrl'] as $field) {
            $url = $item[$field] ?? null;

            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $item[$field] = null;
            }
        }

        $downloads = array_values(array_filter(
            $this->arrayList($item['downloads'] ?? []),
            fn (array $download): bool => $this->isSafePublicResourceUrl($download['url'] ?? null),
        ));
        $item['downloads'] = $downloads;

        if ($doi === '' || ! $this->isPublishableDoi($doi)) {
            $item['isOpenAccess'] = $downloads !== [];
        }

        return $item;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function sanitizeLandingContent(array $content): array
    {
        $featured = is_array($content['featuredPublication'] ?? null) ? $content['featuredPublication'] : [];
        $doi = trim((string) ($featured['doi'] ?? ''));

        if (! $this->isPublishableDoi($doi)) {
            $featured['doi'] = null;
        }

        $content['featuredPublication'] = $featured;

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedConferencesContent(array $content): array
    {
        $content['upcoming'] = array_map(function (array $event): array {
            if (! $this->isSafePublicResourceUrl($event['registrationUrl'] ?? null)) {
                $id = trim((string) ($event['id'] ?? ''));
                $event['registrationUrl'] = $id === '' ? null : '/research/conferences/register?event='.rawurlencode($id);
            }

            return $event;
        }, $this->arrayList($content['upcoming'] ?? []));

        $content['past'] = array_map(function (array $event): array {
            if (! $this->isSafePublicResourceUrl($event['proceedingsUrl'] ?? null)) {
                $event['proceedingsUrl'] = null;
            }

            return $event;
        }, $this->arrayList($content['past'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedPoliciesContent(array $content): array
    {
        $content['sections'] = array_map(function (array $section): array {
            $section['documents'] = array_map(function (array $document): array {
                if (! $this->isSafePublicResourceUrl($document['url'] ?? null)) {
                    $document['url'] = null;
                }

                return $document;
            }, $this->arrayList($section['documents'] ?? []));

            return $section;
        }, $this->arrayList($content['sections'] ?? []));

        return $content;
    }

    private function isPublishableDoi(string $doi): bool
    {
        return $doi !== ''
            && ! str_starts_with($doi, '10.1234/')
            && preg_match('~^10\.\d{4,9}/\S+$~', $doi) === 1;
    }

    private function isSafePublicResourceUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || $url === '#') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            return true;
        }

        $appUrl = (string) config('app.url');

        return parse_url($url, PHP_URL_SCHEME) === parse_url($appUrl, PHP_URL_SCHEME)
            && parse_url($url, PHP_URL_HOST) === parse_url($appUrl, PHP_URL_HOST)
            && parse_url($url, PHP_URL_PORT) === parse_url($appUrl, PHP_URL_PORT);
    }

    private function canonicalFacultySlug(string $slug): string
    {
        return match ($slug) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $slug,
        };
    }

    private function plainText(?string $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** @param array<int, ResearchPublicationTranslation> $translations @return array{author: string, publisher: string, abstract: string, keywords: list<string>} */
    private function withFallbackLegacyPublicationMetadata(array $metadata, array $translations): array
    {
        if ($metadata['author'] !== '' && $metadata['publisher'] !== '' && $metadata['abstract'] !== '' && $metadata['keywords'] !== []) {
            return $metadata;
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof ResearchPublicationTranslation) {
                continue;
            }

            $fallback = $this->legacyPublicationMetadata($translation->abstract);
            $metadata = [
                'author' => $metadata['author'] !== '' ? $metadata['author'] : $fallback['author'],
                'publisher' => $metadata['publisher'] !== '' ? $metadata['publisher'] : $fallback['publisher'],
                'abstract' => $metadata['abstract'] !== '' ? $metadata['abstract'] : $fallback['abstract'],
                'keywords' => $metadata['keywords'] !== [] ? $metadata['keywords'] : $fallback['keywords'],
            ];
        }

        return $metadata;
    }

    /** @return array{author: string, publisher: string, abstract: string, keywords: list<string>} */
    private function legacyPublicationMetadata(?string $value): array
    {
        $lines = $this->legacyPublicationLines($value);

        return [
            'author' => $this->legacySectionText($lines, ['author', 'authors', 'author:', 'authors:', 'المؤلف', 'المؤلفون'], ['published in', 'abstract', 'keywords', 'نشر في', 'الملخص', 'الكلمات المفتاحية']),
            'publisher' => $this->legacySectionText($lines, ['published in', 'published in:', 'publisher', 'publisher:', 'نشر في', 'الناشر'], ['abstract', 'keywords', 'الملخص', 'الكلمات المفتاحية']),
            'abstract' => $this->legacySectionText($lines, ['abstract', 'abstract:', 'الملخص'], ['keywords', 'الكلمات المفتاحية']),
            'keywords' => $this->legacyKeywords($this->legacySectionText($lines, ['keywords', 'keywords:', 'key words', 'key words:', 'الكلمات المفتاحية'], [])),
        ];
    }

    /** @return list<string> */
    private function legacyPublicationLines(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
        $value = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $value) ?? $value;
        $value = strip_tags($value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $value) ?: []),
            static fn (string $line): bool => $line !== ''
        ));
    }

    /** @param list<string> $lines @param list<string> $startLabels @param list<string> $stopLabels */
    private function legacySectionText(array $lines, array $startLabels, array $stopLabels): string
    {
        $capturing = false;
        $captured = [];

        foreach ($lines as $line) {
            $normalized = $this->legacySectionLabel($line);

            if (! $capturing && in_array($normalized, $startLabels, true)) {
                $capturing = true;

                continue;
            }

            if ($capturing && in_array($normalized, $stopLabels, true)) {
                break;
            }

            if ($capturing) {
                $captured[] = $line;
            }
        }

        $text = preg_replace('/\s+/u', ' ', implode(' ', $captured)) ?? implode(' ', $captured);

        return trim($text);
    }

    private function legacySectionLabel(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B:：.-");

        return strtolower($line);
    }

    /** @return list<string> */
    private function legacyKeywords(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,;،]+/u', $value) ?: [];

        return array_slice(array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== ''
        )), 0, 12);
    }

    private function databasePublicationSlug(ResearchPublication $publication, ?int $sourceId): string
    {
        $translations = $publication->translations->keyBy('locale');
        $title = (string) (($translations->get('en')?->title ?? null) ?: ($translations->get('ar')?->title ?? null) ?: 'legacy-research-publication');
        $slug = Str::slug($title);
        $suffix = (string) ($sourceId ?? $publication->getKey());

        return ($slug !== '' ? $slug : 'legacy-research-publication').'-'.$suffix;
    }

    private function cmsResearcher(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $content = $this->publishedLocalizedPayload('research.experts', $locale);

        if (! is_array($content)) {
            return null;
        }

        $content = $this->normalizedCmsExpertsContent($content);

        $items = $this->arrayList($content['researchers'] ?? $content['items'] ?? []);
        $item = $this->firstBySlugOrId($items, $slug);

        if ($item === null) {
            return null;
        }

        $profile = $this->cmsResearcherProfilePayload($item);

        return $this->detailDto($locale, 'researcher', $slug, $item, [
            'item' => $item,
            'profile' => $profile,
            'publications' => $this->arrayList($profile['publications'] ?? []),
        ], '/research/researchers/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizedCmsExpertsContent(array $content): array
    {
        $researchers = array_map(function (array $researcher): array {
            if (is_array($researcher['faculty'] ?? null)) {
                $researcher['profileFaculty'] = $researcher['faculty'];
                $researcher['faculty'] = (string) ($researcher['profileFaculty']['name'] ?? '');
            }

            if (! isset($researcher['title']) && isset($researcher['role'])) {
                $researcher['title'] = $researcher['role'];
            }

            return $researcher;
        }, $this->arrayList($content['researchers'] ?? $content['items'] ?? []));

        $content['researchers'] = $researchers;
        $content['items'] = $researchers;

        return $content;
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function cmsResearcherProfilePayload(array $item): array
    {
        $publications = $this->arrayList($item['profilePublications'] ?? []);
        $publicationCount = is_numeric($item['publications'] ?? null) ? (int) $item['publications'] : count($publications);
        $citations = is_numeric($item['citations'] ?? null) ? (int) $item['citations'] : 0;

        return [
            'slug' => $item['slug'] ?? $item['id'] ?? '',
            'name' => $item['name'] ?? '',
            'role' => $item['role'] ?? $item['title'] ?? '',
            'department' => $item['department'] ?? '',
            'description' => $item['description'] ?? $item['bio'] ?? '',
            'biography' => is_array($item['biography'] ?? null) ? $item['biography'] : (! empty($item['bio']) ? [$item['bio']] : []),
            'education' => $this->arrayList($item['education'] ?? []),
            'courses' => $this->arrayList($item['courses'] ?? []),
            'expertise' => is_array($item['expertise'] ?? null) ? $item['expertise'] : [],
            'faculty' => is_array($item['profileFaculty'] ?? null) ? $item['profileFaculty'] : [
                'id' => $item['facultyId'] ?? $item['facultySlug'] ?? '',
                'name' => $item['faculty'] ?? '',
                'slug' => $item['facultySlug'] ?? $item['facultyId'] ?? '',
            ],
            'office' => is_array($item['office'] ?? null) ? $item['office'] : null,
            'email' => $item['email'] ?? '',
            'image' => $item['image'] ?? '/images/uni-main-place.JPG',
            'orcidUrl' => $item['orcidUrl'] ?? '',
            'scholarUrl' => $item['scholarUrl'] ?? '',
            'researchStats' => is_array($item['researchStats'] ?? null) ? $item['researchStats'] : [
                'publications' => $publicationCount,
                'citations' => $citations,
            ],
            'publications' => $publications,
            'isResearcher' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid research data file: '.$path);
        }

        return $decoded;
    }

    /** @return array<int, array<string, mixed>> */
    private function arrayList(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string, mixed> $hero */
    private function pageDto(string $locale, string $type, array $data, string $path, array $hero): ResearchPageDTO
    {
        $localizedHero = $this->localized($hero, $locale);
        $title = (string) ($localizedHero['title'] ?? ($locale === 'ar' ? 'البحث' : 'Research'));
        $description = (string) ($localizedHero['summary'] ?? ($locale === 'ar'
            ? 'صفحات البحث في الجامعة السورية الخاصة.'
            : 'Research pages at Syrian Private University.'));

        return new ResearchPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            type: $type,
            data: $this->normalizeUrls($data, $locale),
            seoTitle: $title.' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'Syrian Private University'),
            seoDescription: $description,
            seoImage: (string) ($localizedHero['backgroundImage'] ?? '/images/uni-main-place.JPG'),
            path: '/'.$locale.$path,
        );
    }

    /** @param array<string, mixed> $item @param array<string, mixed> $data */
    private function detailDto(string $locale, string $type, string $slug, array $item, array $data, string $path, string $image): ResearchDetailPageDTO
    {
        $localizedItem = $this->localized($item, $locale);
        $localizedItem = $type === 'publication' && is_array($localizedItem) ? $this->sanitizePublication($localizedItem) : $localizedItem;
        $title = (string) ($localizedItem['title'] ?? $localizedItem['name'] ?? ($locale === 'ar' ? 'البحث' : 'Research'));
        $description = (string) ($localizedItem['summary'] ?? $localizedItem['mission'] ?? $localizedItem['description'] ?? $title);

        return new ResearchDetailPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            type: $type,
            slug: $slug,
            item: $this->normalizeUrls($localizedItem, $locale),
            data: $this->normalizeUrls($this->localized($data, $locale), $locale),
            seoTitle: $title.' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'Syrian Private University'),
            seoDescription: $description,
            seoImage: $image,
            path: '/'.$locale.$path,
        );
    }

    private function localized(mixed $value, string $locale): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_keys($value) === ['en', 'ar'] || array_keys($value) === ['ar', 'en']) {
            return $value[$locale] ?? $value['en'] ?? $value['ar'] ?? '';
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->localized($item, $locale), $value);
        }

        $localized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                $localized[$key] = $this->localized($item, $locale);

                continue;
            }

            if (str_ends_with($key, 'En') || str_ends_with($key, 'Ar')) {
                $base = substr($key, 0, -2);
                $suffix = $locale === 'ar' ? 'Ar' : 'En';

                if (str_ends_with($key, $suffix)) {
                    $localized[$base] = $this->localized($item, $locale);
                }

                continue;
            }

            $localized[$key] = $this->localized($item, $locale);
        }

        return $localized;
    }

    private function normalizeUrls(mixed $value, string $locale): mixed
    {
        if (is_string($value)) {
            return $this->localizedUrl($value, $locale);
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(fn (mixed $item): mixed => $this->normalizeUrls($item, $locale), $value);
    }

    private function localizedUrl(string $url, string $locale): string
    {
        if ($url === '/' || $url === '/research.html') {
            return '/'.$locale.'/research';
        }

        if (str_starts_with($url, '/research/')) {
            return '/'.$locale.rtrim($url, '/');
        }

        if (str_starts_with($url, '/contact')) {
            return '/'.$locale.$url;
        }

        return $url;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function firstBySlug(array $items, string $slug): ?array
    {
        foreach ($items as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function researcherSourceItem(string $slug): ?array
    {
        $researcherItems = $this->content()['researchers']['items'] ?? [];
        $expertItems = $this->content()['expertFinder']['researchers'] ?? [];
        $base = $this->firstBySlugOrId($researcherItems, $slug);
        $expert = $this->firstBySlugOrId($expertItems, $slug);

        if ($base === null && $expert === null) {
            return null;
        }

        return array_merge($expert ?? [], $base ?? []);
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed>|null */
    private function firstBySlugOrId(array $items, string $slug): ?array
    {
        foreach ($items as $item) {
            if (($item['slug'] ?? null) === $slug || ($item['id'] ?? null) === $slug) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $researcher @return array<int, array<string, mixed>> */
    private function researcherPublications(array $researcher): array
    {
        $researcherIds = array_filter([
            $researcher['id'] ?? null,
            $researcher['slug'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');

        $publications = array_values(array_filter(
            $this->detailContent()['publications'] ?? [],
            static fn (array $publication): bool => in_array($publication['authorSlug'] ?? '', $researcherIds, true),
        ));

        return array_map(fn (array $publication): array => $this->profilePublication($publication), $publications);
    }

    /** @param array<string, mixed> $publication @return array<string, mixed> */
    private function profilePublication(array $publication): array
    {
        $slug = (string) ($publication['slug'] ?? '');

        return [
            'id' => $publication['id'] ?? $slug,
            'slug' => $slug,
            'titleEn' => $publication['titleEn'] ?? '',
            'titleAr' => $publication['titleAr'] ?? '',
            'journalEn' => $publication['journalEn'] ?? $publication['categoryEn'] ?? $publication['typeEn'] ?? '',
            'journalAr' => $publication['journalAr'] ?? $publication['categoryAr'] ?? $publication['typeAr'] ?? '',
            'year' => $publication['year'] ?? '',
            'links' => [
                'local' => $slug !== '' ? '/research/publications/'.$slug.'/' : '#',
                'scholar' => $publication['scholarUrl'] ?? '',
            ],
        ];
    }

    /** @param array<string, mixed> $researcher @param array<int, array<string, mixed>> $publications @return array<string, mixed> */
    private function researcherProfile(array $researcher, array $publications): array
    {
        $enrichment = $this->researcherProfileEnrichment((string) ($researcher['id'] ?? $researcher['slug'] ?? ''));
        $bioEn = (string) ($researcher['bioEn'] ?? '');
        $bioAr = (string) ($researcher['bioAr'] ?? '');
        $orcidUrl = $researcher['orcidUrl'] ?? '';

        if ($orcidUrl === '' && ! empty($researcher['orcidId'])) {
            $orcidUrl = 'https://orcid.org/'.$researcher['orcidId'];
        }

        $enrichedPublications = $enrichment['publications'] ?? [];

        return [
            'slug' => $researcher['slug'] ?? $researcher['id'] ?? '',
            'nameEn' => $researcher['nameEn'] ?? '',
            'nameAr' => $researcher['nameAr'] ?? '',
            'roleEn' => $researcher['titleEn'] ?? '',
            'roleAr' => $researcher['titleAr'] ?? '',
            'departmentEn' => $researcher['departmentEn'] ?? '',
            'departmentAr' => $researcher['departmentAr'] ?? '',
            'descriptionEn' => $bioEn,
            'descriptionAr' => $bioAr,
            'biographyEn' => $enrichment['biographyEn'] ?? ($bioEn !== '' ? [$bioEn] : []),
            'biographyAr' => $bioAr !== '' ? [$bioAr] : [],
            'educationEn' => $enrichment['educationEn'] ?? [],
            'educationAr' => $enrichment['educationAr'] ?? $enrichment['educationEn'] ?? [],
            'courses' => $enrichment['courses'] ?? [],
            'expertiseEn' => $researcher['expertiseEn'] ?? [],
            'expertiseAr' => $researcher['expertiseAr'] ?? [],
            'faculty' => $this->researcherFaculty($researcher),
            'office' => $enrichment['office'] ?? null,
            'email' => $researcher['email'] ?? '',
            'image' => $researcher['image'] ?? '/images/uni-main-place.JPG',
            'orcidUrl' => $orcidUrl,
            'scholarUrl' => $enrichment['scholarUrl'] ?? $researcher['scholarUrl'] ?? '',
            'researchStats' => [
                'publications' => $researcher['publications'] ?? 0,
                'citations' => $researcher['citations'] ?? 0,
            ],
            'publications' => $this->uniqueProfilePublications([...$enrichedPublications, ...$publications]),
            'isResearcher' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function researcherProfileEnrichment(string $slug): array
    {
        return match ($slug) {
            'arwa-khair' => [
                'office' => ['fullAddressEn' => 'Academic Affairs Office', 'fullAddressAr' => 'مكتب الشؤون العلمية'],
                'biographyEn' => [
                    'Dr. Arwa Khair supports academic affairs across SPU, with responsibility for curriculum quality, faculty coordination, and academic standards.',
                    'Her work focuses on aligning teaching practice, assessment, and program development with institutional goals.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral degree in an academic discipline',
                    'Experience in curriculum and academic standards',
                    'Leadership experience in higher education',
                ]),
            ],
            'ayman-ali' => [
                'scholarUrl' => 'https://scholar.google.com',
                'office' => ['fullAddressEn' => 'Faculty of Medicine Dean Office', 'fullAddressAr' => 'مكتب عميد كلية الطب البشري'],
                'biographyEn' => [
                    'Dr. Ayman Ali leads the Faculty of Medicine, supporting medical education, clinical training, and faculty coordination.',
                    'His profile highlights the faculty commitment to applied learning, ethical practice, and student progression.',
                ],
                'educationEn' => $this->educationItems([
                    'Medical doctorate and senior academic experience',
                    'Clinical and teaching leadership background',
                    'Experience in health-science program management',
                ]),
                'courses' => $this->courseItems(['Clinical Medicine', 'Medical Education Seminar'], 'medicine-plan'),
                'publications' => [[
                    'id' => 'res-005',
                    'titleEn' => 'Clinical Simulation Training Impact on Medical Student Diagnostic Accuracy',
                    'titleAr' => 'تأثير تدريب المحاكاة السريرية على دقة تشخيص طلاب الطب',
                    'journalEn' => 'Journal of Medical Education',
                    'journalAr' => 'Journal of Medical Education',
                    'year' => 2024,
                    'links' => ['local' => '/research/publications/clinical-simulation-training-medical-students/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            'talaat-abuhatab', 'talaat-abu-hatab' => [
                'office' => ['fullAddressEn' => 'Faculty of Dentistry Dean Office', 'fullAddressAr' => 'مكتب عميد كلية طب الأسنان'],
                'biographyEn' => [
                    'Dr. Talaat Abu Hatab leads dentistry programs with attention to clinical readiness, patient care standards, and practical training.',
                    'His office supports coordinated learning between classrooms, labs, and the university dental clinics.',
                ],
                'educationEn' => $this->educationItems([
                    'DDS or equivalent dental qualification',
                    'Senior clinical and academic experience',
                    'Experience in dental education leadership',
                ]),
                'courses' => $this->courseItems(['Advanced Clinical Dentistry', 'Dental Practice Management'], 'dentistry-plan'),
                'publications' => [[
                    'id' => 'res-002',
                    'titleEn' => 'AI-Driven Predictive Models for Early Dental Caries Detection',
                    'titleAr' => 'نماذج تنبؤية مدعومة بالذكاء الاصطناعي للكشف المبكر عن تسوس الأسنان',
                    'journalEn' => 'International Journal of Dental Informatics',
                    'journalAr' => 'International Journal of Dental Informatics',
                    'year' => 2024,
                    'links' => ['local' => '/research/publications/ai-dental-diagnostics/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            'hossam-shahrour' => [
                'scholarUrl' => 'https://scholar.google.com',
                'office' => ['fullAddressEn' => 'Faculty of Pharmacy Dean Office', 'fullAddressAr' => 'مكتب عميد كلية الصيدلة'],
                'biographyEn' => [
                    'Dr. Hossam Shahrour leads the Faculty of Pharmacy, supporting pharmaceutical education, laboratory learning, and applied research.',
                    'His work helps connect scientific foundations with professional pharmacy practice.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral qualification in pharmaceutical sciences',
                    'Academic and laboratory leadership experience',
                    'Experience in health-science education',
                ]),
                'courses' => $this->courseItems(['Pharmaceutical Sciences Seminar', 'Applied Pharmacy Research'], 'pharmacy-plan'),
                'publications' => [[
                    'id' => 'res-001',
                    'titleEn' => 'Machine Learning Applications in Pharmaceutical Quality Control',
                    'titleAr' => 'تطبيقات تعلم الآلة في مراقبة الجودة الصيدلانية',
                    'journalEn' => 'Journal of Pharmaceutical Analysis',
                    'journalAr' => 'Journal of Pharmaceutical Analysis',
                    'year' => 2024,
                    'links' => ['local' => '/research/publications/machine-learning-pharmaceutical-quality-control/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            'mouhib-alnoukari' => [
                'scholarUrl' => 'https://scholar.google.com',
                'office' => ['fullAddressEn' => 'Faculty of Engineering Dean Office', 'fullAddressAr' => 'مكتب عميد كلية الهندسة'],
                'biographyEn' => [
                    'Dr. Mouhib Alnoukari leads engineering academic programs with a focus on technical capability, innovation, and applied project work.',
                    'His office supports learning environments where students connect engineering theory with practical systems and design challenges.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral qualification in engineering or technology',
                    'Academic leadership experience',
                    'Background in applied technical education',
                ]),
                'courses' => [
                    ['id' => 'ai-201', 'code' => 'AI201', 'nameEn' => 'Artificial Intelligence', 'nameAr' => 'الذكاء الاصطناعي', 'departmentId' => 'si'],
                    ['id' => 'ml-301', 'code' => 'ML301', 'nameEn' => 'Machine Learning', 'nameAr' => 'تعلم الآلة', 'departmentId' => 'si'],
                    ['id' => 'data-301', 'code' => 'DATA301', 'nameEn' => 'Data Science', 'nameAr' => 'علم البيانات', 'departmentId' => 'si'],
                ],
                'publications' => [[
                    'id' => 'res-006',
                    'titleEn' => 'Natural Language Processing for Arabic Medical Record Summarization',
                    'titleAr' => 'معالجة اللغة الطبيعية لتلخيص السجلات الطبية العربية',
                    'journalEn' => 'ACM Transactions on Asian Language Information Processing',
                    'journalAr' => 'ACM Transactions on Asian Language Information Processing',
                    'year' => 2024,
                    'links' => ['local' => '/research/publications/arabic-medical-record-nlp/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            'mahmoud-hadid' => [
                'office' => ['fullAddressEn' => 'Faculty of Petroleum Engineering Dean Office', 'fullAddressAr' => 'مكتب عميد كلية هندسة البترول'],
                'biographyEn' => [
                    'Dr. Mahmoud Hadid leads petroleum engineering education with emphasis on geoscience foundations, energy systems, and field-oriented learning.',
                    'His profile reflects the faculty role in preparing students for technical work in energy and industrial contexts.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral qualification in petroleum or energy engineering',
                    'Academic and technical leadership experience',
                    'Experience in applied engineering education',
                ]),
                'courses' => $this->courseItems(['Petroleum Engineering Systems', 'Field Training Seminar'], 'petroleum-plan'),
                'publications' => [[
                    'id' => 'res-004',
                    'titleEn' => 'Deep Learning Framework for Reservoir Permeability Prediction',
                    'titleAr' => 'إطار تعلم عميق للتنبؤ بنفاذية المكامن',
                    'journalEn' => 'Journal of Petroleum Science and Engineering',
                    'journalAr' => 'Journal of Petroleum Science and Engineering',
                    'year' => 2023,
                    'links' => ['local' => '/research/publications/deep-learning-reservoir-permeability/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            'samar-habib' => [
                'scholarUrl' => 'https://scholar.google.com',
                'office' => ['fullAddressEn' => 'Faculty of Business Administration Dean Office', 'fullAddressAr' => 'مكتب عميد كلية إدارة الأعمال'],
                'biographyEn' => [
                    'Dr. Samar Habib leads the Faculty of Business Administration, supporting management education, entrepreneurship, and career-oriented academic experiences.',
                    'Her office helps connect classroom learning with organizational analysis, project work, and professional readiness.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral qualification in business or management',
                    'Academic leadership experience',
                    'Background in program development and advising',
                ]),
                'courses' => $this->courseItems(['Strategic Management', 'Business Leadership'], 'management'),
            ],
            'ammar-ghada' => [
                'office' => ['fullAddressEn' => 'Faculty of Building & Construction Engineering Dean Office', 'fullAddressAr' => 'مكتب عميد كلية هندسة التشييد والبناء'],
                'biographyEn' => [
                    'Dr. Ammar Ghada leads the Faculty of Building and Construction Engineering, supporting structural engineering, architectural design, and applied construction education.',
                    'His office prepares qualified engineers to contribute to reconstruction and development projects.',
                ],
                'educationEn' => $this->educationItems([
                    'Doctoral qualification in civil or construction engineering',
                    'Academic leadership experience',
                    'Background in structural design and project delivery',
                ]),
                'courses' => $this->courseItems(['Structural Analysis', 'Construction Methods'], 'construction-plan'),
                'publications' => [[
                    'id' => 'res-003',
                    'titleEn' => 'Structural Performance of Fiber-Reinforced Concrete in Seismic Zones',
                    'titleAr' => 'الأداء الإنشائي للخرسانة المسلحة بالألياف في المناطق الزلزالية',
                    'journalEn' => 'Engineering Structures',
                    'journalAr' => 'Engineering Structures',
                    'year' => 2023,
                    'links' => ['local' => '/research/publications/structural-analysis-earthquake-resistant-concrete/', 'scholar' => 'https://scholar.google.com'],
                ]],
            ],
            default => [],
        };
    }

    /** @param array<int, string> $qualifications @return array<int, array<string, mixed>> */
    private function educationItems(array $qualifications): array
    {
        return array_map(static fn (string $qualification): array => [
            'degreeEn' => $qualification,
            'degreeAr' => $qualification,
            'institution' => '',
            'year' => null,
        ], $qualifications);
    }

    /** @param array<int, string> $courseNames @return array<int, array<string, string>> */
    private function courseItems(array $courseNames, string $departmentId): array
    {
        return array_map(static fn (string $name, int $index): array => [
            'id' => $departmentId.'-'.($index + 1),
            'code' => strtoupper($departmentId).str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'nameEn' => $name,
            'nameAr' => $name,
            'departmentId' => $departmentId,
        ], $courseNames, array_keys($courseNames));
    }

    /** @param array<int, array<string, mixed>> $publications @return array<int, array<string, mixed>> */
    private function uniqueProfilePublications(array $publications): array
    {
        $seen = [];
        $unique = [];

        foreach ($publications as $publication) {
            $key = (string) ($publication['id'] ?? $publication['slug'] ?? $publication['titleEn'] ?? $publication['title'] ?? '');

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $publication;
        }

        return $unique;
    }

    /** @param array<string, mixed> $researcher @return array<string, mixed>|null */
    private function researcherFaculty(array $researcher): ?array
    {
        $facultyId = (string) ($researcher['facultyId'] ?? $researcher['facultySlug'] ?? '');
        $slugs = [
            'ai' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
        ];

        return [
            'id' => $facultyId,
            'nameEn' => $researcher['facultyEn'] ?? '',
            'nameAr' => $researcher['facultyAr'] ?? '',
            'slug' => $slugs[$facultyId] ?? $facultyId,
            'route' => '#',
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function indexBySlug(array $items, string $slug): int
    {
        foreach ($items as $index => $item) {
            if (($item['slug'] ?? null) === $slug) {
                return $index;
            }
        }

        return 0;
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function uniqueBySlug(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $slug = (string) ($item['slug'] ?? '');

            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /** @param array<string, mixed> $center */
    private function facultySlugFromCenter(array $center): string
    {
        $explicitSlug = $this->canonicalFacultySlug(trim((string) ($center['facultySlug'] ?? '')));

        if ($explicitSlug !== '') {
            return $explicitSlug;
        }

        $faculty = strtolower((string) ($center['facultyEn'] ?? $center['faculty'] ?? ''));

        return match (true) {
            ($center['slug'] ?? null) === 'ai-digital-innovation', str_contains($faculty, 'artificial') => 'artificial-intelligence',
            ($center['slug'] ?? null) === 'clinical-research-simulation', str_contains($faculty, 'medicine') => 'medicine',
            str_contains($faculty, 'dentistry') => 'dentistry',
            str_contains($faculty, 'pharmacy') => 'pharmacy',
            str_contains($faculty, 'petroleum') => 'petroleum',
            ($center['slug'] ?? null) === 'energy-sustainable-systems' => 'petroleum',
            str_contains($faculty, 'construction') => 'building-construction-engineering',
            str_contains($faculty, 'business') => 'business-administration',
            default => '',
        };
    }
}
