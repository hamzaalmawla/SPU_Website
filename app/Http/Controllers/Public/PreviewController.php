<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\DTOs\About\PartnershipDirectoryDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Page\PageDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Preview\PreviewDTO;
use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PreviewController extends Controller
{
    public function __construct(
        private readonly PreviewServiceInterface $previewService,
        private readonly AboutPageServiceInterface $aboutPageService,
        private readonly NewsServiceInterface $newsService,
        private readonly AdmissionsPageServiceInterface $admissionsPageService,
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly ContactPageServiceInterface $contactPageService,
        private readonly EServicesPageServiceInterface $eServicesPageService,
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly PageServiceInterface $pageService,
        private readonly VirtualTourPageServiceInterface $virtualTourPageService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly AboutEntityCmsServiceInterface $aboutEntityCmsService,
        private readonly NewsArticleCmsServiceInterface $newsArticleCmsService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $token = $this->resolvePreviewToken($request);

        abort_if($token === null, 404);

        $preview = $this->previewService->resolveToken($token, $locale);

        abort_if($preview === null, 404);

        if ($preview->targetType === 'cms') {
            return $this->renderCmsPreview($request, $locale, $preview);
        }

        if ($preview->payload->page instanceof PageDTO && ! $preview->payload->page->metadata->isHomepageShell) {
            return $this->renderPagePreview($locale, $preview);
        }

        return $this->renderHomepagePreview($locale, $preview);
    }

    private function renderHomepagePreview(string $locale, PreviewDTO $preview): View
    {
        $homepage = $preview->payload->homepage;
        abort_if($homepage === null, 404);

        $homeShell = $this->pageService->getPublicPageBySlug('home', $locale);

        return view('public.home', [
            'locale' => $locale,
            'direction' => $homepage->direction,
            'homepage' => $homepage,
            'homepageFooterSection' => $homepage->findSection('footer'),
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $homeShell !== null
                ? ($locale === 'ar' ? $homeShell->arabicSeo : $homeShell->englishSeo)
                : $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale,
                    'locale_paths' => ['ar' => '/ar', 'en' => '/en'],
                ]),
            'languageSwitch' => $this->homepageLanguageSwitchLinks($locale, $preview->token),
            'isPreview' => true,
            'preview' => $preview,
        ]);
    }

    private function renderPagePreview(string $locale, PreviewDTO $preview): View
    {
        $page = $preview->payload->page;
        abort_if($page === null, 404);

        $translation = $locale === 'ar' ? $page->arabicTranslation : $page->englishTranslation;
        $seo = $locale === 'ar' ? $page->arabicSeo : $page->englishSeo;

        return view('public.page', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $seo,
            'page' => $this->pagePayload($page, $translation),
            'breadcrumbs' => $this->pageService->buildBreadcrumbPayload($page->id, $locale),
            'languageSwitch' => $this->pageLanguageSwitchLinks($page->id, $locale, $preview->token),
            'isPreview' => true,
            'preview' => $preview,
        ]);
    }

    private function renderCmsPreview(Request $request, string $locale, PreviewDTO $preview): View
    {
        $snapshot = $preview->payload->cms;
        abort_if(! is_array($snapshot), 404);

        $targetKey = $snapshot['target_key'] ?? null;
        $payload = is_array($snapshot['payload'] ?? null) ? $snapshot['payload'] : [];

        if (is_string($targetKey) && str_starts_with($targetKey, 'entity.news-article.')) {
            return $this->renderNewsArticlePreview($locale, $preview, $payload);
        }

        if (is_string($targetKey) && str_starts_with($targetKey, 'entity.')) {
            return $this->renderAboutEntityPreview($locale, $preview, $targetKey, $payload);
        }

        $localizedContent = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : null;

        abort_if(! is_array($localizedContent), 404);

        if ($targetKey === 'admissions.landing') {
            return $this->renderAdmissionsLandingPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'about.landing') {
            return $this->renderAboutLandingPreview($locale, $preview, $localizedContent);
        }

        if (is_string($targetKey) && str_starts_with($targetKey, 'about.')) {
            return $this->renderAboutContentPreview($locale, $preview, $targetKey, $localizedContent);
        }

        if ($targetKey === 'news.index') {
            return $this->renderNewsIndexPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'news.articles') {
            return $this->renderNewsArticlesPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'news.announcements') {
            return $this->renderNewsAnnouncementsPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'news.events') {
            return $this->renderNewsEventsPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'news.gallery') {
            return $this->renderNewsGalleryPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.publications') {
            return $this->renderResearchPublicationsPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.index') {
            return $this->renderResearchLandingPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.experts') {
            return $this->renderResearchExpertsPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.centers') {
            return $this->renderResearchCentersPreview($request, $locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.projects') {
            return $this->renderResearchProjectsPreview($request, $locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.themes') {
            return $this->renderResearchThemesPreview($request, $locale, $preview, $localizedContent);
        }

        if ($targetKey === 'research.conferences') {
            return $this->renderResearchTargetPreview($locale, $preview, $targetKey, $localizedContent, 'public.research.conferences', '/research/conferences');
        }

        if ($targetKey === 'research.library') {
            return $this->renderResearchTargetPreview($locale, $preview, $targetKey, $localizedContent, 'public.research.library', '/research/library');
        }

        if ($targetKey === 'research.office') {
            return $this->renderResearchTargetPreview($locale, $preview, $targetKey, $localizedContent, 'public.research.office', '/research/office');
        }

        if ($targetKey === 'research.policies') {
            return $this->renderResearchTargetPreview($locale, $preview, $targetKey, $localizedContent, 'public.research.policies', '/research/policies');
        }

        if ($targetKey === 'campus_life.landing') {
            return $this->renderCampusLifeLandingPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'campus_life.virtual_tour') {
            return $this->renderVirtualTourPreview($locale, $preview, $localizedContent);
        }

        if ($targetKey === 'campus_life.jobs') {
            return $this->renderCampusLifeJobsPreview($request, $locale, $preview, $localizedContent);
        }

        if ($targetKey === 'facilities.landing') {
            return $this->renderFacilitiesHubPreview($locale, $preview, $localizedContent);
        }

        if (is_string($targetKey) && $this->isFacultyHomepageTarget($targetKey)) {
            return $this->renderFacultyHomepagePreview($locale, $preview, $targetKey, $localizedContent);
        }

        if (is_string($targetKey) && $this->isFacultySubpageTarget($targetKey)) {
            return $this->renderFacultySubpagePreview($locale, $preview, $targetKey, $localizedContent);
        }

        if (is_string($targetKey) && str_starts_with($targetKey, 'campus_life.')) {
            return $this->renderCampusLifeSectionPreview($locale, $preview, $targetKey, $localizedContent);
        }

        if (is_string($targetKey) && str_starts_with($targetKey, 'admissions.')) {
            return $this->renderAdmissionsSectionPreview($locale, $preview, $targetKey, $localizedContent);
        }

        if ($targetKey === 'e_services.suggestions-complaints') {
            return $this->renderSuggestionsComplaintsPreview($locale, $preview, $localizedContent);
        }

        if (is_string($targetKey) && str_starts_with($targetKey, 'e_services.')) {
            return $this->renderEServicesDetailPreview($locale, $preview, $targetKey, $localizedContent);
        }

        return match ($targetKey) {
            'contact' => $this->renderContactPreview($locale, $preview, $localizedContent),
            'e_services' => $this->renderEServicesPreview($locale, $preview, $localizedContent),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $payload */
    private function renderNewsArticlePreview(string $locale, PreviewDTO $preview, array $payload): View
    {
        $article = $this->newsArticleCmsService->buildPreview($payload, $locale);
        abort_if($article === null, 404);

        $path = '/'.$locale.'/news/'.$article->id;

        return view('public.news.show', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $path),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'article' => $article,
            'relatedArticles' => collect(),
            'adjacentArticles' => ['previous' => null, 'next' => null],
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => $path,
                'locale_paths' => ['ar' => '/ar/news/'.$article->id, 'en' => '/en/news/'.$article->id],
                'title' => $article->metaTitle ?? $article->title,
                'meta_description' => $article->metaDescription ?? $article->excerpt ?? $article->title,
                'og_title' => $article->ogTitle ?? $article->title,
                'og_description' => $article->ogDescription ?? $article->excerpt ?? $article->title,
                'og_image' => $article->ogImage,
                'robots' => 'noindex,nofollow',
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function renderAboutEntityPreview(string $locale, PreviewDTO $preview, string $targetKey, array $payload): View
    {
        if (str_starts_with($targetKey, 'entity.faculty-member.')) {
            $profile = $this->aboutEntityCmsService->buildFacultyMemberPreview($payload, $locale);
            abort_if($profile === null, 404);

            return view('public.about.profile', [
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $profile->path),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'profile' => $profile,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => $profile->path,
                    'locale_paths' => ['ar' => '/ar/about/profile/'.$profile->slug, 'en' => '/en/about/profile/'.$profile->slug],
                    'title' => $profile->seoTitle,
                    'meta_description' => $profile->seoDescription,
                    'og_title' => $profile->seoTitle,
                    'og_description' => $profile->seoDescription,
                    'og_image' => $profile->seoImage,
                    'robots' => 'noindex,nofollow',
                ]),
                'preview' => $preview,
            ]);
        }

        if (str_starts_with($targetKey, 'entity.person.')) {
            $profile = $this->aboutEntityCmsService->buildPersonPreview($payload, $locale);
            abort_if($profile === null, 404);

            return view('public.about.profile', [
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $profile->path),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'profile' => $profile,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => $profile->path,
                    'locale_paths' => ['ar' => '/ar/about/profile/'.$profile->slug, 'en' => '/en/about/profile/'.$profile->slug],
                    'title' => $profile->seoTitle,
                    'meta_description' => $profile->seoDescription,
                    'og_title' => $profile->seoTitle,
                    'og_description' => $profile->seoDescription,
                    'og_image' => $profile->seoImage,
                    'robots' => 'noindex,nofollow',
                ]),
                'preview' => $preview,
            ]);
        }

        if (str_starts_with($targetKey, 'entity.directorate.')) {
            $directorate = $this->aboutEntityCmsService->buildDirectoratePreview($payload, $locale);
            abort_if($directorate === null, 404);
            $path = '/'.$locale.'/about/directorates/'.$directorate->slug;

            return view('public.about.directorate-detail', [
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $path),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'directorate' => $directorate,
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => $path,
                    'locale_paths' => ['ar' => '/ar/about/directorates/'.$directorate->slug, 'en' => '/en/about/directorates/'.$directorate->slug],
                    'title' => $directorate->title,
                    'meta_description' => $directorate->summary,
                    'robots' => 'noindex,nofollow',
                ]),
                'preview' => $preview,
            ]);
        }

        if (str_starts_with($targetKey, 'entity.partnership.')) {
            $partnership = $this->aboutEntityCmsService->buildPartnershipPreview($payload, $locale);
            $page = $this->aboutPageService->getContentPage('partnerships', $locale);
            abort_if($partnership === null || $page === null, 404);
            $directory = $this->aboutPageService->getPartnerships($locale);
            $partnerships = $directory->items
                ->reject(fn ($item): bool => $item->id === $partnership->id)
                ->push($partnership)
                ->values();
            $directory = new PartnershipDirectoryDTO(
                items: $partnerships,
                categories: $directory->categories,
                activeCategory: '',
                query: '',
                currentPage: 1,
                totalPages: 1,
                totalItems: $partnerships->count(),
                perPage: max($partnerships->count(), 1),
            );

            return view('public.about.partnerships', [
                'locale' => $locale,
                'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/partnerships'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'page' => $page,
                'directory' => $directory,
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/partnerships',
                    'locale_paths' => ['ar' => '/ar/about/partnerships', 'en' => '/en/about/partnerships'],
                    'title' => $page->title,
                    'meta_description' => $page->summary,
                    'robots' => 'noindex,nofollow',
                ]),
                'preview' => $preview,
            ]);
        }

        abort(404);
    }

    /** @param array<string, mixed> $content */
    private function renderResearchLandingPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->researchPageService->buildPreviewLanding($locale, $content);

        return view('public.research.index', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/research'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/research',
                'locale_paths' => ['ar' => '/ar/research', 'en' => '/en/research'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderResearchExpertsPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->researchPageService->buildPreviewExperts($locale, $content);

        return view('public.research.expert-finder', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/research/expert-finder'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/research/expert-finder',
                'locale_paths' => ['ar' => '/ar/research/expert-finder', 'en' => '/en/research/expert-finder'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderResearchPublicationsPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->researchPageService->buildPreviewPublications($locale, $content);

        return view('public.research.publications.index', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/research/publications'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/research/publications',
                'locale_paths' => ['ar' => '/ar/research/publications', 'en' => '/en/research/publications'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderResearchCentersPreview(Request $request, string $locale, PreviewDTO $preview, array $content): View
    {
        $centerSlug = is_string($request->query('center')) ? trim((string) $request->query('center')) : '';

        if ($centerSlug !== '') {
            $page = $this->researchPageService->buildPreviewCenter($locale, $content, $centerSlug);
            abort_if($page === null, 404);

            return view('public.research.centers.show', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/research/centers/'.$centerSlug),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale, ['center' => $centerSlug]),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/research/centers/'.$centerSlug,
                    'locale_paths' => ['ar' => '/ar/research/centers/'.$centerSlug, 'en' => '/en/research/centers/'.$centerSlug],
                    'title' => $page->seoTitle,
                    'meta_description' => $page->seoDescription,
                    'og_title' => $page->seoTitle,
                    'og_description' => $page->seoDescription,
                    'og_image' => $page->seoImage,
                ]),
                'page' => $page,
                'preview' => $preview,
            ]);
        }

        return $this->renderResearchTargetPreview($locale, $preview, 'research.centers', $content, 'public.research.centers.index', '/research/centers');
    }

    /** @param array<string, mixed> $content */
    private function renderResearchProjectsPreview(Request $request, string $locale, PreviewDTO $preview, array $content): View
    {
        $slug = is_string($request->query('project')) ? trim((string) $request->query('project')) : '';
        if ($slug !== '') {
            $page = $this->researchPageService->buildPreviewProject($locale, $content, $slug);
            abort_if($page === null, 404);

            return $this->renderResearchDetailPreview($locale, $preview, $page, 'public.research.projects.show', '/research/projects/'.$slug, ['project' => $slug]);
        }

        $page = $this->researchPageService->buildPreviewProjects($locale, $content, $request->only(['q', 'status', 'faculty', 'theme', 'page']));

        return $this->renderResearchPagePreview($locale, $preview, $page, 'public.research.projects.index', '/research/projects');
    }

    /** @param array<string, mixed> $content */
    private function renderResearchThemesPreview(Request $request, string $locale, PreviewDTO $preview, array $content): View
    {
        $slug = is_string($request->query('theme')) ? trim((string) $request->query('theme')) : '';
        if ($slug !== '') {
            $page = $this->researchPageService->buildPreviewTheme($locale, $content, $slug);
            abort_if($page === null, 404);

            return $this->renderResearchDetailPreview($locale, $preview, $page, 'public.research.themes.show', '/research/themes/'.$slug, ['theme' => $slug]);
        }

        return $this->renderResearchTargetPreview($locale, $preview, 'research.themes', $content, 'public.research.themes.index', '/research/themes');
    }

    private function renderResearchPagePreview(string $locale, PreviewDTO $preview, ResearchPageDTO $page, string $view, string $path): View
    {
        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.$path),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.$path,
                'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, string> $query */
    private function renderResearchDetailPreview(string $locale, PreviewDTO $preview, ResearchDetailPageDTO $page, string $view, string $path, array $query): View
    {
        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.$path),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale, $query),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.$path,
                'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderResearchTargetPreview(string $locale, PreviewDTO $preview, string $targetKey, array $content, string $view, string $path): View
    {
        $page = $this->researchPageService->buildPreviewTarget($targetKey, $locale, $content);

        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.$path),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.$path,
                'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderFacilitiesHubPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->facultyPageService->buildPreviewHub($locale, $content);

        return view('public.facilities.index', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/facilities'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/facilities',
                'locale_paths' => ['ar' => '/ar/facilities', 'en' => '/en/facilities'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderFacultySubpagePreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        $parts = explode('.', $targetKey);
        $facultySlug = $parts[1] ?? '';
        $subpageSlug = ($parts[2] ?? '') === 'study_plan' ? 'study-plan' : ($parts[2] ?? '');
        $page = $this->facultyPageService->buildPreviewSubpage($facultySlug, $subpageSlug, $locale, $content);
        abort_if($page === null, 404);

        return view('public.faculties.subpage', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/facilities/'.$page->facultySlug.'/'.$page->subpageSlug),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/facilities/'.$page->facultySlug.'/'.$page->subpageSlug,
                'locale_paths' => [
                    'ar' => '/ar/facilities/'.$page->facultySlug.'/'.$page->subpageSlug,
                    'en' => '/en/facilities/'.$page->facultySlug.'/'.$page->subpageSlug,
                ],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderFacultyHomepagePreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        $facultySlug = substr($targetKey, strlen('facilities.'));
        $page = $this->facultyPageService->buildPreviewFaculty($facultySlug, $locale, $content);
        abort_if($page === null, 404);

        return view('public.facilities.index', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/facilities/'.$page->slug),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/facilities/'.$page->slug,
                'locale_paths' => ['ar' => '/ar/facilities/'.$page->slug, 'en' => '/en/facilities/'.$page->slug],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderCampusLifeSectionPreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        $page = $this->campusLifePageService->buildPreviewSection($targetKey, $locale, $content);
        abort_if($page === null, 404);

        return view('public.campus-life.section', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/campus-life/'.$page->sectionSlug),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/campus-life/'.$page->sectionSlug,
                'locale_paths' => [
                    'ar' => '/ar/campus-life/'.$page->sectionSlug,
                    'en' => '/en/campus-life/'.$page->sectionSlug,
                ],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderCampusLifeJobsPreview(Request $request, string $locale, PreviewDTO $preview, array $content): View
    {
        $slug = is_string($request->query('job')) ? trim((string) $request->query('job')) : '';

        if ($slug !== '') {
            $page = $this->campusLifePageService->buildPreviewCareerJob($locale, $content, $slug);
            abort_if($page === null, 404);
            $job = is_array($page->section['job'] ?? null) ? $page->section['job'] : [];
            $path = '/'.$locale.'/campus-life/career-development/jobs/'.$slug;

            return view('public.campus-life.job-detail', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $path),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale, ['job' => $slug]),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => $path,
                    'locale_paths' => ['ar' => '/ar/campus-life/career-development/jobs/'.$slug, 'en' => '/en/campus-life/career-development/jobs/'.$slug],
                    'title' => $page->seoTitle,
                    'meta_description' => $page->seoDescription,
                    'og_title' => $page->seoTitle,
                    'og_description' => $page->seoDescription,
                    'og_image' => $page->seoImage,
                    'robots' => 'noindex,nofollow',
                ]),
                'structuredData' => $this->previewJobStructuredData($locale, $job),
                'page' => $page,
                'preview' => $preview,
            ]);
        }

        $page = $this->campusLifePageService->buildPreviewCareerJobs($locale, $content, $request->only(['q', 'category', 'type', 'page']));
        $filters = is_array($page->section['activeFilters'] ?? null) ? $page->section['activeFilters'] : [];
        $query = array_filter($filters, static fn (mixed $value, string $key): bool => is_scalar($value) && $value !== '' && $value !== 'all' && ! ($key === 'page' && (int) $value <= 1), ARRAY_FILTER_USE_BOTH);

        return view('public.campus-life.job-board', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/campus-life/career-development/jobs'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale, $query),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/campus-life/career-development/jobs',
                'locale_paths' => ['ar' => '/ar/campus-life/career-development/jobs', 'en' => '/en/campus-life/career-development/jobs'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $job @return array<string, mixed> */
    private function previewJobStructuredData(string $locale, array $job): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => (string) ($job['title'] ?? ''),
            'description' => implode("\n", array_values(array_filter(is_array($job['overview'] ?? null) ? $job['overview'] : [], 'is_string'))),
            'datePosted' => (string) ($job['postedDate'] ?? ''),
            'validThrough' => (string) ($job['closeDate'] ?? ''),
            'inLanguage' => $locale,
            'url' => url('/'.$locale.'/campus-life/career-development/jobs/'.($job['slug'] ?? '')),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Syrian Private University'),
            ],
        ];
    }

    /** @param array<string, mixed> $content */
    private function renderCampusLifeLandingPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->campusLifePageService->buildPreviewLanding($locale, $content);

        return view('public.campus-life.landing', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/campus-life'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/campus-life',
                'locale_paths' => ['ar' => '/ar/campus-life', 'en' => '/en/campus-life'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderVirtualTourPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->virtualTourPageService->buildPreviewPage($locale, $content);

        return view('public.virtual-tour.show', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/virtual-tour'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/virtual-tour',
                'locale_paths' => ['ar' => '/ar/virtual-tour', 'en' => '/en/virtual-tour'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderNewsIndexPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->newsService->buildPreviewIndexPage($locale, $content);
        $latest = $this->newsService->getLatestArticleCards($locale, 5, 'news');
        $announcements = $this->newsService->getLatestArticleCards($locale, 3, 'announcement');
        $featured = $this->newsService->getFeaturedArticles($locale, 1)->first() ?? $latest->first() ?? $announcements->first();

        return view('public.news.index', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/news'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'page' => $page,
            'featured' => $featured,
            'lastNews' => $latest,
            'announcements' => $announcements,
            'events' => $this->newsService->listNewsEvents($locale)->take(3)->values(),
            'pageTitle' => (string) ($page['pageTitle'] ?? ''),
            'pageDescription' => (string) ($page['pageDescription'] ?? ''),
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/news',
                'locale_paths' => ['ar' => '/ar/news', 'en' => '/en/news'],
                'title' => (string) ($page['pageTitle'] ?? ''),
                'meta_description' => (string) ($page['pageDescription'] ?? ''),
                'og_title' => (string) ($page['pageTitle'] ?? ''),
                'og_description' => (string) ($page['pageDescription'] ?? ''),
                'og_image' => (string) ($page['heroImage'] ?? ''),
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderNewsAnnouncementsPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->newsService->buildPreviewAnnouncementsPage($locale, $content);
        $featured = $this->newsService->getFeaturedArticles($locale, 1, 'announcement')->first();

        return view('public.news.announcements', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/news/announcements'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'page' => $page,
            'featured' => $featured,
            'announcements' => $this->newsService->listPublicArticles($locale, [
                'categoryType' => 'announcement',
                'excludeId' => $featured?->id,
            ], 1, 4),
            'categories' => $this->newsService->getPublicCategories($locale, 'announcement'),
            'activeCategory' => null,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/news/announcements',
                'locale_paths' => ['ar' => '/ar/news/announcements', 'en' => '/en/news/announcements'],
                'title' => (string) $page['pageTitle'],
                'meta_description' => (string) $page['pageDescription'],
                'og_title' => (string) $page['pageTitle'],
                'og_description' => (string) $page['pageDescription'],
                'og_image' => (string) $page['heroImage'],
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderNewsArticlesPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->newsService->buildPreviewArticlesPage($locale, $content);

        return view('public.news.articles', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/news/articles'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'page' => $page,
            'articles' => $this->newsService->listPublicArticles($locale, [], 1, 9),
            'categories' => $this->newsService->getPublicCategories($locale),
            'activeCategory' => null,
            'search' => '',
            'pageTitle' => (string) $page['title'],
            'pageDescription' => (string) $page['summary'],
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/news/articles',
                'locale_paths' => ['ar' => '/ar/news/articles', 'en' => '/en/news/articles'],
                'title' => (string) $page['seoTitle'],
                'meta_description' => (string) $page['seoDescription'],
                'og_image' => (string) $page['seoImage'],
                'robots' => 'noindex,nofollow',
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderNewsEventsPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->newsService->buildPreviewEventsPage($locale, $content);

        return view('public.news.events-list', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/news/events-list'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'page' => $page,
            'activeCategory' => null,
            'upcomingEvents' => $this->newsService->listPreviewNewsEvents($locale, $content),
            'pastEvents' => $this->newsService->listPreviewNewsEvents($locale, $content, true),
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/news/events-list',
                'locale_paths' => ['ar' => '/ar/news/events-list', 'en' => '/en/news/events-list'],
                'title' => (string) $page['title'],
                'meta_description' => (string) $page['summary'],
                'og_title' => (string) $page['title'],
                'og_description' => (string) $page['summary'],
                'og_image' => (string) $page['heroImage'],
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderNewsGalleryPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $listing = $this->newsService->buildPreviewGalleryListing($locale, $content);
        $page = $listing['page'];

        return view('public.news.gallery', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/news/gallery'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'page' => $page,
            'featured' => $listing['featured'],
            'galleryItems' => $listing['items'],
            'activeCategory' => null,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/news/gallery',
                'locale_paths' => ['ar' => '/ar/news/gallery', 'en' => '/en/news/gallery'],
                'title' => (string) $page['title'],
                'meta_description' => (string) $page['summary'],
                'og_title' => (string) $page['title'],
                'og_description' => (string) $page['summary'],
                'og_image' => (string) $page['heroImage'],
            ]),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderAboutContentPreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        if ($targetKey === 'about.vision-mission') {
            $page = $this->aboutPageService->buildPreviewVisionMission($locale, $content);

            return view('public.about.vision-mission', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/vision-mission'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/vision-mission',
                    'locale_paths' => ['ar' => '/ar/about/vision-mission', 'en' => '/en/about/vision-mission'],
                    'title' => $page->seoTitle,
                    'meta_description' => $page->seoDescription,
                    'og_title' => $page->seoTitle,
                    'og_description' => $page->seoDescription,
                    'og_image' => $page->seoImage,
                ]),
                'page' => $page,
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.vision-mission'),
                'preview' => $preview,
            ]);
        }

        $page = $this->aboutPageService->buildPreviewContentPage($targetKey, $locale, $content);
        abort_if($page === null, 404);

        if ($page->slug === 'leadership') {
            return view('public.about.leadership', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/leadership'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/leadership',
                    'locale_paths' => ['ar' => '/ar/about/leadership', 'en' => '/en/about/leadership'],
                    'title' => $page->title,
                    'meta_description' => $page->summary,
                    'og_title' => $page->title,
                    'og_description' => $page->summary,
                    'og_image' => $page->heroImage,
                ]),
                'page' => $page,
                'directory' => $this->aboutPageService->getLeadershipDirectory($locale),
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'preview' => $preview,
            ]);
        }

        if ($page->slug === 'directorates') {
            return view('public.about.directorates', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/directorates'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/directorates',
                    'locale_paths' => ['ar' => '/ar/about/directorates', 'en' => '/en/about/directorates'],
                    'title' => $page->title,
                    'meta_description' => $page->summary,
                    'og_title' => $page->title,
                    'og_description' => $page->summary,
                    'og_image' => $page->heroImage,
                ]),
                'page' => $page,
                'directorates' => $this->aboutPageService->getDirectorates($locale),
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'preview' => $preview,
            ]);
        }

        if ($page->slug === 'directorates_staff') {
            return view('public.about.staff', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/directorates/staff'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/directorates/staff',
                    'locale_paths' => ['ar' => '/ar/about/directorates/staff', 'en' => '/en/about/directorates/staff'],
                    'title' => $page->title,
                    'meta_description' => $page->summary,
                    'og_title' => $page->title,
                    'og_description' => $page->summary,
                    'og_image' => $page->heroImage,
                ]),
                'page' => $page,
                'directory' => $this->aboutPageService->getStaffDirectory($locale),
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'preview' => $preview,
            ]);
        }

        if ($page->slug === 'partnerships') {
            return view('public.about.partnerships', [
                'locale' => $locale,
                'direction' => $page->direction,
                'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/partnerships'),
                'settings' => $this->settingsService->getPublicSettings($locale),
                'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
                'isPreview' => true,
                'seo' => $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale.'/about/partnerships',
                    'locale_paths' => ['ar' => '/ar/about/partnerships', 'en' => '/en/about/partnerships'],
                    'title' => $page->title,
                    'meta_description' => $page->summary,
                    'og_title' => $page->title,
                    'og_description' => $page->summary,
                    'og_image' => $page->heroImage,
                ]),
                'page' => $page,
                'directory' => $this->aboutPageService->getPartnerships($locale),
                'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
                'preview' => $preview,
            ]);
        }

        return view('public.about.content-page', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about/'.$page->slug),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/about/'.$page->slug,
                'locale_paths' => ['ar' => '/ar/about/'.$page->slug, 'en' => '/en/about/'.$page->slug],
                'title' => $page->title,
                'meta_description' => $page->summary,
                'og_title' => $page->title,
                'og_description' => $page->summary,
                'og_image' => $page->heroImage,
            ]),
            'page' => $page,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale),
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderAboutLandingPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $about = $this->aboutPageService->buildPreviewAboutLanding($locale, $content);

        return view('public.about.landing', [
            'locale' => $locale,
            'direction' => $about->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/about'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/about',
                'locale_paths' => ['ar' => '/ar/about', 'en' => '/en/about'],
                'title' => $about->title,
                'meta_description' => $about->summary,
                'og_title' => $about->title,
                'og_description' => $about->summary,
                'og_image' => $about->imagePrimary,
            ]),
            'about' => $about,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderAdmissionsLandingPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->admissionsPageService->buildPreviewLanding($locale, $content);

        return view('public.admissions.landing', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/admissions'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->admissionsLanguageSwitchLinks($locale, $preview->token),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/admissions',
                'locale_paths' => ['ar' => '/ar/admissions', 'en' => '/en/admissions'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderAdmissionsSectionPreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        $page = $this->admissionsPageService->buildPreviewSection($targetKey, $locale, $content);
        abort_if($page === null, 404);

        return view('public.admissions.section', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/admissions/'.$page->sectionSlug),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->admissionsLanguageSwitchLinks($locale, $preview->token),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/admissions/'.$page->sectionSlug,
                'locale_paths' => [
                    'ar' => '/ar/admissions/'.$page->sectionSlug,
                    'en' => '/en/admissions/'.$page->sectionSlug,
                ],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderContactPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $contact = $this->contactPageService->buildPreviewPage($locale, $content);

        return view('public.contact', [
            'locale' => $locale,
            'direction' => $contact->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/contact'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/contact',
                'locale_paths' => ['ar' => '/ar/contact', 'en' => '/en/contact'],
                'title' => $contact->seoTitle,
                'meta_description' => $contact->seoDescription,
                'og_title' => $contact->seoTitle,
                'og_description' => $contact->seoDescription,
                'og_image' => $contact->seoImage,
            ]),
            'contact' => $contact,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderEServicesPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->eServicesPageService->buildPreviewPage($locale, $content);

        return view('public.e-services', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale.'/e-services'),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.'/e-services',
                'locale_paths' => ['ar' => '/ar/e-services', 'en' => '/en/e-services'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderEServicesDetailPreview(string $locale, PreviewDTO $preview, string $targetKey, array $content): View
    {
        $slug = substr($targetKey, strlen('e_services.'));
        $page = $this->eServicesPageService->buildDetailPreviewPage($locale, $slug, $content);
        $path = '/'.$locale.'/e-services/'.$slug;

        return view('public.e-services-detail', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, ltrim($path, '/')),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => $path,
                'locale_paths' => ['ar' => '/ar/e-services/'.$slug, 'en' => '/en/e-services/'.$slug],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_title' => $page->seoTitle,
                'og_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, mixed> $content */
    private function renderSuggestionsComplaintsPreview(string $locale, PreviewDTO $preview, array $content): View
    {
        $page = $this->eServicesPageService->buildSuggestionsComplaintsPreviewPage($locale, $content);
        $path = '/'.$locale.'/e-services/suggestions-complaints';

        return view('public.e-services-suggestions-complaints', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, ltrim($path, '/')),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->cmsLanguageSwitchLinks($preview->token, $locale),
            'isPreview' => true,
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => $path,
                'locale_paths' => ['ar' => '/ar/e-services/suggestions-complaints', 'en' => '/en/e-services/suggestions-complaints'],
                'title' => $page->seoTitle,
                'meta_description' => $page->seoDescription,
                'og_image' => $page->seoImage,
                'robots' => 'noindex,nofollow',
            ]),
            'page' => $page,
            'preview' => $preview,
        ]);
    }

    private function resolvePreviewToken(Request $request): ?string
    {
        foreach ([(string) $request->query('token'), (string) $request->query('preview_token'), (string) $request->header('X-Preview-Token')] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(PageDTO $page, PageTranslationDTO $translation): array
    {
        $bodyBlocks = is_array($translation->bodyPayload['blocks'] ?? null)
            ? array_values(array_filter($translation->bodyPayload['blocks'], static fn (mixed $block): bool => is_array($block)))
            : [];

        return [
            'id' => $page->id,
            'title' => $translation->title,
            'navigationLabel' => $translation->navigationLabel,
            'headline' => $translation->headline,
            'subheadline' => $translation->subheadline,
            'hero' => $translation->heroPayload,
            'overviewCards' => $translation->overviewCardsPayload ?? [],
            'stats' => $translation->statsPayload ?? [],
            'bodyBlocks' => $bodyBlocks,
            'body' => $translation->body,
            'excerpt' => $translation->excerpt,
            'cta' => $translation->ctaPayload,
            'sidebar' => $translation->sidebarPayload,
        ];
    }

    /**
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function homepageLanguageSwitchLinks(string $locale, string $token): array
    {
        return array_map(
            static fn (string $candidateLocale): LanguageSwitchLinkDTO => new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale.'/preview?token='.$token,
                isCurrent: $candidateLocale === $locale,
            ),
            ['ar', 'en']
        );
    }

    /**
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function pageLanguageSwitchLinks(int $pageId, string $locale, string $token): array
    {
        $links = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $links[] = new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale.'/preview?token='.$token,
                isCurrent: $candidateLocale === $locale,
            );
        }

        return $links;
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function cmsLanguageSwitchLinks(string $token, string $locale, array $query = []): array
    {
        $queryString = $query !== [] ? '&'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return array_map(
            static fn (string $candidateLocale): LanguageSwitchLinkDTO => new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale.'/preview?token='.$token.$queryString,
                isCurrent: $candidateLocale === $locale,
            ),
            ['ar', 'en']
        );
    }

    private function isFacultyHomepageTarget(string $targetKey): bool
    {
        if (! str_starts_with($targetKey, 'facilities.')) {
            return false;
        }

        $suffix = substr($targetKey, strlen('facilities.'));

        return $suffix !== 'landing' && ! str_contains($suffix, '.');
    }

    private function isFacultySubpageTarget(string $targetKey): bool
    {
        if (! str_starts_with($targetKey, 'facilities.')) {
            return false;
        }

        return count(explode('.', $targetKey)) === 3;
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function admissionsLanguageSwitchLinks(string $locale, string $token): array
    {
        return $this->cmsLanguageSwitchLinks($token, $locale);
    }
}
