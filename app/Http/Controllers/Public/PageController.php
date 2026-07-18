<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Page\PageDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PageController extends Controller
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function __invoke(Request $request, string $locale, string $slugPath): View
    {
        $page = $this->pageService->getPublicPageBySlug($slugPath, $locale);

        abort_if($page === null || $page->metadata->isHomepageShell, 404);

        $navigation = $this->navigationService->getFullNavigationPayload($locale, $request->path());
        $translation = $locale === 'ar' ? $page->arabicTranslation : $page->englishTranslation;
        $seo = $locale === 'ar' ? $page->arabicSeo : $page->englishSeo;

        return view('public.page', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $navigation,
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $seo,
            'page' => $this->pagePayload($page, $translation),
            'breadcrumbs' => $this->pageService->buildBreadcrumbPayload($page->id, $locale),
            'languageSwitch' => $this->languageSwitchLinks($page->id, $locale, '/'.trim($request->path(), '/')),
            'isPreview' => false,
        ]);
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
    private function languageSwitchLinks(int $pageId, string $locale, string $currentUrl): array
    {
        $links = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $links[] = new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: $candidateLocale === $locale
                    ? $currentUrl
                    : ($this->pageService->resolveLanguageSwitchTargetUrl($pageId, $candidateLocale) ?? '/'.$candidateLocale),
                isCurrent: $candidateLocale === $locale,
            );
        }

        return $links;
    }
}
