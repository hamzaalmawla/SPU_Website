<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Page\PageDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Preview\PreviewDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PreviewController extends Controller
{
    public function __construct(
        private readonly PreviewServiceInterface $previewService,
        private readonly PageServiceInterface $pageService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly NavigationServiceInterface $navigationService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $token = $this->resolvePreviewToken($request);

        abort_if($token === null, 404);

        $preview = $this->previewService->resolveToken($token, $locale);

        abort_if($preview === null, 404);

        if ($preview->payload->page instanceof PageDTO && ! $preview->payload->page->metadata->isHomepageShell) {
            return $this->renderPagePreview($locale, $preview);
        }

        return $this->renderHomepagePreview($locale, $preview);
    }

    private function renderHomepagePreview(string $locale, PreviewDTO $preview): View
    {
        $homepage = $preview->payload->homepage;
        abort_if($homepage === null, 404);

        $homeShell = $this->pageService->getPublicPageBySlug('home', $locale);

        return view('public.home', [
            'locale' => $locale,
            'direction' => $homepage->direction,
            'homepage' => $homepage,
            'homepageFooterSection' => $homepage->findSection('footer'),
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale, $locale),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $homeShell !== null
                ? ($locale === 'ar' ? $homeShell->arabicSeo : $homeShell->englishSeo)
                : $this->seoMetadataService->buildFallback($locale, [
                    'path' => '/'.$locale,
                    'locale_paths' => ['ar' => '/ar', 'en' => '/en'],
                ]),
            'languageSwitch' => $this->homepageLanguageSwitchLinks($locale, $preview->token),
            'isPreview' => true,
            'preview' => $preview,
        ]);
    }

    private function renderPagePreview(string $locale, PreviewDTO $preview): View
    {
        $page = $preview->payload->page;
        abort_if($page === null, 404);

        $translation = $locale === 'ar' ? $page->arabicTranslation : $page->englishTranslation;
        $seo = $locale === 'ar' ? $page->arabicSeo : $page->englishSeo;

        return view('public.page', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $preview->payload->navigation ?? $this->navigationService->getFullNavigationPayload($locale),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'seo' => $seo,
            'page' => $this->pagePayload($page, $translation),
            'breadcrumbs' => $this->pageService->buildBreadcrumbPayload($page->id, $locale),
            'languageSwitch' => $this->pageLanguageSwitchLinks($page->id, $locale, $preview->token),
            'isPreview' => true,
            'preview' => $preview,
        ]);
    }

    private function resolvePreviewToken(Request $request): ?string
    {
        foreach ([(string) $request->query('token'), (string) $request->query('preview_token'), (string) $request->header('X-Preview-Token')] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
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
            'slug' => $page->metadata->slug,
            'template' => $page->metadata->template,
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
    private function homepageLanguageSwitchLinks(string $locale, string $token): array
    {
        return array_map(
            static fn (string $candidateLocale): LanguageSwitchLinkDTO => new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale.'/preview?token='.$token,
                isCurrent: $candidateLocale === $locale,
            ),
            ['ar', 'en']
        );
    }

    /**
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function pageLanguageSwitchLinks(int $pageId, string $locale, string $token): array
    {
        $links = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $links[] = new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: '/'.$candidateLocale.'/preview?token='.$token,
                isCurrent: $candidateLocale === $locale,
            );
        }

        return $links;
    }
}
