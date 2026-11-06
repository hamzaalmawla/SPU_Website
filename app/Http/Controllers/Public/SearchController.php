<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Search\SiteSearchServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public site-wide search results.
 *
 * The page is deliberately noindex,follow: a results page is thin, infinitely
 * variable content that search engines should not index, but its links to real
 * pages are worth following. It is also absent from the sitemap for the same
 * reason.
 */
final class SearchController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly SiteSearchServiceInterface $siteSearchService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $query = is_string($request->query('q')) ? (string) $request->query('q') : '';
        $type = is_string($request->query('type')) ? (string) $request->query('type') : 'all';

        $results = $this->siteSearchService->search(
            $locale,
            $query,
            $type,
            max(1, (int) $request->query('page', 1)),
            self::PER_PAGE,
        );

        $title = trim($query) === ''
            ? __('public.search_page_title')
            : __('public.search_results_for', ['query' => trim($query)]);

        return view('public.search', $this->sharedPayload($request, $locale, '/search', [
            'results' => $results,
            'types' => SiteSearchServiceInterface::TYPES,
            'pageTitle' => $title,
            'seo' => $this->seo($locale, '/search', $title, (string) __('public.search_page_description'), null, 'noindex,follow'),
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
            'languageSwitch' => $this->languageSwitchLinks($locale, $path, $request),
            'isPreview' => false,
        ], $payload);
    }

    private function seo(string $locale, string $path, string $title, string $description, ?string $image = null, string $robots = 'index,follow'): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.$path,
            'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'robots' => $robots,
        ]);
    }

    /**
     * Switching language must keep the visitor's query and filter, otherwise
     * the control silently throws their search away.
     *
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function languageSwitchLinks(string $locale, string $path, Request $request): array
    {
        $carried = array_filter(
            [
                'q' => is_string($request->query('q')) ? trim((string) $request->query('q')) : '',
                'type' => is_string($request->query('type')) ? (string) $request->query('type') : '',
            ],
            static fn (string $value): bool => $value !== '',
        );

        $suffix = $carried === [] ? '' : '?'.http_build_query($carried, '', '&', PHP_QUERY_RFC3986);

        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$path.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en'.$path.$suffix, $locale === 'en'),
        ];
    }
}
