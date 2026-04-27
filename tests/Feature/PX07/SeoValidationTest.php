<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\Page;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for continuity:validate-seo command.
 *
 * Requirements: 27.5
 */
class SeoValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_identifies_pages_with_missing_seo(): void
    {
        $this->seedSeoDefaults();

        $page = Page::create([
            'slug' => 'weak-seo',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة بدون SEO',
        ]);

        // No SEO meta record — should be flagged
        $this->artisan('continuity:validate-seo', ['--format' => 'json'])
            ->assertFailed()
            ->expectsOutputToContain('missing_seo_record');
    }

    public function test_command_passes_with_complete_seo(): void
    {
        $this->seedSeoDefaults();

        $page = Page::create([
            'slug' => 'complete-seo',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة كاملة',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Complete Page',
        ]);

        foreach (['ar', 'en'] as $locale) {
            PageSeoMeta::create([
                'page_id' => $page->id,
                'locale' => $locale,
                'meta_title' => 'Title ' . $locale,
                'meta_description' => 'Description ' . $locale,
                'og_title' => 'OG Title ' . $locale,
                'og_description' => 'OG Description ' . $locale,
                'og_image_url' => 'https://example.com/image.jpg',
                'canonical_url' => 'https://example.com/' . $locale . '/complete-seo',
            ]);
        }

        $this->artisan('continuity:validate-seo', ['--format' => 'json'])
            ->assertSuccessful();
    }

    public function test_command_filters_by_locale(): void
    {
        $this->seedSeoDefaults();

        $page = Page::create([
            'slug' => 'locale-filter',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة',
        ]);

        $this->artisan('continuity:validate-seo', ['--locale' => 'ar', '--format' => 'json'])
            ->assertFailed();
    }

    private function seedSeoDefaults(): void
    {
        foreach (['ar', 'en'] as $locale) {
            Setting::updateOrCreate(
                ['key' => 'default_seo', 'group_key' => 'seo', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => [
                        'title' => 'SPU',
                        'meta_description' => 'Syrian Private University',
                        'og_title' => 'SPU',
                        'og_description' => 'Syrian Private University',
                        'og_image' => '',
                        'robots' => 'index,follow',
                    ],
                    'is_public' => true,
                ],
            );
        }
    }
}
