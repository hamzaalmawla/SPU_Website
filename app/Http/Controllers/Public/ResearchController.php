<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Research\ResearchDetailPageDTO;
use App\DTOs\Research\ResearchPageDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ResearchController extends Controller
{
    public function __construct(
        private readonly ResearchPageServiceInterface $researchPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function index(Request $request, string $locale): View|RedirectResponse
    {
        $page = $this->researchPageService->landing($locale);

        // The research landing is editorial chrome with no database equivalent, so
        // it is unavailable until SPU publishes it. Research itself is not empty -
        // the publications archive carries the migrated legacy research - so send
        // visitors there instead of showing an apology page.
        //
        // Safe to redirect unconditionally: the publications archive always
        // renders. It is data-backed, so "no matching records" is an ordinary
        // empty-results state with working filters, not a retirement.
        if (! $page->isAvailable) {
            return redirect()->to('/'.$locale.'/research/publications');
        }

        return $this->renderPage($request, $locale, $page, 'public.research.index', '/research');
    }

    public function repository(Request $request, string $locale): View
    {
        return $this->renderPage(
            $request,
            $locale,
            $this->researchPageService->repository($locale, $request->only(['q', 'faculty', 'type', 'year', 'page'])),
            'public.research.repository',
            '/research/repository'
        );
    }

    public function publications(Request $request, string $locale): View
    {
        return $this->renderPage(
            $request,
            $locale,
            $this->researchPageService->publications($locale, $request->only(['q', 'faculty', 'type', 'year', 'page'])),
            'public.research.publications.index',
            '/research/publications'
        );
    }

    public function publication(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->publication($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.publications.show', '/research/publications/'.$slug);
    }

    public function centers(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->centers($locale), 'public.research.centers.index', '/research/centers');
    }

    public function center(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->center($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.centers.show', '/research/centers/'.$slug);
    }

    public function projects(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->projects($locale, $request->only(['q', 'status', 'faculty', 'theme', 'page'])), 'public.research.projects.index', '/research/projects');
    }

    public function project(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->project($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.projects.show', '/research/projects/'.$slug);
    }

    public function themes(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->themes($locale), 'public.research.themes.index', '/research/themes');
    }

    public function theme(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->theme($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.themes.show', '/research/themes/'.$slug);
    }

    public function researchers(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->researchers($locale, $request->only(['q', 'faculty', 'expertise', 'page'])), 'public.research.researchers.index', '/research/researchers');
    }

    public function researcher(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->researcher($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.researchers.show', '/research/researchers/'.$slug);
    }

    public function expertFinder(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->expertFinder($locale, $request->only(['q', 'faculty', 'page'])), 'public.research.expert-finder', '/research/expert-finder');
    }

    public function conferences(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->conferences($locale), 'public.research.conferences', '/research/conferences');
    }

    public function conferenceRegistration(Request $request, string $locale): View
    {
        return $this->renderPage(
            $request,
            $locale,
            $this->researchPageService->conferenceRegistration($locale, is_string($request->query('event')) ? (string) $request->query('event') : null),
            'public.research.conference-registration',
            '/research/conferences/register'
        );
    }

    public function library(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->library($locale), 'public.research.library', '/research/library');
    }

    public function office(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->office($locale), 'public.research.office', '/research/office');
    }

    public function policies(Request $request, string $locale): View
    {
        return $this->renderRetirableSection($request, $locale, $this->researchPageService->policies($locale), 'public.research.policies', '/research/policies');
    }

    public function legacyDetail(Request $request, string $locale): RedirectResponse
    {
        $id = (string) $request->query('id', '');
        $slug = $id !== '' ? $this->researchPageService->publicationSlugForLegacyId($id) : null;
        abort_if($slug === null, 404);

        return redirect()->route('public.research.publications.show', ['locale' => $locale, 'slug' => $slug]);
    }

    /**
     * Render a CMS-only research section, or 404 if it has been retired.
     *
     * These sections have no database equivalent, so when nothing is published
     * there is genuinely nothing to show. The alternative - an empty-state page
     * reading "this section will appear after bilingual content is published and
     * reviewed" - exposes our editorial workflow to visitors and reads as an
     * unfinished site, so it must never be public. None of these paths appear in
     * navigation while unavailable, so a real 404 costs nothing.
     *
     * Data-backed pages (publications, researchers) deliberately do NOT use this:
     * an archive whose filters return nothing is a normal empty-results state,
     * not a retirement, and must keep rendering.
     */
    private function renderRetirableSection(Request $request, string $locale, ResearchPageDTO $page, string $view, string $suffix): View
    {
        abort_if(! $page->isAvailable, 404);

        return $this->renderPage($request, $locale, $page, $view, $suffix);
    }

    private function renderPage(Request $request, string $locale, ResearchPageDTO $page, string $view, string $suffix): View
    {
        $query = $this->pageQuery($page);

        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $suffix, $query),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page->path, $suffix, $page->seoTitle, $page->seoDescription, $page->seoImage, $query),
            'page' => $page,
        ]);
    }

    private function renderDetail(Request $request, string $locale, ResearchDetailPageDTO $page, string $view, string $suffix): View
    {
        $structuredData = $this->detailStructuredData($page);

        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $suffix),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page->path, $suffix, $page->seoTitle, $page->seoDescription, $page->seoImage),
            'structuredData' => $structuredData,
            'citationMeta' => $page->type === 'publication' ? $this->publicationCitationMeta($page) : null,
            'ogType' => $page->type === 'publication' ? 'article' : 'website',
            'page' => $page,
        ]);
    }

    private function seo(string $locale, string $path, string $suffix, string $title, string $description, string $image, array $query = []): mixed
    {
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => $path.$queryString,
            'locale_paths' => ['ar' => '/ar'.$suffix.$queryString, 'en' => '/en'.$suffix.$queryString],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix, array $query = []): array
    {
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$suffix.$queryString, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en'.$suffix.$queryString, $locale === 'en'),
        ];
    }

    /** @return array<string, scalar> */
    private function pageQuery(ResearchPageDTO $page): array
    {
        $filters = is_array($page->data['activeFilters'] ?? null) ? $page->data['activeFilters'] : [];
        $query = [];

        foreach ($filters as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || $value === '' || ($key === 'page' && (int) $value <= 1)) {
                continue;
            }

            $query[$key] = $value;
        }

        if ($page->type === 'conference-registration') {
            $eventId = $page->data['registerEvent']['id'] ?? null;

            if (is_scalar($eventId) && (string) $eventId !== '') {
                $query['event'] = (string) $eventId;
            }
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function detailStructuredData(ResearchDetailPageDTO $page): array
    {
        $item = $page->item;
        $canonicalUrl = url($page->path);

        if ($page->type === 'publication') {
            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'ScholarlyArticle',
                'headline' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['lead'] ?? $item['summary'] ?? ''),
                'inLanguage' => $page->locale,
                'mainEntityOfPage' => $canonicalUrl,
                'url' => $canonicalUrl,
                'image' => url((string) ($item['image'] ?? $page->seoImage)),
                'author' => [
                    '@type' => 'Person',
                    'name' => (string) ($item['author'] ?? ''),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name', 'Syrian Private University'),
                    'url' => url('/'),
                ],
                'datePublished' => (string) ($item['publicationDate'] ?? $item['year'] ?? ''),
                'keywords' => array_values(array_filter(is_array($item['keywords'] ?? null) ? $item['keywords'] : [], 'is_scalar')),
            ];

            if (is_string($item['doi'] ?? null) && $item['doi'] !== '') {
                $data['identifier'] = 'https://doi.org/'.$item['doi'];
            }

            if (is_bool($item['isOpenAccess'] ?? null)) {
                $data['isAccessibleForFree'] = $item['isOpenAccess'];
            }

            return $data;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => match ($page->type) {
                'researcher' => 'Person',
                'project' => 'ResearchProject',
                'center' => 'Organization',
                default => 'WebPage',
            },
            'name' => (string) ($item['title'] ?? $item['name'] ?? ''),
            'description' => (string) ($item['summary'] ?? $item['mission'] ?? $item['description'] ?? ''),
            'inLanguage' => $page->locale,
            'url' => $canonicalUrl,
        ];
    }

    /** @return array<string, string> */
    private function publicationCitationMeta(ResearchDetailPageDTO $page): array
    {
        $item = $page->item;
        $keywords = is_array($item['keywords'] ?? null)
            ? implode(', ', array_map('strval', array_filter($item['keywords'], 'is_scalar')))
            : '';
        $meta = [
            'citation_title' => (string) ($item['title'] ?? ''),
            'citation_author' => (string) ($item['author'] ?? ''),
            'citation_publication_date' => (string) ($item['publicationDate'] ?? $item['year'] ?? ''),
            'citation_journal_title' => (string) ($item['journalTitle'] ?? $item['publisher'] ?? ''),
            'citation_language' => $page->locale,
            'citation_abstract' => (string) ($item['lead'] ?? $item['summary'] ?? ''),
            'citation_keywords' => $keywords,
            'DC.title' => (string) ($item['title'] ?? ''),
            'DC.creator' => (string) ($item['author'] ?? ''),
            'DC.date' => (string) ($item['publicationDate'] ?? $item['year'] ?? ''),
            'DC.description' => (string) ($item['lead'] ?? $item['summary'] ?? ''),
            'DC.type' => (string) ($item['type'] ?? 'ScholarlyArticle'),
            'DC.language' => $page->locale,
            'DC.subject' => $keywords,
        ];

        if (is_string($item['doi'] ?? null) && $item['doi'] !== '') {
            $meta['citation_doi'] = $item['doi'];
            $meta['DC.identifier'] = 'https://doi.org/'.$item['doi'];
        }

        return $meta;
    }
}
