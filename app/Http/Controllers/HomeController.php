<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\HomepageDTO;
use App\DTOs\HomepageSectionDTO;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController extends Controller
{
    public function __construct(
        private readonly HomepageSectionServiceInterface $homepageSectionService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly PageServiceInterface $pageService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $homepage = $this->homepageSectionService->getPublicHomepage($locale);
        abort_if(! $this->hasRenderableHomepage($homepage), 404);

        $navigation = $this->navigationService->getFullNavigationPayload($locale, $request->path());
        $homeShell = $this->pageService->getPublicPageBySlug('home', $locale);

        return view('public.home', [
            'locale' => $locale,
            'direction' => $homepage->direction,
            'homepage' => $homepage,
            'homepageFooterSection' => $this->findSection($homepage, 'footer'),
            'navigation' => $navigation,
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $homeShell !== null
                ? ($locale === 'ar' ? $homeShell->arabicSeo : $homeShell->englishSeo)
                : $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale,
                    'locale_paths' => ['ar' => '/ar', 'en' => '/en'],
                ]),
            'languageSwitch' => $navigation->languageSwitchLinks,
            'isPreview' => false,
        ]);
    }

    private function findSection(HomepageDTO $homepage, string $key): ?HomepageSectionDTO
    {
        foreach ($homepage->sections as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    private function hasRenderableHomepage(HomepageDTO $homepage): bool
    {
        return $homepage->sections !== [] && $this->findSection($homepage, 'hero') instanceof HomepageSectionDTO;
    }
}
