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

    public function index(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->landing($locale), 'public.research.index', '/research');
    }

    public function repository(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->repository($locale), 'public.research.repository', '/research/repository');
    }

    public function publications(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->publications($locale), 'public.research.publications.index', '/research/publications');
    }

    public function publication(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->publication($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.publications.show', '/research/publications/'.$slug);
    }

    public function centers(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->centers($locale), 'public.research.centers.index', '/research/centers');
    }

    public function center(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->center($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.centers.show', '/research/centers/'.$slug);
    }

    public function projects(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->projects($locale), 'public.research.projects.index', '/research/projects');
    }

    public function project(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->project($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.projects.show', '/research/projects/'.$slug);
    }

    public function themes(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->themes($locale), 'public.research.themes.index', '/research/themes');
    }

    public function theme(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->theme($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.themes.show', '/research/themes/'.$slug);
    }

    public function researchers(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->researchers($locale), 'public.research.researchers.index', '/research/researchers');
    }

    public function researcher(Request $request, string $locale, string $slug): View
    {
        $page = $this->researchPageService->researcher($locale, $slug);
        abort_if($page === null, 404);

        return $this->renderDetail($request, $locale, $page, 'public.research.researchers.show', '/research/researchers/'.$slug);
    }

    public function expertFinder(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->expertFinder($locale), 'public.research.expert-finder', '/research/expert-finder');
    }

    public function conferences(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->conferences($locale), 'public.research.conferences', '/research/conferences');
    }

    public function library(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->library($locale), 'public.research.library', '/research/library');
    }

    public function office(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->office($locale), 'public.research.office', '/research/office');
    }

    public function policies(Request $request, string $locale): View
    {
        return $this->renderPage($request, $locale, $this->researchPageService->policies($locale), 'public.research.policies', '/research/policies');
    }

    public function legacyDetail(Request $request, string $locale): RedirectResponse
    {
        $id = (string) $request->query('id', '');
        $slug = $id !== '' ? $this->researchPageService->publicationSlugForLegacyId($id) : null;
        abort_if($slug === null, 404);

        return redirect()->route('public.research.publications.show', ['locale' => $locale, 'slug' => $slug]);
    }

    private function renderPage(Request $request, string $locale, ResearchPageDTO $page, string $view, string $suffix): View
    {
        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $suffix),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page->path, $suffix, $page->seoTitle, $page->seoDescription, $page->seoImage),
            'page' => $page,
        ]);
    }

    private function renderDetail(Request $request, string $locale, ResearchDetailPageDTO $page, string $view, string $suffix): View
    {
        return view($view, [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $suffix),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page->path, $suffix, $page->seoTitle, $page->seoDescription, $page->seoImage),
            'page' => $page,
        ]);
    }

    private function seo(string $locale, string $path, string $suffix, string $title, string $description, string $image): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => $path,
            'locale_paths' => ['ar' => '/ar'.$suffix, 'en' => '/en'.$suffix],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en'.$suffix, $locale === 'en'),
        ];
    }
}
