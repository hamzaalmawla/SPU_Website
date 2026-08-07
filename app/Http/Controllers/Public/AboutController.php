<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\ProfilePageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AboutController extends Controller
{
    public function __construct(
        private readonly AboutPageServiceInterface $aboutPageService,
        private readonly ProfilePageServiceInterface $profilePageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $about = $this->aboutPageService->getAboutLanding($locale);

        return view('public.about.landing', $this->sharedPayload($request, $locale, '/about', [
            'about' => $about,
            'seo' => $this->seo($locale, '/about', $about->seoTitle, $about->seoDescription, $about->seoImage),
        ]));
    }

    public function history(Request $request, string $locale): View
    {
        return $this->contentPage($request, $locale, 'history');
    }

    public function visionMission(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getVisionMission($locale);
        $path = '/about/vision-mission';

        return view('public.about.vision-mission', $this->sharedPayload($request, $locale, $path, [
            'page' => $page,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.vision-mission'),
            'seo' => $this->seo($locale, $path, $page->seoTitle, $page->seoDescription, $page->seoImage),
        ]));
    }

    public function content(Request $request, string $locale, string $section): View
    {
        return $this->contentPage($request, $locale, $section);
    }

    public function redirectUniversityCouncil(string $locale): RedirectResponse
    {
        return redirect('/'.$locale.'/about/leadership', 301);
    }

    public function redirectPartnershipAlias(string $locale): RedirectResponse
    {
        return redirect('/'.$locale.'/about/partnerships', 301);
    }

    public function leadership(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getContentPage('leadership', $locale);
        abort_if($page === null, 404);
        $requestedFaculty = $request->query('faculty');
        $directory = $this->aboutPageService->getLeadershipDirectory(
            $locale,
            is_string($requestedFaculty) ? $requestedFaculty : null,
        );
        $languagePath = '/about/leadership'.($directory->activeFaculty !== '' ? '?faculty='.rawurlencode($directory->activeFaculty) : '');

        return view('public.about.leadership', $this->sharedPayload($request, $locale, $languagePath, [
            'page' => $page,
            'directory' => $directory,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.leadership'),
            'seo' => $this->seo($locale, '/about/leadership', $page->title, $page->summary),
        ]));
    }

    public function directorates(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getContentPage('directorates', $locale);
        abort_if($page === null, 404);

        return view('public.about.directorates', $this->sharedPayload($request, $locale, '/about/directorates', [
            'page' => $page,
            'directorates' => $this->aboutPageService->getDirectorates($locale),
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.directorates'),
            'seo' => $this->seo($locale, '/about/directorates', $page->title, $page->summary),
        ]));
    }

    public function directorateDetail(Request $request, string $locale, string $directorate): RedirectResponse|View
    {
        if ($directorate === 'it-services') {
            return redirect('/' . $locale . '/e-services/it-support', 301);
        }

        $item = $this->aboutPageService->getDirectorate($directorate, $locale);
        abort_if($item === null, 404);

        return view('public.about.directorate-detail', $this->sharedPayload($request, $locale, '/about/directorates/'.$directorate, [
            'directorate' => $item,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.directorates'),
            'seo' => $this->seo($locale, '/about/directorates/'.$directorate, $item->title, $item->summary),
        ]));
    }

    public function staffDirectory(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getStaffDirectoryPage($locale);
        $requestedFaculty = $request->query('faculty');
        $requestedPage = $request->query('page');
        $directory = $this->aboutPageService->getStaffDirectory(
            $locale,
            is_string($requestedFaculty) ? $requestedFaculty : null,
            is_string($requestedPage) && ctype_digit($requestedPage) ? (int) $requestedPage : 1,
        );
        $query = array_filter([
            'faculty' => $directory->activeFaculty !== '' ? $directory->activeFaculty : null,
            'page' => $directory->currentPage > 1 ? $directory->currentPage : null,
        ], fn (mixed $value): bool => $value !== null);
        $languagePath = '/about/directorates/staff'.($query !== [] ? '?'.http_build_query($query) : '');

        return view('public.about.staff', $this->sharedPayload($request, $locale, $languagePath, [
            'page' => $page,
            'directory' => $directory,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.directorates_staff'),
            'seo' => $this->seo($locale, '/about/directorates/staff', $page->title, $page->summary),
        ]));
    }

    public function partnerships(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getContentPage('partnerships', $locale);
        abort_if($page === null, 404);
        $requestedCategory = $request->query('category');
        $requestedQuery = $request->query('q');
        $requestedPage = $request->query('page');
        $directory = $this->aboutPageService->getPartnerships(
            $locale,
            is_string($requestedCategory) ? $requestedCategory : null,
            is_string($requestedQuery) ? $requestedQuery : null,
            is_string($requestedPage) && ctype_digit($requestedPage) ? (int) $requestedPage : 1,
        );
        $query = array_filter([
            'category' => $directory->activeCategory !== '' ? $directory->activeCategory : null,
            'q' => $directory->query !== '' ? $directory->query : null,
            'page' => $directory->currentPage > 1 ? $directory->currentPage : null,
        ], fn (mixed $value): bool => $value !== null);
        $languagePath = '/about/partnerships'.($query !== [] ? '?'.http_build_query($query) : '');

        return view('public.about.partnerships', $this->sharedPayload($request, $locale, $languagePath, [
            'page' => $page,
            'directory' => $directory,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.partnerships'),
            'seo' => $this->seo($locale, '/about/partnerships', $page->title, $page->summary),
        ]));
    }

    public function profile(Request $request, string $locale, string $source, string $slug): View
    {
        $profile = $this->profilePageService->getProfile($locale, $source, $slug);
        abort_if($profile === null, 404);

        $path = '/about/profile/'.$source.'/'.$slug;

        return view('public.about.profile', $this->sharedPayload($request, $locale, $path, [
            'profile' => $profile,
            'seo' => $this->seo($locale, $path, $profile->seoTitle, $profile->seoDescription, $profile->seoImage),
        ]));
    }

    public function redirectLegacyProfile(Request $request, string $locale): RedirectResponse
    {
        $identifier = $request->query('slug', $request->query('id'));
        abort_unless(is_string($identifier) && trim($identifier) !== '', 404);

        $profile = $this->profilePageService->resolveLegacyProfile($locale, trim($identifier));
        abort_if($profile === null, 404);
        $source = $profile->sourceType === 'faculty_member' ? 'faculty-member' : 'person';

        return redirect('/'.$locale.'/about/profile/'.$source.'/'.$profile->slug, 301);
    }

    private function contentPage(Request $request, string $locale, string $slug): View
    {
        $page = $this->aboutPageService->getContentPage($slug, $locale);
        abort_if($page === null, 404);

        return view('public.about.content-page', $this->sharedPayload($request, $locale, '/about/'.$slug, [
            'page' => $page,
            'aboutNavigationCards' => $this->aboutPageService->getAboutSubPages($locale, 'about.'.$slug),
            'seo' => $this->seo($locale, '/about/'.$slug, $page->seoTitle, $page->seoDescription, $page->seoImage),
        ]));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function sharedPayload(Request $request, string $locale, string $path, array $payload): array
    {
        $seo = $payload['seo'] ?? null;

        return array_merge([
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $path),
            'isPreview' => false,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => is_object($seo) && isset($seo->title) ? $seo->title : config('app.name', 'SPU'),
                'url' => is_object($seo) && isset($seo->canonicalUrl) ? $seo->canonicalUrl : url('/'.$locale.$path),
                'inLanguage' => $locale,
                'breadcrumb' => [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'ar' ? 'الرئيسية' : 'Home', 'item' => url('/'.$locale)],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $locale === 'ar' ? 'عن الجامعة' : 'About', 'item' => url('/'.$locale.'/about')],
                    ],
                ],
            ],
        ], $payload);
    }

    private function seo(string $locale, string $path, string $title, string $description, ?string $image = null): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.$path,
            'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image ?? '/images/about-hero-1.webp',
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $path): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$path, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en'.$path, $locale === 'en'),
        ];
    }
}
