<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CampusLifeController extends Controller
{
    public function __construct(
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $page = $this->campusLifePageService->getLanding($locale);

        return view('public.campus-life.landing', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale),
            'isPreview' => false,
            'seo' => $this->landingSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function section(Request $request, string $locale, string $section): View
    {
        $page = $this->campusLifePageService->getSection($section, $locale);
        abort_if($page === null, 404);

        return view('public.campus-life.section', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/'.$page->sectionSlug),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function careerJobBoard(Request $request, string $locale): View
    {
        $page = $this->campusLifePageService->getCareerJobBoard($locale);

        return view('public.campus-life.job-board', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs'),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function careerJobDetail(Request $request, string $locale, string $job): View
    {
        $page = $this->campusLifePageService->getCareerJobDetail($job, $locale);
        abort_if($page === null, 404);

        return view('public.campus-life.job-detail', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs/'.$job),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function careerJobApplication(Request $request, string $locale): View
    {
        $page = $this->campusLifePageService->getCareerJobApplication($locale);

        return view('public.campus-life.job-application', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs/apply'),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    private function landingSeo(string $locale, CampusLifePageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/campus-life',
            'locale_paths' => ['ar' => '/ar/campus-life', 'en' => '/en/campus-life'],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function sectionSeo(string $locale, CampusLifeSectionDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
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
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix = ''): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/campus-life'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/campus-life'.$suffix, $locale === 'en'),
        ];
    }
}
