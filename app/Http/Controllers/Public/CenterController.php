<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Center\CenterPageServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Center\CenterPageDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CenterController extends Controller
{
    public function __construct(
        private readonly CenterPageServiceInterface $centerPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $page = $this->centerPageService->getLanding($locale);

        return view('public.centers.landing', [
            ...$this->shell($request, $locale, ''),
            'seo' => $this->seo($locale, '', $page->seoTitle, $page->seoDescription, $page->seoImage),
            'page' => $page,
        ]);
    }

    public function show(Request $request, string $locale, string $center): View
    {
        return $this->render($request, $locale, $center, '');
    }

    public function subpage(Request $request, string $locale, string $center, string $subpage): View
    {
        return $this->render($request, $locale, $center, $subpage);
    }

    private function render(Request $request, string $locale, string $center, string $subpage): View
    {
        $page = $this->centerPageService->getPage($center, $subpage, $locale, $request->query());
        abort_if(! $page instanceof CenterPageDTO, 404);

        $suffix = '/'.$center.($subpage !== '' ? '/'.$subpage : '');
        $query = $page->filters !== [] ? '?'.http_build_query($page->filters) : '';

        return view($subpage === '' ? 'public.centers.show' : 'public.centers.subpage', [
            ...$this->shell($request, $locale, $suffix.$query),
            'seo' => $this->seo($locale, $suffix.$query, $page->seoTitle, $page->seoDescription, $page->seoImage),
            'page' => $page,
        ]);
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, string $locale, string $suffix): array
    {
        return [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => [
                new LanguageSwitchLinkDTO('ar', 'AR', '/ar/centers'.$suffix, $locale === 'ar'),
                new LanguageSwitchLinkDTO('en', 'EN', '/en/centers'.$suffix, $locale === 'en'),
            ],
            'isPreview' => false,
        ];
    }

    private function seo(string $locale, string $suffix, string $title, string $description, string $image): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/centers'.$suffix,
            'locale_paths' => ['ar' => '/ar/centers'.$suffix, 'en' => '/en/centers'.$suffix],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
        ]);
    }
}
