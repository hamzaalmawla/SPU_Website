<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
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
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $about = $this->aboutPageService->getAboutLanding($locale);

        return view('public.about.landing', $this->sharedPayload($request, $locale, '/about', [
            'about' => $about,
            'seo' => $this->seo($locale, '/about', $about->title, $about->summary),
        ]));
    }

    public function history(Request $request, string $locale): View
    {
        return $this->contentPage($request, $locale, 'history');
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

        return view('public.about.leadership', $this->sharedPayload($request, $locale, '/about/leadership', [
            'page' => $page,
            'people' => $this->aboutPageService->getLeadershipProfiles($locale),
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
            'seo' => $this->seo($locale, '/about/directorates', $page->title, $page->summary),
        ]));
    }

    public function directorateDetail(Request $request, string $locale, string $directorate): View
    {
        $item = $this->aboutPageService->getDirectorate($directorate, $locale);
        abort_if($item === null, 404);

        return view('public.about.directorate-detail', $this->sharedPayload($request, $locale, '/about/directorates/'.$directorate, [
            'directorate' => $item,
            'seo' => $this->seo($locale, '/about/directorates/'.$directorate, $item->title, $item->summary),
        ]));
    }

    public function staffDirectory(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getStaffDirectoryPage($locale);

        return view('public.about.staff', $this->sharedPayload($request, $locale, '/about/directorates/staff', [
            'page' => $page,
            'people' => $this->aboutPageService->getLeadershipProfiles($locale),
            'seo' => $this->seo($locale, '/about/directorates/staff', $page->title, $page->summary),
        ]));
    }

    public function partnerships(Request $request, string $locale): View
    {
        $page = $this->aboutPageService->getContentPage('partnerships', $locale);
        abort_if($page === null, 404);

        return view('public.about.partnerships', $this->sharedPayload($request, $locale, '/about/partnerships', [
            'page' => $page,
            'partnerships' => $this->aboutPageService->getPartnerships($locale),
            'seo' => $this->seo($locale, '/about/partnerships', $page->title, $page->summary),
        ]));
    }

    private function contentPage(Request $request, string $locale, string $slug): View
    {
        $page = $this->aboutPageService->getContentPage($slug, $locale);
        abort_if($page === null, 404);

        return view('public.about.content-page', $this->sharedPayload($request, $locale, '/about/'.$slug, [
            'page' => $page,
            'seo' => $this->seo($locale, '/about/'.$slug, $page->title, $page->summary),
        ]));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function sharedPayload(Request $request, string $locale, string $path, array $payload): array
    {
        return array_merge([
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $path),
            'isPreview' => false,
        ], $payload);
    }

    private function seo(string $locale, string $path, string $title, string $description): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.$path,
            'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => '/images/about-hero-1.webp',
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
