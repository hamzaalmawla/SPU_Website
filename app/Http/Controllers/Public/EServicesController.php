<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\EServices\EServicesDetailPageDTO;
use App\DTOs\EServices\EServicesPageDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\DynamicFormSubmissionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EServicesController extends Controller
{
    public function __construct(
        private readonly EServicesPageServiceInterface $eServicesPageService,
        private readonly DynamicFormSubmissionServiceInterface $submissionService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $page = $this->eServicesPageService->getPage($locale);

        return view('public.e-services', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page),
            'page' => $page,
        ]);
    }

    public function suggestionsComplaints(Request $request, string $locale): View
    {
        $page = $this->eServicesPageService->getSuggestionsComplaintsPage($locale);

        return view('public.e-services-suggestions-complaints', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/suggestions-complaints'),
            'isPreview' => false,
            'seo' => $this->seo($locale, $page, '/suggestions-complaints'),
            'page' => $page,
        ]);
    }

    public function detail(Request $request, string $locale, string $detail): View
    {
        $page = $this->eServicesPageService->getDetailPage($locale, $detail);
        abort_if($page->heroTitle === '' || $page->seoTitle === '', 404);

        return view('public.e-services-detail', [
            'locale' => $locale,
            'direction' => $page->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, '/'.$detail),
            'isPreview' => false,
            'seo' => $this->detailSeo($locale, $page),
            'structuredData' => $this->detailStructuredData($locale, $page),
            'page' => $page,
        ]);
    }

    public function storeSuggestionsComplaints(DynamicFormSubmissionRequest $request, string $locale): RedirectResponse
    {
        $this->submissionService->submit($request->toDto('suggestions-complaints', $locale));

        return redirect()
            ->route('public.e-services.suggestions-complaints', ['locale' => $locale])
            ->with('suggestions_status', $locale === 'ar' ? 'تم إرسال طلبك بنجاح.' : 'Your request has been submitted.');
    }

    private function seo(string $locale, EServicesPageDTO $page, string $suffix = ''): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/e-services'.$suffix,
            'locale_paths' => ['ar' => '/ar/e-services'.$suffix, 'en' => '/en/e-services'.$suffix],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    private function detailSeo(string $locale, EServicesDetailPageDTO $page): mixed
    {
        $suffix = '/'.$page->slug;

        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/e-services'.$suffix,
            'locale_paths' => ['ar' => '/ar/e-services'.$suffix, 'en' => '/en/e-services'.$suffix],
            'title' => $page->seoTitle,
            'meta_description' => $page->seoDescription,
            'og_title' => $page->seoTitle,
            'og_description' => $page->seoDescription,
            'og_image' => $page->seoImage,
        ]);
    }

    /** @return array<string, mixed> */
    private function detailStructuredData(string $locale, EServicesDetailPageDTO $page): array
    {
        $path = '/'.$locale.'/e-services/'.$page->slug;

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => url($path).'#webpage',
                    'url' => url($path),
                    'name' => $page->seoTitle,
                    'description' => $page->seoDescription,
                    'inLanguage' => $locale,
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'ar' ? 'الرئيسية' : 'Home', 'item' => url('/'.$locale)],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $locale === 'ar' ? 'الخدمات الإلكترونية' : 'E-Services', 'item' => url('/'.$locale.'/e-services')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $page->heroTitle, 'item' => url($path)],
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix = ''): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/e-services'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/e-services'.$suffix, $locale === 'en'),
        ];
    }
}
