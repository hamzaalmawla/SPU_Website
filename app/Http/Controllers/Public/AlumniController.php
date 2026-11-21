<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Career\AlumniDirectoryServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Career\AlumniDirectoryPageDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AlumniController extends Controller
{
    public function __construct(
        private readonly AlumniDirectoryServiceInterface $alumniDirectoryService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function index(Request $request, string $locale): View
    {
        $page = $this->alumniDirectoryService->getDirectory(
            $locale,
            $request->only(['q', 'year', 'faculty', 'department', 'page']),
        );
        abort_if($page === null, 404);

        $query = $this->validatedQuery($page);
        $path = '/'.$locale.'/alumni'.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $seo = $this->seoMetadataService->buildFallback($locale, [
            'path' => $path,
            'locale_paths' => [
                'ar' => $this->localizedPath('ar', $query),
                'en' => $this->localizedPath('en', $query),
            ],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);

        return view('public.alumni.index', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitch($locale, $query),
            'seo' => $seo,
            'page' => $page,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $page->seoTitle,
                'description' => $page->seoDescription,
                'url' => $seo->canonicalUrl,
                'numberOfItems' => $page->pagination['total_items'] ?? 0,
                'inLanguage' => $locale,
            ],
            'isPreview' => false,
        ]);
    }

    /** @return array<string, string|int> */
    private function validatedQuery(AlumniDirectoryPageDTO $page): array
    {
        $query = collect($page->filters)
            ->except('page')
            ->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
        $currentPage = (int) ($page->pagination['current_page'] ?? 1);

        return $currentPage > 1 ? [...$query, 'page' => $currentPage] : $query;
    }

    /** @param array<string, string|int> $query */
    private function localizedPath(string $locale, array $query): string
    {
        $queryString = $query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return '/'.$locale.'/alumni'.$queryString;
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitch(string $locale, array $query): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', $this->localizedPath('ar', $query), $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', $this->localizedPath('en', $query), $locale === 'en'),
        ];
    }
}
