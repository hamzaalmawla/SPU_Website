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
use App\DTOs\LanguageSwitchLinkDTO;
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
        $homeShell = $this->pageService->getPublicPageBySlug('home', $locale);

        return view('public.home', [
            'locale' => $locale,
            'direction' => $homepage->direction,
            'homepage' => $homepage,
            'heroSection' => $this->findSection($homepage, 'hero'),
            'bodySections' => $this->bodySections($homepage),
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $homeShell !== null
                ? ($locale === 'ar' ? $homeShell->arabicSeo : $homeShell->englishSeo)
                : $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale,
                    'locale_paths' => ['ar' => '/ar', 'en' => '/en'],
                ]),
            'languageSwitch' => $this->languageSwitchLinks($locale),
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

    /**
     * @return array<int, HomepageSectionDTO>
     */
    private function bodySections(HomepageDTO $homepage): array
    {
        return array_values(array_filter(
            $homepage->sections,
            static fn (HomepageSectionDTO $section): bool => $section->key !== 'hero'
        ));
    }

    /**
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function languageSwitchLinks(string $locale): array
    {
        return array_map(
            static fn (string $candidateLocale): LanguageSwitchLinkDTO => new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale,
                isCurrent: $candidateLocale === $locale,
            ),
            ['ar', 'en']
        );
    }
}
