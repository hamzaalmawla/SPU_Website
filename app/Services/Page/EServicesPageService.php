<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\EServices\EServicesDetailPageDTO;
use App\DTOs\EServices\EServicesPageContentDTO;
use App\DTOs\EServices\EServicesPageDTO;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;

final class EServicesPageService implements EServicesPageServiceInterface
{
    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getPage(string $locale): EServicesPageDTO
    {
        return $this->pageFromContent($locale, $this->getContent($locale));
    }

    public function getSuggestionsComplaintsPage(string $locale): EServicesPageDTO
    {
        $isArabic = $locale === 'ar';

        return new EServicesPageDTO(
            locale: $locale,
            direction: $isArabic ? 'rtl' : 'ltr',
            hero: [
                'eyebrow' => $isArabic ? 'الخدمات الإلكترونية' : 'E-Services',
                'title' => $isArabic ? 'الاقتراحات والشكاوى' : 'Suggestions & Complaints',
                'summary' => $isArabic ? 'شاركنا ملاحظاتك واقتراحاتك أو مخاوفك للمساعدة في تحسين خدمات الجامعة وتجربة الطلاب.' : 'Share your feedback, suggestions, or concerns to help us improve university services and student experience.',
                'imageHero' => '/images/slider-3.webp',
                'imageLeft' => '',
                'imageRight' => '',
            ],
            digitalServices: [
                'formTitle' => $isArabic ? 'قدّم طلبك' : 'Submit Your Request',
                'requestTypes' => [
                    ['value' => 'suggestion', 'label' => $isArabic ? 'اقتراح' : 'Suggestion'],
                    ['value' => 'complaint', 'label' => $isArabic ? 'شكوى' : 'Complaint'],
                    ['value' => 'inquiry', 'label' => $isArabic ? 'استفسار' : 'Inquiry'],
                ],
                'infoTitle' => $isArabic ? 'نقدّر ملاحظاتك' : 'We Value Your Feedback',
                'infoBody' => $isArabic ? 'تساعدنا آراؤك في تحديد فرص التحسين وضمان تلبية خدمات الجامعة لتوقعات الطلاب. تتم مراجعة كل رسالة بعناية من قبل القسم المعني.' : 'Your opinions help us identify opportunities for improvement and ensure that university services meet student expectations. Every submission is reviewed carefully by the relevant department.',
                'cards' => [
                    ['title' => $isArabic ? 'السرية' : 'Confidentiality', 'body' => $isArabic ? 'يتم التعامل مع جميع المعلومات بسرية ومشاركتها فقط مع القسم المسؤول.' : 'All submitted information is handled confidentially and shared only with the responsible department.'],
                    ['title' => $isArabic ? 'مدة الرد' : 'Response Time', 'body' => $isArabic ? 'يمكنك توقع رد خلال 3 إلى 5 أيام عمل بعد التقديم.' : 'You can expect a response within 3-5 business days after submission.'],
                ],
            ],
            supportCards: [],
            seoTitle: ($isArabic ? 'الاقتراحات والشكاوى' : 'Suggestions & Complaints').' | '.($isArabic ? 'الجامعة السورية الخاصة' : 'Syrian Private University'),
            seoDescription: $isArabic ? 'قدّم اقتراحاتك أو شكاواك لتحسين خدمات الجامعة السورية الخاصة.' : 'Submit suggestions, complaints, or inquiries to help improve Syrian Private University services.',
            seoImage: '/images/slider-3.webp',
        );
    }

    public function getDetailPage(string $locale, string $slug): EServicesDetailPageDTO
    {
        $targetKey = $this->detailTargetKey($slug);
        $publishedPayload = $this->cmsWorkflowService->getPublishedPayload($targetKey);
        $content = is_array($publishedPayload['translations'][$locale] ?? null)
            ? $publishedPayload['translations'][$locale]
            : null;

        if (! is_array($content)) {
            $settings = $this->settingsService->getGroup($this->detailSettingsGroup($slug), $locale);
            $content = collect($settings->values)->firstWhere('key', 'content')?->jsonValue ?? [];
        }

        return $this->detailPageFromArray($locale, $slug, is_array($content) ? $content : []);
    }

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): EServicesPageDTO
    {
        return $this->pageFromContent($locale, $this->contentFromArray($content));
    }

    /** @param array<string, mixed> $content */
    public function buildDetailPreviewPage(string $locale, string $slug, array $content): EServicesDetailPageDTO
    {
        $this->detailTargetKey($slug);

        return $this->detailPageFromArray($locale, $slug, $content);
    }

    private function pageFromContent(string $locale, EServicesPageContentDTO $content): EServicesPageDTO
    {
        return new EServicesPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            hero: $content->hero,
            digitalServices: $content->digitalServices,
            supportCards: $content->supportCards,
            seoTitle: $content->seoTitle,
            seoDescription: $content->seoDescription,
            seoImage: $content->seoImage,
        );
    }

    public function getContent(string $locale): EServicesPageContentDTO
    {
        $publishedPayload = $this->cmsWorkflowService->getPublishedPayload('e_services');
        $publishedContent = is_array($publishedPayload['translations'][$locale] ?? null)
            ? $publishedPayload['translations'][$locale]
            : null;

        if (is_array($publishedContent)) {
            return $this->contentFromArray($publishedContent);
        }

        $settings = $this->settingsService->getGroup('e_services_page', $locale);
        $content = collect($settings->values)->firstWhere('key', 'content')?->jsonValue ?? [];

        return $this->contentFromArray(is_array($content) ? $content : []);
    }

    public function updatePage(string $locale, EServicesPageContentDTO $content, int $userId): bool
    {
        return $this->settingsService->updateGroup(
            new SettingsDTO('e_services_page', $locale, [
                new SettingValueDTO(
                    key: 'content',
                    type: 'json',
                    jsonValue: $this->contentToArray($content),
                    isPublic: true,
                ),
            ]),
            $userId,
        );
    }

    /** @param array<string, mixed> $content */
    private function contentFromArray(array $content): EServicesPageContentDTO
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $digital = is_array($content['digitalServices'] ?? null) ? $content['digitalServices'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return new EServicesPageContentDTO(
            hero: [
                'eyebrow' => $this->stringValue($hero, 'eyebrow'),
                'title' => $this->stringValue($hero, 'title'),
                'summary' => $this->stringValue($hero, 'summary'),
                'imageHero' => $this->stringValue($hero, 'imageHero'),
                'imageLeft' => $this->stringValue($hero, 'imageLeft'),
                'imageRight' => $this->stringValue($hero, 'imageRight'),
            ],
            digitalServices: [
                'title' => $this->stringValue($digital, 'title'),
                'services' => $this->services($digital['services'] ?? []),
            ],
            supportCards: $this->supportCards($content['supportCards'] ?? []),
            seoTitle: $this->stringValue($seo, 'title'),
            seoDescription: $this->stringValue($seo, 'description'),
            seoImage: $this->stringValue($seo, 'image'),
        );
    }

    /** @return array<string, mixed> */
    private function contentToArray(EServicesPageContentDTO $content): array
    {
        return [
            'hero' => $content->hero,
            'digitalServices' => $content->digitalServices,
            'supportCards' => $content->supportCards,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ];
    }

    /** @param array<string, mixed> $content */
    private function detailPageFromArray(string $locale, string $slug, array $content): EServicesDetailPageDTO
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $intro = is_array($content['intro'] ?? null) ? $content['intro'] : [];
        $resources = is_array($content['resources'] ?? null) ? $content['resources'] : [];
        $cta = is_array($content['cta'] ?? null) ? $content['cta'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return new EServicesDetailPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            slug: $slug,
            heroEyebrow: $this->stringValue($hero, 'eyebrow'),
            heroTitle: $this->stringValue($hero, 'title'),
            heroSummary: $this->stringValue($hero, 'summary'),
            heroImage: $this->stringValue($hero, 'image'),
            introTitle: $this->stringValue($intro, 'title'),
            introBody: $this->stringValue($intro, 'body'),
            sections: $this->detailSections($content['sections'] ?? []),
            resourceLinksTitle: $this->stringValue($resources, 'title'),
            resourceLinks: $this->safeHttpsLinks($resources['links'] ?? []),
            ctaTitle: $this->stringValue($cta, 'title'),
            ctaBody: $this->stringValue($cta, 'body'),
            ctaLabel: $this->stringValue($cta, 'label'),
            ctaUrl: $this->safeInternalUrl($this->stringValue($cta, 'url')),
            relatedLinks: $this->safeInternalLinks($content['relatedLinks'] ?? []),
            seoTitle: $this->stringValue($seo, 'title'),
            seoDescription: $this->stringValue($seo, 'description'),
            seoImage: $this->stringValue($seo, 'image'),
        );
    }

    /** @return array<int, array{id: string, title: string, body: string}> */
    private function detailSections(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'title' => $this->stringValue($item, 'title'),
                'body' => $this->stringValue($item, 'body'),
            ];
        }, $items)));
    }

    /** @return array<int, array{id: string, title: string, url: string}> */
    private function safeHttpsLinks(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            $url = $this->stringValue($item, 'url');
            if (! $this->isSafeHttpsUrl($url)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'title' => $this->stringValue($item, 'title'),
                'url' => $url,
            ];
        }, $items)));
    }

    /** @return array<int, array{id: string, title: string, url: string}> */
    private function safeInternalLinks(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            $url = $this->safeInternalUrl($this->stringValue($item, 'url'));
            if ($url === '') {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'title' => $this->stringValue($item, 'title'),
                'url' => $url,
            ];
        }, $items)));
    }

    private function safeInternalUrl(string $url): string
    {
        if (preg_match('~^/(?:ar|en)/(?:e-services|contact)(?:[/?#]|$)~', $url) !== 1 || str_starts_with($url, '//')) {
            return '';
        }

        return $url;
    }

    private function isSafeHttpsUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && ! isset($parts['user'], $parts['pass']);
    }

    private function detailTargetKey(string $slug): string
    {
        return match ($slug) {
            'library', 'staff-email', 'it-support' => 'e_services.'.$slug,
            default => throw new \InvalidArgumentException('Unsupported E-Services detail page.'),
        };
    }

    private function detailSettingsGroup(string $slug): string
    {
        return 'e_services_'.str_replace('-', '_', $slug).'_page';
    }

    /** @return array<int, array<string, string>> */
    private function services(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'title' => $this->stringValue($item, 'title'),
                'summary' => $this->stringValue($item, 'summary'),
                'icon' => $this->stringValue($item, 'icon'),
                'url' => $this->stringValue($item, 'url'),
                'button' => $this->stringValue($item, 'button'),
            ];
        }, $items)));
    }

    /** @return array<int, array<string, string>> */
    private function supportCards(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'eyebrow' => $this->stringValue($item, 'eyebrow'),
                'title' => $this->stringValue($item, 'title'),
                'summary' => $this->stringValue($item, 'summary'),
            ];
        }, $items)));
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
