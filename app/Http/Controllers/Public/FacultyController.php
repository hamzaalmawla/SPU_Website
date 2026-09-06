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

    public function hub(Request $request, string $locale): View|RedirectResponse
    {
        $legacyFaculty = $request->query('id');

        if (is_string($legacyFaculty) && trim($legacyFaculty) !== '') {
            $faculty = $this->facultyPageService->getFaculty($legacyFaculty, $locale);
            abort_if($faculty === null, 404);

            return redirect('/'.$locale.'/faculties/'.$faculty->slug, 301);
        }

        $page = $this->facultyPageService->getHub($locale);

        return view('public.faculties.index', $this->viewPayload($request, $locale, $page, $this->hubSeo($locale, $page), $this->languageSwitch($locale, '')));
    }

    public function faculty(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getFaculty($faculty, $locale);
        abort_if($page === null, 404);

        return view('public.faculties.index', $this->viewPayload($request, $locale, $page, $this->facultySeo($locale, $page), $this->languageSwitch($locale, '/'.$page->slug)));
    }

    public function subpage(Request $request, string $locale, string $faculty, string $subpage): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, $subpage, $locale, $request->query());
        abort_if($page === null, 404);
        $query = match ($page->subpageSlug) {
            'alumni', 'valedictorians' => $this->studentDirectoryQuery($request, $page),
            'projects', 'research' => $this->paginationQuery($request, $page),
            'labs' => $this->labQuery($request, $page),
            default => [],
        };

        return view('public.faculties.subpage', $this->viewPayload(
            $request,
            $locale,
            $page,
            $this->subpageSeo($locale, $page, $query),
            $this->languageSwitch($locale, '/'.$page->facultySlug.'/'.$page->subpageSlug, $query),
        ));
    }

    public function project(Request $request, string $locale, string $faculty, string $project): View
    {
        $page = $this->facultyPageService->getProject($faculty, $project, $locale);
        abort_if($page === null, 404);

        return view('public.faculties.project-detail', $this->viewPayload($request, $locale, $page, $this->projectSeo($locale, $page), $this->languageSwitch($locale, '/'.$page->facultySlug.'/projects/'.($page->project['slug'] ?? $project))));
    }

    public function studyPlan(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, 'study-plan', $locale, $request->query());
        abort_if($page === null, 404);

        return view('public.faculties.subpage', $this->viewPayload(
            $request,
            $locale,
            $page,
            $this->subpageSeo($locale, $page, $page->filters),
            $this->languageSwitch($locale, '/'.$page->facultySlug.'/study-plan', $page->filters),
        ));
    }

    public function courseLessons(Request $request, string $locale, string $faculty): View
    {
        $page = $this->facultyPageService->getSubpage($faculty, 'study-plan-course', $locale, $request->query());
        abort_if($page === null, 404);

        return view('public.faculties.subpage', $this->viewPayload(
            $request,
            $locale,
            $page,
            $this->subpageSeo($locale, $page, $page->filters),
            $this->languageSwitch($locale, '/'.$page->facultySlug.'/study-plan/course', $page->filters),
        ));
    }

    public function redirectLegacy(Request $request, string $locale, ?string $legacyPath = null): RedirectResponse
    {
        $segments = collect(explode('/', trim((string) $legacyPath, '/')))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values()
            ->map(fn (string $segment, int $index): string => $index === 0
                ? $this->facultyPageService->canonicalFacultySlug($segment)
                : $segment);

        $target = '/'.$locale.'/faculties'.($segments->isNotEmpty() ? '/'.$segments->implode('/') : '');
        $query = $request->getQueryString();

        return redirect()->to($query ? $target.'?'.$query : $target, 301);
    }

    public function redirectLegacyProject(Request $request, string $locale): RedirectResponse
    {
        $projectId = $request->query('id');
        abort_unless(is_string($projectId) && trim($projectId) !== '', 404);

        $target = $this->facultyPageService->resolveLegacyProjectUrl(trim($projectId), $locale);
        abort_if($target === null, 404);

        return redirect($target, 301);
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
            'path' => '/'.$locale.'/faculties',
            'locale_paths' => ['ar' => '/ar/faculties', 'en' => '/en/faculties'],
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
            'path' => '/'.$locale.'/faculties/'.$page->slug,
            'locale_paths' => ['ar' => '/ar/faculties/'.$page->slug, 'en' => '/en/faculties/'.$page->slug],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function subpageSeo(string $locale, FacultySubpageDTO $page, array $query = []): mixed
    {
        $subpagePath = $page->subpageSlug === 'study-plan-course' ? 'study-plan/course' : $page->subpageSlug;
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/faculties/'.$page->facultySlug.'/'.$subpagePath.$queryString,
            'locale_paths' => [
                'ar' => '/ar/faculties/'.$page->facultySlug.'/'.$subpagePath.$queryString,
                'en' => '/en/faculties/'.$page->facultySlug.'/'.$subpagePath.$queryString,
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
            'path' => '/'.$locale.'/faculties/'.$page->facultySlug.'/projects/'.$projectSlug,
            'locale_paths' => [
                'ar' => '/ar/faculties/'.$page->facultySlug.'/projects/'.$projectSlug,
                'en' => '/en/faculties/'.$page->facultySlug.'/projects/'.$projectSlug,
            ],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitch(string $locale, string $suffix, array $query = []): array
    {
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/faculties'.$suffix.$queryString, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/faculties'.$suffix.$queryString, $locale === 'en'),
        ];
    }

    /** @return array<string, int> */
    private function paginationQuery(Request $request, FacultySubpageDTO $page): array
    {
        $requestedPage = $request->query('page');

        if (! is_scalar($requestedPage) || filter_var($requestedPage, FILTER_VALIDATE_INT) === false || (int) $requestedPage < 1) {
            return [];
        }

        $currentPage = (int) ($page->pagination['current_page'] ?? 1);

        return ['page' => $currentPage];
    }

    /** @return array<string, int|string> */
    private function studentDirectoryQuery(Request $request, FacultySubpageDTO $page): array
    {
        $query = [];
        $search = (string) ($page->filters['q'] ?? '');
        $year = (string) ($page->filters['year'] ?? '');
        $semester = (string) ($page->filters['semester'] ?? '');
        $years = is_array($page->filterOptions['years'] ?? null) ? $page->filterOptions['years'] : [];
        $semesters = collect(is_array($page->filterOptions['semesters'] ?? null) ? $page->filterOptions['semesters'] : [])
            ->pluck('key')
            ->all();

        if ($search !== '') {
            $query['q'] = $search;
        }

        if ($year !== '' && in_array($year, array_map('strval', $years), true)) {
            $query['year'] = $year;
        }

        if ($semester !== '' && ($semesters === [] || in_array($semester, $semesters, true))) {
            $query['semester'] = $semester;
        }

        return [...$query, ...$this->paginationQuery($request, $page)];
    }

    /** @return array<string, int|string> */
    private function labQuery(Request $request, FacultySubpageDTO $page): array
    {
        $selectedLab = $page->detail['item'] ?? null;
        $query = is_array($selectedLab) && is_string($selectedLab['slug'] ?? null)
            ? ['lab' => $selectedLab['slug']]
            : [];

        return [...$query, ...$this->paginationQuery($request, $page)];
    }
}
