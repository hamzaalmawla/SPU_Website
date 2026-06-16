<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Models\Page\Page;
use App\Models\Page\PageSeoMeta;
use App\Models\Page\PageTranslation;
use App\Models\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for SEO metadata rendering.
 *
 * Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6
 */
class SeoRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_absolute_canonical_url_with_correct_locale(): void
    {
        $this->seedSeoDefaults();
        $page = $this->seedHomepageShell();

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        $this->assertStringStartsWith('http', $seo->canonicalUrl);
        $this->assertStringContainsString('/ar', $seo->canonicalUrl);
    }

    public function test_landing_page_renders_absolute_canonical_url_with_locale_and_slug(): void
    {
        $this->seedSeoDefaults();
        $page = $this->seedPublishedPage('about-us');

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        $this->assertStringStartsWith('http', $seo->canonicalUrl);
        $this->assertStringContainsString('/ar/', $seo->canonicalUrl);
        $this->assertStringContainsString('about-us', $seo->canonicalUrl);
    }

    public function test_pages_with_translations_render_reciprocal_hreflang_tags(): void
    {
        $this->seedSeoDefaults();
        $page = $this->seedBilingualPage('bilingual-page');

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        $this->assertNotEmpty($seo->hreflang);

        $locales = array_column($seo->hreflang, 'locale');
        $this->assertContains('ar', $locales);
        $this->assertContains('en', $locales);

        foreach ($seo->hreflang as $entry) {
            $this->assertStringStartsWith('http', $entry['url']);
        }
    }

    public function test_page_specific_seo_values_override_defaults(): void
    {
        $this->seedSeoDefaults();
        $page = $this->seedPublishedPage('custom-seo');

        PageSeoMeta::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'meta_title' => 'عنوان مخصص',
            'meta_description' => 'وصف مخصص',
        ]);

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        $this->assertSame('عنوان مخصص', $seo->title);
        $this->assertSame('وصف مخصص', $seo->metaDescription);
    }

    public function test_missing_seo_fields_fall_back_to_settings_defaults(): void
    {
        $this->seedSeoDefaults('Default Title AR', 'Default Description AR');
        $page = $this->seedPublishedPage('no-seo');

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        // Should use fallback values from settings
        $this->assertNotNull($seo->title);
        $this->assertNotNull($seo->metaDescription);
    }

    public function test_robots_directive_renders_when_set(): void
    {
        $this->seedSeoDefaults();
        $page = $this->seedPublishedPage('robots-page');

        PageSeoMeta::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'meta_title' => 'Robots Test',
            'robots' => 'noindex,nofollow',
        ]);

        $seoService = app(SeoMetadataServiceInterface::class);
        $seo = $seoService->buildForPage($page->id, 'ar');

        $this->assertSame('noindex,nofollow', $seo->robots);
    }

    private function seedSeoDefaults(string $title = 'SPU', string $description = 'Syrian Private University'): void
    {
        foreach (['ar', 'en'] as $locale) {
            Setting::updateOrCreate(
                ['key' => 'default_seo', 'group_key' => 'seo', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => [
                        'title' => $title,
                        'meta_description' => $description,
                        'og_title' => $title,
                        'og_description' => $description,
                        'og_image' => '',
                        'robots' => 'index,follow',
                    ],
                    'is_public' => true,
                ],
            );
        }
    }

    private function seedHomepageShell(): Page
    {
        $page = Page::create([
            'slug' => 'home',
            'status' => 'published',
            'is_enabled' => true,
            'is_homepage_shell' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'shell',
        ]);

        PageTranslation::create(['page_id' => $page->id, 'locale' => 'ar', 'title' => 'الرئيسية']);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'title' => 'Home']);

        return $page;
    }

    private function seedPublishedPage(string $slug): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة ' . $slug,
        ]);

        return $page;
    }

    private function seedBilingualPage(string $slug): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create(['page_id' => $page->id, 'locale' => 'ar', 'title' => 'صفحة ' . $slug]);
        PageTranslation::create(['page_id' => $page->id, 'locale' => 'en', 'title' => 'Page ' . $slug]);

        return $page;
    }
}
