<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\EServicesPageServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\EServicesPageDTO;
use App\DTOs\LanguageSwitchLinkDTO;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class EServicesController extends Controller
{
    public function __construct(
        private readonly EServicesPageServiceInterface $eServicesPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $page = $this->eServicesPageService->getPage($locale);

        return view('public.e-services', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page),
            'page' => $page,
        ]);
    }

    private function seo(string $locale, EServicesPageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/e-services',
            'locale_paths' => ['ar' => '/ar/e-services', 'en' => '/en/e-services'],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/e-services', $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/e-services', $locale === 'en'),
        ];
    }
}
