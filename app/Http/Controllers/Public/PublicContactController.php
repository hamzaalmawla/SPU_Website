<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Contact\ContactPageDTO;
use App\DTOs\Contact\ContactSubmissionDataDTO;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class PublicContactController extends Controller
{
    public function __construct(
        private readonly ContactPageServiceInterface $contactPageService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function show(Request $request, string $locale): View
    {
        $contact = $this->contactPageService->getPage($locale);

        return view('public.contact', [
            'locale' => $locale,
            'direction' => $contact->direction,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale),
            'isPreview' => false,
            'seo' => $this->seo($locale, $contact),
            'contact' => $contact,
        ]);
    }

    public function store(PublicContactRequest $request, string $locale): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $submitted = $this->contactPageService->submit(new ContactSubmissionDataDTO(
            locale: $locale,
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            subject: (string) $validated['subject'],
            message: (string) $validated['message'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'submitted' => $submitted,
                'locale' => $locale,
            ]);
        }

        return redirect()
            ->route('public.contact', ['locale' => $locale])
            ->with('contact_status', $locale === 'ar' ? 'تم إرسال رسالتك بنجاح.' : 'Your message has been sent.');
    }

    private function seo(string $locale, ContactPageDTO $contact): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.'/contact',
            'locale_paths' => ['ar' => '/ar/contact', 'en' => '/en/contact'],
            'title' => $contact->seoTitle,
            'meta_description' => $contact->seoDescription,
            'og_title' => $contact->seoTitle,
            'og_description' => $contact->seoDescription,
            'og_image' => $contact->seoImage,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar/contact', $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en/contact', $locale === 'en'),
        ];
    }
}
