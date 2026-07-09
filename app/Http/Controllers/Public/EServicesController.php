<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\EServices\EServicesPageDTO;
use App\DTOs\Contact\ContactSubmissionDataDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EServicesController extends Controller
{
    public function __construct(
        private readonly EServicesPageServiceInterface $eServicesPageService,
        private readonly ContactPageServiceInterface $contactPageService,
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

    public function storeSuggestionsComplaints(Request $request, string $locale): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'request_type' => ['required', 'string', 'in:suggestion,complaint,inquiry'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $typeLabel = match ((string) $validated['request_type']) {
            'suggestion' => $locale === 'ar' ? 'اقتراح' : 'Suggestion',
            'complaint' => $locale === 'ar' ? 'شكوى' : 'Complaint',
            default => $locale === 'ar' ? 'استفسار' : 'Inquiry',
        };

        $this->contactPageService->submit(new ContactSubmissionDataDTO(
            locale: $locale,
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            subject: '['.$typeLabel.'] '.(string) $validated['subject'],
            message: (string) $validated['message'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

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

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $suffix = ''): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/e-services'.$suffix, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/e-services'.$suffix, $locale === 'en'),
        ];
    }
}
