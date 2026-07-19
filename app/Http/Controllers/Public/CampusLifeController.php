<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CampusLifeController extends Controller
{
    public function __construct(
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function landing(Request $request, string $locale): View
    {
        $page = $this->campusLifePageService->getLanding($locale);

        return view('public.campus-life.landing', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale),
            'isPreview' => false,
            'seo' => $this->landingSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function section(Request $request, string $locale, string $section): View
    {
        $page = $this->campusLifePageService->getSection($section, $locale);
        abort_if($page === null, 404);

        return view('public.campus-life.section', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/'.$page->sectionSlug),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    public function careerJobBoard(Request $request, string $locale): View
    {
        $page = $this->campusLifePageService->getCareerJobBoard($locale, $request->only(['q', 'category', 'type', 'page']));
        $query = $this->pageQuery($page);

        return view('public.campus-life.job-board', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs', $query),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page, $query),
            'page' => $page,
        ]);
    }

    public function careerJobDetail(Request $request, string $locale, string $job): View
    {
        $page = $this->campusLifePageService->getCareerJobDetail($job, $locale);
        abort_if($page === null, 404);

        return view('public.campus-life.job-detail', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs/'.$job),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'structuredData' => $this->jobStructuredData($locale, $page),
            'ogType' => 'website',
            'page' => $page,
        ]);
    }

    public function careerJobApplication(Request $request, string $locale): View
    {
        $job = is_string($request->query('job')) ? trim((string) $request->query('job')) : null;
        $page = $this->campusLifePageService->getCareerJobApplication($locale, $job);
        abort_if($page === null, 404);
        $query = ['job' => (string) ($page->section['selectedJob']['slug'] ?? '')];

        return view('public.campus-life.job-application', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/career-development/jobs/apply', $query),
            'isPreview' => false,
            'seo' => $this->sectionSeo($locale, $page),
            'page' => $page,
        ]);
    }

    private function landingSeo(string $locale, CampusLifePageDTO $page): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/campus-life',
            'locale_paths' => ['ar' => '/ar/campus-life', 'en' => '/en/campus-life'],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function sectionSeo(string $locale, CampusLifeSectionDTO $page, array $query = []): mixed
    {
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/campus-life/'.$page->sectionSlug.$queryString,
            'locale_paths' => [
                'ar' => '/ar/campus-life/'.$page->sectionSlug.$queryString,
                'en' => '/en/campus-life/'.$page->sectionSlug.$queryString,
            ],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix = '', array $query = []): array
    {
        $queryString = $query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';

        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/campus-life'.$suffix.$queryString, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/campus-life'.$suffix.$queryString, $locale === 'en'),
        ];
    }

    /** @return array<string, scalar> */
    private function pageQuery(CampusLifeSectionDTO $page): array
    {
        $filters = is_array($page->section['activeFilters'] ?? null) ? $page->section['activeFilters'] : [];
        $query = [];

        foreach (['q', 'category', 'type', 'page'] as $key) {
            $value = $filters[$key] ?? null;
            if (! is_scalar($value) || $value === '' || in_array($value, ['all', 1, '1'], true)) {
                continue;
            }

            $query[$key] = $value;
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function jobStructuredData(string $locale, CampusLifeSectionDTO $page): array
    {
        $job = is_array($page->section['job'] ?? null) ? $page->section['job'] : [];
        $employmentType = match ($job['type'] ?? null) {
            'full-time' => 'FULL_TIME',
            'part-time' => 'PART_TIME',
            'contract' => 'CONTRACTOR',
            default => 'OTHER',
        };

        return [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => (string) ($job['title'] ?? ''),
            'description' => implode("\n", array_values(array_filter(is_array($job['overview'] ?? null) ? $job['overview'] : [], 'is_string'))),
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => config('app.name', 'Syrian Private University'),
                'value' => (string) ($job['id'] ?? ''),
            ],
            'datePosted' => (string) ($job['postedDate'] ?? ''),
            'validThrough' => (string) ($job['closeDate'] ?? ''),
            'employmentType' => $employmentType,
            'inLanguage' => $locale,
            'url' => url('/'.$locale.'/campus-life/career-development/jobs/'.($job['slug'] ?? '')),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Syrian Private University'),
                'sameAs' => url('/'),
                'logo' => url('/images/logo-spu.png'),
            ],
            'jobLocation' => [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => (string) ($job['location'] ?? ''),
                    'addressCountry' => 'SY',
                ],
            ],
        ];
    }
}
