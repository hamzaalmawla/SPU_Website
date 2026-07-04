<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Faculty\FacultyDetailPageDTO;
use App\DTOs\Faculty\FacultyHubPageDTO;
use App\DTOs\Faculty\FacultyProjectDetailPageDTO;
use App\DTOs\Faculty\FacultySubpageDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FacultyController extends Controller
{
    public function __construct(
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function hub(Request $request, string $locale): View
    {
        $page = $this->facultyPageService->getHub($locale);

        return view('public.facilities.index', $this->viewPayload($request, $locale, $page, $this->hubSeo($locale, $page), $this->languageSwitch($locale, '')));
    }

    public function faculty(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getFaculty($faculty, $locale);
        abort_if($page === null, 404);

        return view('public.facilities.index', $this->viewPayload($request, $locale, $page, $this->facultySeo($locale, $page), $this->languageSwitch($locale, '/'.$page->slug)));
    }

    public function subpage(Request $request, string $locale, string $faculty, string $subpage): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, $subpage, $locale);
        abort_if($page === null, 404);

        return view('public.faculties.subpage', $this->viewPayload($request, $locale, $page, $this->subpageSeo($locale, $page), $this->languageSwitch($locale, '/'.$page->facultySlug.'/'.$page->subpageSlug)));
    }

    public function project(Request $request, string $locale, string $faculty, string $project): View
    {
        $page = $this->facultyPageService->getProject($faculty, $project, $locale);
        abort_if($page === null, 404);

        return view('public.faculties.project-detail', $this->viewPayload($request, $locale, $page, $this->projectSeo($locale, $page), $this->languageSwitch($locale, '/'.$page->facultySlug.'/projects/'.($page->project['slug'] ?? $project))));
    }

    public function studyPlan(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, 'study-plan', $locale);
        abort_if($page === null, 404);

        return view('public.faculties.subpage', $this->viewPayload($request, $locale, $page, $this->subpageSeo($locale, $page), $this->languageSwitch($locale, '/'.$page->facultySlug.'/study-plan')));
    }

    public function courseLessons(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, 'study-plan-course', $locale);
        abort_if($page === null, 404);

        return view('public.faculties.subpage', $this->viewPayload($request, $locale, $page, $this->subpageSeo($locale, $page), $this->languageSwitch($locale, '/'.$page->facultySlug.'/study-plan/course')));
    }

    public function redirectLegacy(Request $request, string $locale, ?string $legacyPath = null): RedirectResponse
    {
        $segments = collect(explode('/', trim((string) $legacyPath, '/')))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isNotEmpty()) {
            $segments[0] = $this->facultyPageService->canonicalFacultySlug((string) $segments[0]);
        }

        $target = '/'.$locale.'/facilities'.($segments->isNotEmpty() ? '/'.$segments->implode('/') : '');
        $query = $request->getQueryString();

        return redirect()->to($query ? $target.'?'.$query : $target, 301);
    }

    /** @return array<string, mixed> */
    private function viewPayload(Request $request, string $locale, object $page, mixed $seo, array $languageSwitch): array
    {
        return [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $languageSwitch,
            'isPreview' => false,
            'seo' => $seo,
            'page' => $page,
        ];
    }

    private function hubSeo(string $locale, FacultyHubPageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/facilities',
            'locale_paths' => ['ar' => '/ar/facilities', 'en' => '/en/facilities'],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function facultySeo(string $locale, FacultyDetailPageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/facilities/'.$page->slug,
            'locale_paths' => ['ar' => '/ar/facilities/'.$page->slug, 'en' => '/en/facilities/'.$page->slug],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function subpageSeo(string $locale, FacultySubpageDTO $page): mixed
    {
        $subpagePath = $page->subpageSlug === 'study-plan-course' ? 'study-plan/course' : $page->subpageSlug;

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/facilities/'.$page->facultySlug.'/'.$subpagePath,
            'locale_paths' => [
                'ar' => '/ar/facilities/'.$page->facultySlug.'/'.$subpagePath,
                'en' => '/en/facilities/'.$page->facultySlug.'/'.$subpagePath,
            ],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function projectSeo(string $locale, FacultyProjectDetailPageDTO $page): mixed
    {
        $projectSlug = (string) ($page->project['slug'] ?? '');

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/facilities/'.$page->facultySlug.'/projects/'.$projectSlug,
            'locale_paths' => [
                'ar' => '/ar/facilities/'.$page->facultySlug.'/projects/'.$projectSlug,
                'en' => '/en/facilities/'.$page->facultySlug.'/projects/'.$projectSlug,
            ],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitch(string $locale, string $suffix): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/facilities'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/facilities'.$suffix, $locale === 'en'),
        ];
    }
}
