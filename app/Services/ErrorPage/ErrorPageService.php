<?php

declare(strict_types=1);

namespace App\Services\ErrorPage;

use App\Contracts\ErrorPage\ErrorPageServiceInterface;
use App\DTOs\ErrorPage\ErrorPageContentDTO;
use App\DTOs\ErrorPage\ErrorPageLinkDTO;
use App\Support\ErrorPageLocale;
use Illuminate\Support\Facades\Route;

/**
 * Builds error page content from constants and the route table only.
 *
 * Deliberately infrastructure-free: no database, no cache, no CMS lookups and
 * no translator. Copy lives in this class rather than in lang/ because the
 * 500/503 views must show Arabic and English at the same time, and because a
 * failing outage page is strictly worse than the stock Laravel page.
 */
final class ErrorPageService implements ErrorPageServiceInterface
{
    /**
     * Statuses that presume healthy infrastructure and may therefore render
     * inside the full public layout (navigation, footer, compiled assets).
     *
     * @var list<int>
     */
    private const FULL_LAYOUT_STATUSES = [403, 404, 419, 429];

    /**
     * Bilingual copy per status.
     *
     * @var array<int, array{ar: array{title: string, message: string}, en: array{title: string, message: string}}>
     */
    private const COPY = [
        403 => [
            'ar' => [
                'title' => 'الوصول غير مسموح',
                'message' => 'لا تملك الصلاحية اللازمة لعرض هذه الصفحة. إن كنت تعتقد أن هذا خطأ، تواصل مع إدارة الموقع.',
            ],
            'en' => [
                'title' => 'Access denied',
                'message' => 'You do not have permission to view this page. If you believe this is a mistake, please contact the site administrators.',
            ],
        ],
        404 => [
            'ar' => [
                'title' => 'الصفحة غير موجودة',
                'message' => 'قد يكون الرابط قديمًا أو تكون الصفحة قد نُقلت إلى عنوان جديد ضمن الموقع. يمكنك المتابعة من الروابط أدناه.',
            ],
            'en' => [
                'title' => 'Page not found',
                'message' => 'The link may be outdated, or the page may have moved to a new address on this site. You can continue from the links below.',
            ],
        ],
        419 => [
            'ar' => [
                'title' => 'انتهت صلاحية الجلسة',
                'message' => 'انتهت صلاحية الجلسة لأسباب أمنية. يرجى تحديث الصفحة وإعادة إرسال النموذج.',
            ],
            'en' => [
                'title' => 'Your session expired',
                'message' => 'Your session expired for security reasons. Please refresh the page and submit the form again.',
            ],
        ],
        429 => [
            'ar' => [
                'title' => 'طلبات كثيرة جدًا',
                'message' => 'تم استقبال عدد كبير من الطلبات من هذا الجهاز خلال وقت قصير. يرجى الانتظار قليلًا ثم المحاولة مجددًا.',
            ],
            'en' => [
                'title' => 'Too many requests',
                'message' => 'Too many requests were received from this device in a short time. Please wait a moment and try again.',
            ],
        ],
        500 => [
            'ar' => [
                'title' => 'خطأ في الخادم',
                'message' => 'حدث خلل غير متوقع من جانبنا. فريق الجامعة التقني على علم بالمشكلة ويعمل على معالجتها.',
            ],
            'en' => [
                'title' => 'Server error',
                'message' => 'Something went wrong on our side. The university technical team has been notified and is working on it.',
            ],
        ],
        503 => [
            'ar' => [
                'title' => 'الموقع قيد الصيانة',
                'message' => 'الموقع متوقف مؤقتًا لأعمال صيانة مجدولة. يرجى المحاولة بعد وقت قصير.',
            ],
            'en' => [
                'title' => 'Under maintenance',
                'message' => 'The site is temporarily offline for scheduled maintenance. Please try again shortly.',
            ],
        ],
    ];

    /**
     * Fallback copy for any status without a dedicated entry.
     *
     * @var array<string, array{title: string, message: string}>
     */
    private const DEFAULT_COPY = [
        'ar' => [
            'title' => 'تعذّر إتمام الطلب',
            'message' => 'حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى.',
        ],
        'en' => [
            'title' => 'Request could not be completed',
            'message' => 'Something went wrong while handling your request. Please try again.',
        ],
    ];

    /**
     * Route names offered as ways back into the site, in display order.
     * Each is rendered only when the route actually exists.
     *
     * @var array<string, array{ar: string, en: string}>
     */
    private const RETURN_ROUTES = [
        'public.home' => ['ar' => 'الصفحة الرئيسية', 'en' => 'Homepage'],
        'public.about.landing' => ['ar' => 'عن الجامعة', 'en' => 'About the university'],
        'public.admissions.landing' => ['ar' => 'القبول والتسجيل', 'en' => 'Admissions'],
        'public.faculties.hub' => ['ar' => 'الكليات', 'en' => 'Faculties'],
        'public.news.index' => ['ar' => 'الأخبار', 'en' => 'News'],
        'public.contact' => ['ar' => 'اتصل بنا', 'en' => 'Contact us'],
    ];

    /**
     * Candidate route names for the site search page, most specific first.
     *
     * @var list<string>
     */
    private const SEARCH_ROUTES = ['public.search', 'public.search.index', 'search'];

    public function content(int $status, string $requestPath, ?string $acceptLanguage = null): ErrorPageContentDTO
    {
        $locale = ErrorPageLocale::resolve($requestPath, $acceptLanguage);
        $copy = self::COPY[$status] ?? self::DEFAULT_COPY;

        /** @var array{title: string, message: string} $arabic */
        $arabic = $copy['ar'] ?? self::DEFAULT_COPY['ar'];
        /** @var array{title: string, message: string} $english */
        $english = $copy['en'] ?? self::DEFAULT_COPY['en'];
        $active = $locale === 'ar' ? $arabic : $english;

        return new ErrorPageContentDTO(
            status: $status,
            locale: $locale,
            direction: ErrorPageLocale::direction($locale),
            title: $active['title'],
            message: $active['message'],
            arabicTitle: $arabic['title'],
            arabicMessage: $arabic['message'],
            englishTitle: $english['title'],
            englishMessage: $english['message'],
            homeUrl: $this->homeUrl($locale),
            searchUrl: $this->searchUrl($locale),
            logoUrl: $this->logoUrl(),
            links: $this->supportsFullLayout($status) ? $this->returnLinks($locale) : [],
        );
    }

    public function supportsFullLayout(int $status): bool
    {
        return in_array($status, self::FULL_LAYOUT_STATUSES, true);
    }

    /**
     * @return array<int, ErrorPageLinkDTO>
     */
    private function returnLinks(string $locale): array
    {
        $links = [];

        foreach (self::RETURN_ROUTES as $name => $labels) {
            $url = $this->routeUrl($name, $locale);

            if ($url === null) {
                continue;
            }

            $links[] = new ErrorPageLinkDTO(
                label: $labels[$locale] ?? $labels['en'],
                url: $url,
                isPrimary: $name === 'public.home',
            );
        }

        return $links;
    }

    /**
     * Resolve a localized route URL, or null when the route is not registered.
     */
    private function routeUrl(string $name, string $locale): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name, ['locale' => $locale]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function homeUrl(string $locale): string
    {
        return $this->routeUrl('public.home', $locale) ?? url('/'.$locale);
    }

    /**
     * The search page is not merged in every branch, so it is probed by name
     * and simply omitted when absent.
     */
    private function searchUrl(string $locale): ?string
    {
        foreach (self::SEARCH_ROUTES as $name) {
            $url = $this->routeUrl($name, $locale);

            if ($url !== null) {
                return $url.'?q=';
            }
        }

        return null;
    }

    /**
     * Logo URL built from the asset root only. The views pair it with an inline
     * mark so branding survives a missing or unbuilt asset.
     */
    private function logoUrl(): string
    {
        try {
            return asset('images/single-logo.png');
        } catch (\Throwable) {
            return '/images/single-logo.png';
        }
    }
}
