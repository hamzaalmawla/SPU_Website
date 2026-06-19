<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Admissions\AdmissionsPageDTO;
use App\DTOs\Admissions\AdmissionsSectionDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AdmissionsController extends Controller
{
    public function __construct(
        private readonly AdmissionsPageServiceInterface $admissionsPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $page = $this->admissionsPageService->getLanding($locale);

        return view('public.admissions.landing', [
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
        $page = $this->admissionsPageService->getSection($section, $locale);
        abort_if($page === null, 404);

        return view('public.admissions.section', [
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

    private function landingSeo(string $locale, AdmissionsPageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/admissions',
            'locale_paths' => ['ar' => '/ar/admissions', 'en' => '/en/admissions'],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function sectionSeo(string $locale, AdmissionsSectionDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
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
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix = ''): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/admissions'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/admissions'.$suffix, $locale === 'en'),
        ];
    }
}
