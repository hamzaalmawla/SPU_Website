<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;

final class ResearchPageService implements ResearchPageServiceInterface
{
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
            return $this->pageDto($locale, 'landing', $cmsContent, '/research', $cmsContent['hero'] ?? []);
        }

        $data = $this->localized($this->content(), $locale);

        return $this->pageDto($locale, 'landing', $data, '/research', $data['hero'] ?? []);
    }

    public function repository(string $locale): ResearchPageDTO
    {
        $data = $this->content()['publications'] ?? [];

        return $this->pageDto($locale, 'repository', $this->localized($data, $locale), '/research/repository', $data['hero'] ?? []);
    }

    public function publications(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.publications', $locale);

        if (is_array($cmsContent)) {
            return $this->pageDto($locale, 'publications', $cmsContent, '/research/publications', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['publications'] ?? [];

        return $this->pageDto($locale, 'publications', $this->localized($data, $locale), '/research/publications', $data['hero'] ?? []);
    }

    public function publication(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $cmsPage = $this->cmsPublication($locale, $slug);

        if ($cmsPage instanceof ResearchDetailPageDTO) {
            return $cmsPage;
        }

        $items = $this->detailContent()['publications'] ?? [];
        $item = $this->firstBySlug($items, $slug);

        if ($item === null) {
            return null;
        }

        $index = $this->indexBySlug($items, $slug);
        $previous = $items[($index - 1 + count($items)) % count($items)] ?? null;
        $next = $items[($index + 1) % count($items)] ?? null;
        $sameFaculty = array_values(array_filter($items, static fn (array $publication): bool =>
            ($publication['slug'] ?? null) !== $slug && ($publication['facultyEn'] ?? null) === ($item['facultyEn'] ?? null)
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
        if (! in_array($targetKey, ['research.index', 'research.publications', 'research.experts', 'research.conferences', 'research.library', 'research.office', 'research.policies'], true)) {
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
                    'research.experts' => $this->editableExpertsContent('ar'),
                    'research.conferences' => $this->editableTargetContent('conferences', 'ar'),
                    'research.library' => $this->editableTargetContent('library', 'ar'),
                    'research.office' => $this->editableTargetContent('office', 'ar'),
                    'research.policies' => $this->editableTargetContent('policies', 'ar'),
                },
                'en' => match ($targetKey) {
                    'research.index' => $this->editableLandingContent('en'),
                    'research.publications' => $this->editablePublicationsContent('en'),
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
            'research.library' => ['library', '/research/library'],
            'research.office' => ['office', '/research/office'],
            'research.policies' => ['policies', '/research/policies'],
            default => throw new \InvalidArgumentException('Unsupported research preview target.'),
        };

        return $this->pageDto($locale, $type, $content, $path, $content['hero'] ?? []);
    }

    public function centers(string $locale): ResearchPageDTO
    {
        $data = $this->content()['centers'] ?? [];

        return $this->pageDto($locale, 'centers', $this->localized($data, $locale), '/research/centers', $data['hero'] ?? []);
    }

    public function center(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $centers = $this->content()['centers']['items'] ?? [];
        $item = $this->firstBySlug($centers, $slug);

        if ($item === null) {
            return null;
        }

        $facultySlug = $this->facultySlugFromCenter($item);
        $publications = array_values(array_filter($this->content()['publications']['items'] ?? [], static fn (array $publication): bool => ($publication['facultySlug'] ?? '') === $facultySlug));
        $projects = array_values(array_filter($this->content()['projects']['items'] ?? [], static fn (array $project): bool => ($project['facultySlug'] ?? '') === $facultySlug));

        return $this->detailDto($locale, 'center', $slug, $item, [
            'item' => $item,
            'publications' => array_slice($publications, 0, 4),
            'projects' => array_slice($projects, 0, 3),
            'faculty' => $this->content()['researchers']['items'] ?? [],
        ], '/research/centers/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function projects(string $locale): ResearchPageDTO
    {
        $data = $this->content()['projects'] ?? [];

        return $this->pageDto($locale, 'projects', $this->localized($data, $locale), '/research/projects', $data['hero'] ?? []);
    }

    public function project(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $item = $this->firstBySlug($this->content()['projects']['items'] ?? [], $slug);

        if ($item === null) {
            return null;
        }

        return $this->detailDto($locale, 'project', $slug, $item, ['item' => $item], '/research/projects/'.$slug, $item['image'] ?? '/images/uni-main-place.JPG');
    }

    public function themes(string $locale): ResearchPageDTO
    {
        $data = $this->content()['themes'] ?? [];

        return $this->pageDto($locale, 'themes', $this->localized($data, $locale), '/research/themes', $data['hero'] ?? []);
    }

    public function theme(string $locale, string $slug): ?ResearchDetailPageDTO
    {
        $item = $this->firstBySlug($this->content()['themes']['items'] ?? [], $slug);

        if ($item === null) {
            return null;
        }

        $publications = array_values(array_filter($this->content()['publications']['items'] ?? [], static fn (array $publication): bool => in_array($slug, $publication['themes'] ?? [], true)));
        $projects = array_values(array_filter($this->content()['projects']['items'] ?? [], static fn (array $project): bool => ($project['themeSlug'] ?? '') === $slug));

        return $this->detailDto($locale, 'theme', $slug, $item, [
            'item' => $item,
            'publications' => $publications,
            'projects' => $projects,
        ], '/research/themes/'.$slug, '/images/uni-main-place.JPG');
    }

    public function researchers(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.experts', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedCmsExpertsContent($cmsContent);

            return $this->pageDto($locale, 'researchers', $cmsContent, '/research/researchers', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['researchers'] ?? [];

        return $this->pageDto($locale, 'researchers', $this->localized($data, $locale), '/research/researchers', $data['hero'] ?? []);
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

    public function expertFinder(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.experts', $locale);

        if (is_array($cmsContent)) {
            $cmsContent = $this->normalizedCmsExpertsContent($cmsContent);

            return $this->pageDto($locale, 'expert-finder', $cmsContent, '/research/expert-finder', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['expertFinder'] ?? [];
        $data['items'] = $data['researchers'] ?? [];

        return $this->pageDto($locale, 'expert-finder', $this->localized($data, $locale), '/research/expert-finder', $data['hero'] ?? []);
    }

    public function conferences(string $locale): ResearchPageDTO
    {
        $cmsContent = $this->publishedLocalizedPayload('research.conferences', $locale);

        if (is_array($cmsContent)) {
            return $this->pageDto($locale, 'conferences', $cmsContent, '/research/conferences', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['conferences'] ?? [];

        return $this->pageDto($locale, 'conferences', $this->localized($data, $locale), '/research/conferences', $data['hero'] ?? []);
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
            return $this->pageDto($locale, 'policies', $cmsContent, '/research/policies', $cmsContent['hero'] ?? []);
        }

        $data = $this->content()['policies'] ?? [];

        return $this->pageDto($locale, 'policies', $this->localized($data, $locale), '/research/policies', $data['hero'] ?? []);
    }

    public function publicationSlugForLegacyId(string $id): ?string
    {
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

        $items = $this->arrayList($content['items'] ?? []);
        $item = $this->firstBySlug($items, $slug);

        if ($item === null) {
            return null;
        }

        $index = $this->indexBySlug($items, $slug);
        $previous = $items[($index - 1 + count($items)) % count($items)] ?? null;
        $next = $items[($index + 1) % count($items)] ?? null;
        $sameFaculty = array_values(array_filter($items, static fn (array $publication): bool =>
            ($publication['slug'] ?? null) !== $slug && ($publication['facultySlug'] ?? null) === ($item['facultySlug'] ?? null)
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
        $faculty = strtolower((string) ($center['facultyEn'] ?? ''));

        return match (true) {
            str_contains($faculty, 'artificial') => 'ai',
            str_contains($faculty, 'medicine') => 'medicine',
            str_contains($faculty, 'dentistry') => 'dentistry',
            str_contains($faculty, 'pharmacy') => 'pharmacy',
            str_contains($faculty, 'petroleum') => 'petroleum',
            str_contains($faculty, 'construction') => 'construction',
            str_contains($faculty, 'business') => 'business',
            default => '',
        };
    }
}
