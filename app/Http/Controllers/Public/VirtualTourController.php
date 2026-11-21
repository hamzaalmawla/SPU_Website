<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\VirtualTour\VirtualTourPageDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class VirtualTourController extends Controller
{
    public function __construct(
        private readonly VirtualTourPageServiceInterface $virtualTourPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $page = $this->virtualTourPageService->getPage($locale);

        return view('public.virtual-tour.show', [
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

    private function seo(string $locale, VirtualTourPageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/virtual-tour',
            'locale_paths' => ['ar' => '/ar/virtual-tour', 'en' => '/en/virtual-tour'],
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
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/virtual-tour', $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/virtual-tour', $locale === 'en'),
        ];
    }
}
