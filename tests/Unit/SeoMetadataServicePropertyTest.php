<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\SettingsServiceInterface;
use App\DTOs\PageSeoDTO;
use App\Services\SeoMetadataService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for SeoMetadataService.
 *
 * Feature: spu-homepage-admin-foundation
 */
#[Group('property')]
class SeoMetadataServicePropertyTest extends TestCase
{
    use PropertyTestHelpers;

    private SeoMetadataService $seoService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SettingsServiceInterface to avoid DB dependency for pure-function tests
        $defaultSeo = new PageSeoDTO(
            locale: 'ar',
            title: 'SPU Default',
            metaDescription: 'Default description',
            ogTitle: 'SPU Default OG',
            ogDescription: 'Default OG description',
            ogImage: 'https://spu.edu.sy/images/default-og.jpg',
            canonicalUrl: 'https://spu.edu.sy/ar',
            hreflang: [
                ['locale' => 'ar', 'url' => 'https://spu.edu.sy/ar'],
                ['locale' => 'en', 'url' => 'https://spu.edu.sy/en'],
            ],
            robots: 'index,follow',
        );

        $settingsMock = $this->createMock(SettingsServiceInterface::class);
        $settingsMock->method('getDefaultSeoSettings')
            ->willReturnCallback(fn (string $locale) => new PageSeoDTO(
                locale: $locale,
                title: 'SPU Default',
                metaDescription: 'Default description',
                ogTitle: 'SPU Default OG',
                ogDescription: 'Default OG description',
                ogImage: 'https://spu.edu.sy/images/default-og.jpg',
                canonicalUrl: 'https://spu.edu.sy/' . $locale,
                hreflang: [
                    ['locale' => 'ar', 'url' => 'https://spu.edu.sy/ar'],
                    ['locale' => 'en', 'url' => 'https://spu.edu.sy/en'],
                ],
                robots: 'index,follow',
            ));

        $this->seoService = new SeoMetadataService($settingsMock);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 1: Canonical URL is always absolute and locale-correct
    // Feature: spu-homepage-admin-foundation, Property 1: Canonical URL is always absolute and locale-correct
    // **Validates: Requirements 15.1, 15.2**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function canonicalUrlProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 120; $i++) {
            $locale = self::randomLocale();
            $path = '/' . $locale . '/' . self::randomSlugPath();
            $cases["iteration_{$i}"] = [$path, $locale];
        }

        return $cases;
    }

    #[DataProvider('canonicalUrlProvider')]
    public function test_canonical_url_is_always_absolute_and_locale_correct(string $path, string $locale): void
    {
        $result = $this->seoService->resolveCanonical($path, $locale);

        // Must be an absolute URL
        $this->assertMatchesRegularExpression(
            '/^https?:\/\//',
            $result,
            "Canonical URL must start with http:// or https://, got: {$result}"
        );

        // Must contain the correct locale prefix
        $this->assertStringContainsString(
            '/' . $locale,
            $result,
            "Canonical URL must contain locale prefix /{$locale}, got: {$result}"
        );
    }

    /**
     * Additional edge cases: homepage-style paths (just /{locale}).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function canonicalUrlHomepageProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 30; $i++) {
            $locale = self::randomLocale();
            $cases["homepage_{$i}"] = ['/' . $locale, $locale];
        }

        return $cases;
    }

    #[DataProvider('canonicalUrlHomepageProvider')]
    public function test_canonical_url_is_absolute_for_homepage_paths(string $path, string $locale): void
    {
        $result = $this->seoService->resolveCanonical($path, $locale);

        $this->assertMatchesRegularExpression('/^https?:\/\//', $result);
        $this->assertStringContainsString('/' . $locale, $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 2: Hreflang reciprocity
    // Feature: spu-homepage-admin-foundation, Property 2: Hreflang reciprocity
    // **Validates: Requirements 15.3**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function hreflangReciprocityProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 120; $i++) {
            $localeCount = random_int(1, 2);
            $localePathMap = [];

            if ($localeCount === 1) {
                $locale = self::randomLocale();
                $localePathMap[$locale] = '/' . $locale . '/' . self::randomSlugPath();
            } else {
                $slug = self::randomSlugPath();
                $localePathMap['ar'] = '/ar/' . $slug;
                $localePathMap['en'] = '/en/' . $slug;
            }

            $cases["iteration_{$i}"] = [$localePathMap];
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $localePathMap
     */
    #[DataProvider('hreflangReciprocityProvider')]
    public function test_hreflang_reciprocity(array $localePathMap): void
    {
        $result = $this->seoService->resolveHreflang($localePathMap);

        // Output count must match input count
        $this->assertCount(
            count($localePathMap),
            $result,
            'Hreflang output count must match input locale count'
        );

        // Output locales must exactly match input locales
        $inputLocales = array_keys($localePathMap);
        $outputLocales = array_map(fn (array $entry) => $entry['locale'], $result);
        sort($inputLocales);
        sort($outputLocales);

        $this->assertSame(
            $inputLocales,
            $outputLocales,
            'Hreflang output locales must match input locales'
        );

        // All URLs must be absolute
        foreach ($result as $entry) {
            $this->assertMatchesRegularExpression(
                '/^https?:\/\//',
                $entry['url'],
                "Hreflang URL must be absolute, got: {$entry['url']}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 3: SEO field resolution with fallback
    // Feature: spu-homepage-admin-foundation, Property 3: SEO field resolution with fallback
    // **Validates: Requirements 15.4, 15.5**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: array{meta_title: ?string, meta_description: ?string, og_title: ?string, og_description: ?string, og_image: ?string, canonical_url: ?string, robots: ?string}}>
     */
    public static function seoFieldFallbackProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 120; $i++) {
            $locale = self::randomLocale();
            $seoFields = self::randomSeoFields();
            $cases["iteration_{$i}"] = [$locale, $seoFields];
        }

        return $cases;
    }

    /**
     * Tests that buildFallback always produces a non-null title and respects
     * provided context values vs settings-backed defaults.
     *
     * @param  array{meta_title: ?string, meta_description: ?string, og_title: ?string, og_description: ?string, og_image: ?string, canonical_url: ?string, robots: ?string}  $seoFields
     */
    #[DataProvider('seoFieldFallbackProvider')]
    public function test_seo_field_resolution_with_fallback(string $locale, array $seoFields): void
    {
        $context = [];

        if ($seoFields['meta_title'] !== null) {
            $context['title'] = $seoFields['meta_title'];
        }
        if ($seoFields['meta_description'] !== null) {
            $context['meta_description'] = $seoFields['meta_description'];
        }
        if ($seoFields['og_title'] !== null) {
            $context['og_title'] = $seoFields['og_title'];
        }
        if ($seoFields['og_description'] !== null) {
            $context['og_description'] = $seoFields['og_description'];
        }
        if ($seoFields['og_image'] !== null) {
            $context['og_image'] = $seoFields['og_image'];
        }
        if ($seoFields['robots'] !== null) {
            $context['robots'] = $seoFields['robots'];
        }

        $path = '/' . $locale . '/' . self::randomSlugPath();
        $context['path'] = $path;

        $result = $this->seoService->buildFallback($locale, $context);

        // Title must NEVER be null
        $this->assertNotNull($result->title, 'SEO title must never be null');
        $this->assertNotEmpty($result->title, 'SEO title must never be empty');

        // When page-specific values are provided, they must be used
        if ($seoFields['meta_title'] !== null) {
            $this->assertSame(
                $seoFields['meta_title'],
                $result->title,
                'Page-specific meta_title must be used when present'
            );
        } else {
            // Fallback to default
            $this->assertSame(
                'SPU Default',
                $result->title,
                'Default title must be used when page-specific is null'
            );
        }

        if ($seoFields['meta_description'] !== null) {
            $this->assertSame(
                $seoFields['meta_description'],
                $result->metaDescription,
                'Page-specific meta_description must be used when present'
            );
        }

        if ($seoFields['og_title'] !== null) {
            $this->assertSame(
                $seoFields['og_title'],
                $result->ogTitle,
                'Page-specific og_title must be used when present'
            );
        }

        if ($seoFields['og_description'] !== null) {
            $this->assertSame(
                $seoFields['og_description'],
                $result->ogDescription,
                'Page-specific og_description must be used when present'
            );
        }

        if ($seoFields['og_image'] !== null) {
            $this->assertSame(
                $seoFields['og_image'],
                $result->ogImage,
                'Page-specific og_image must be used when present'
            );
        }

        if ($seoFields['robots'] !== null) {
            $this->assertSame(
                $seoFields['robots'],
                $result->robots,
                'Page-specific robots must be used when present'
            );
        }

        // Canonical URL must always be absolute
        $this->assertMatchesRegularExpression(
            '/^https?:\/\//',
            $result->canonicalUrl,
            'Canonical URL in fallback must be absolute'
        );

        // Robots must never be null (fallback to 'index,follow')
        $this->assertNotNull($result->robots, 'Robots directive must never be null');
    }
}
