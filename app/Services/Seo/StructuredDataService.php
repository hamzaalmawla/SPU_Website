<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Seo\StructuredDataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Contact\ContactLinkDTO;
use App\DTOs\Seo\StructuredDataDTO;
use App\DTOs\Settings\FooterSettingsDTO;
use App\DTOs\Settings\SocialLinkDTO;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Builds schema.org JSON-LD from settings already loaded for the public shell.
 *
 * Every settings read here goes through SettingsService, which is cached for
 * `cache.settings_ttl`; and the pages that use this service are themselves
 * behind the public page cache. No new per-request database query is
 * introduced.
 *
 * Existing publication structured data (ScholarlyArticle + Dublin Core citation
 * meta, built in ResearchController) is untouched: this service is additive and
 * feeds the same `$structuredData` layout variable through the same contract.
 */
final class StructuredDataService implements StructuredDataServiceInterface
{
    /**
     * Candidate route names for site search, most specific first. The search
     * page is not merged on every branch, so SearchAction is emitted only when
     * one of these actually resolves.
     *
     * @var list<string>
     */
    private const SEARCH_ROUTES = ['public.search', 'public.search.index', 'search'];

    private const ARABIC_NAME = 'الجامعة السورية الخاصة';

    private const ENGLISH_NAME = 'Syrian Private University';

    /**
     * Per-request memo of footer settings, keyed by locale.
     *
     * @var array<string, FooterSettingsDTO|null>
     */
    private array $footerCache = [];

    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function organisation(string $locale): StructuredDataDTO
    {
        $footer = $this->footerSettings($locale);

        // Only the ACTIVE locale's settings are read. Pulling the other
        // locale's footer settings would be a cold cache key on every public
        // page and would show up as extra database queries — see
        // tests/Feature/PublicPageQueryBudgetTest.php. The alternate-language
        // name falls back to the canonical constant instead.
        $activeName = $this->brandTitle($locale)
            ?? ($locale === 'ar' ? self::ARABIC_NAME : self::ENGLISH_NAME);
        $alternateName = $locale === 'ar' ? self::ENGLISH_NAME : self::ARABIC_NAME;

        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CollegeOrUniversity',
            '@id' => $this->absolute('/').'#organisation',
            'name' => $activeName,
            'alternateName' => $alternateName,
            'description' => $footer?->brandSummary,
            'url' => $this->absolute('/'.$locale),
            'logo' => $this->logoUrl($footer),
            'image' => $this->logoUrl($footer),
            'address' => $this->postalAddress($footer, $locale),
            'contactPoint' => $this->contactPoints($locale),
            'email' => $footer?->email,
            'telephone' => $footer?->phone,
            'sameAs' => $this->socialProfiles($locale),
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        return new StructuredDataDTO('CollegeOrUniversity', $data);
    }

    public function website(string $locale): StructuredDataDTO
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->absolute('/').'#website',
            'name' => $this->brandTitle($locale)
                ?? ($locale === 'ar' ? self::ARABIC_NAME : self::ENGLISH_NAME),
            'url' => $this->absolute('/'.$locale),
            'inLanguage' => $locale,
            'publisher' => ['@id' => $this->absolute('/').'#organisation'],
        ];

        $searchTemplate = $this->searchUrlTemplate($locale);

        if ($searchTemplate !== null) {
            $data['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return new StructuredDataDTO('WebSite', $data);
    }

    public function breadcrumbs(string $locale, array $trail): StructuredDataDTO
    {
        $items = [[
            '@type' => 'ListItem',
            'position' => 1,
            'name' => $locale === 'ar' ? 'الرئيسية' : 'Home',
            'item' => $this->absolute('/'.$locale),
        ]];

        $position = 2;

        foreach ($trail as $crumb) {
            $name = is_array($crumb) && is_string($crumb['name'] ?? null) ? trim($crumb['name']) : '';
            $url = is_array($crumb) && is_string($crumb['url'] ?? null) ? trim($crumb['url']) : '';

            if ($name === '' || $url === '') {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $name,
                'item' => $this->absolute($url),
            ];

            $position++;
        }

        return new StructuredDataDTO('BreadcrumbList', [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    public function homepage(string $locale): StructuredDataDTO
    {
        $organisation = $this->organisation($locale)->data;
        $website = $this->website($locale)->data;

        unset($organisation['@context'], $website['@context']);

        return new StructuredDataDTO('Graph', [
            '@context' => 'https://schema.org',
            '@graph' => [$organisation, $website],
        ]);
    }

    /**
     * Settings are editor-managed and cached, but a structured-data block must
     * never be the reason a page 500s. Degrade to the built-in names instead.
     */
    private function footerSettings(string $locale): ?FooterSettingsDTO
    {
        if (array_key_exists($locale, $this->footerCache)) {
            return $this->footerCache[$locale];
        }

        try {
            // Already warmed for the active locale by getPublicSettings(),
            // which the public shell loads before this runs.
            return $this->footerCache[$locale] = $this->settingsService->getFooterSettings($locale);
        } catch (Throwable) {
            return $this->footerCache[$locale] = null;
        }
    }

    private function brandTitle(string $locale): ?string
    {
        $title = $this->footerSettings($locale)?->brandTitle;

        return is_string($title) && trim($title) !== '' ? trim($title) : null;
    }

    /**
     * @return array<string, string>|null
     */
    private function postalAddress(?FooterSettingsDTO $footer, string $locale): ?array
    {
        $address = $footer?->address;

        if (! is_string($address) || trim($address) === '') {
            return null;
        }

        return [
            '@type' => 'PostalAddress',
            'streetAddress' => trim($address),
            'addressCountry' => 'SY',
            'addressLocality' => $locale === 'ar' ? 'دمشق' : 'Damascus',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function contactPoints(string $locale): array
    {
        try {
            $links = $this->settingsService->getSocialContactSettings($locale)->contactLinks;
        } catch (Throwable) {
            return [];
        }

        $points = [];

        foreach ($links as $link) {
            if (! $link instanceof ContactLinkDTO || trim($link->value) === '') {
                continue;
            }

            $point = match (strtolower($link->type)) {
                'phone', 'tel', 'telephone' => ['telephone' => trim($link->value)],
                'email', 'mail' => ['email' => trim($link->value)],
                default => null,
            };

            if ($point === null) {
                continue;
            }

            $points[] = [
                '@type' => 'ContactPoint',
                'contactType' => $link->label !== '' ? $link->label : 'customer support',
                'availableLanguage' => $locale === 'ar' ? 'Arabic' : 'English',
                ...$point,
            ];
        }

        return $points;
    }

    /**
     * @return array<int, string>
     */
    private function socialProfiles(string $locale): array
    {
        try {
            $links = $this->settingsService->getSocialContactSettings($locale)->socialLinks;
        } catch (Throwable) {
            return [];
        }

        $profiles = [];

        foreach ($links as $link) {
            if (! $link instanceof SocialLinkDTO || ! $link->isEnabled) {
                continue;
            }

            $url = trim($link->url);

            if ($url === '' || $url === '#' || ! str_starts_with($url, 'http')) {
                continue;
            }

            $profiles[] = $url;
        }

        return array_values(array_unique($profiles));
    }

    private function logoUrl(?FooterSettingsDTO $footer): string
    {
        $logo = $footer?->logoUrl;

        if (is_string($logo) && trim($logo) !== '') {
            return $this->absolute(trim($logo));
        }

        return $this->absolute('/images/single-logo.png');
    }

    /**
     * The schema.org SearchAction template, or null when no search route is
     * registered on this branch yet.
     */
    private function searchUrlTemplate(string $locale): ?string
    {
        foreach (self::SEARCH_ROUTES as $name) {
            if (! Route::has($name)) {
                continue;
            }

            try {
                return route($name, ['locale' => $locale]).'?q={search_term_string}';
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function absolute(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }
}
