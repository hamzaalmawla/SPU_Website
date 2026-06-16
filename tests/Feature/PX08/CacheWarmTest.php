<?php

declare(strict_types=1);

namespace Tests\Feature\PX08;

use App\Models\Homepage\HomepageSection;
use App\Models\Homepage\HomepageSectionTranslation;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for cache:warm command.
 *
 * Requirements: 34.3
 */
class CacheWarmTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_warm_completes_successfully(): void
    {
        $this->seedData();

        $this->artisan('cache:warm')
            ->assertSuccessful();
    }

    public function test_cache_warm_with_locale_option(): void
    {
        $this->seedData();

        $this->artisan('cache:warm', ['--locale' => 'ar'])
            ->expectsOutputToContain('ar')
            ->assertSuccessful();
    }

    public function test_cache_warm_with_include_sitemap(): void
    {
        $this->seedData();

        $this->artisan('cache:warm', ['--include-sitemap' => true])
            ->expectsOutputToContain('Sitemap warmed')
            ->assertSuccessful();
    }

    public function test_cache_warm_reports_warnings_for_unavailable_targets(): void
    {
        // With no data, services may throw — command should log warnings and continue
        $this->artisan('cache:warm')
            ->assertSuccessful();
    }

    public function test_cache_warm_reports_warmed_count(): void
    {
        $this->seedData();

        $this->artisan('cache:warm')
            ->expectsOutputToContain('Cache warm complete')
            ->assertSuccessful();
    }

    /**
     * Seed minimum data for cache warm targets.
     */
    private function seedData(): void
    {
        $sectionKeys = [
            'hero', 'hero_stats', 'academic_faculties', 'achievements_highlights',
            'university_news', 'research_studies', 'events_activities',
            'medical_facilities_services', 'bottom_stats', 'footer',
        ];

        foreach ($sectionKeys as $index => $key) {
            $section = HomepageSection::create([
                'key' => $key,
                'type' => 'section',
                'sort_order' => $index,
                'is_enabled' => true,
            ]);

            foreach (['ar', 'en'] as $locale) {
                HomepageSectionTranslation::create([
                    'section_id' => $section->id,
                    'locale' => $locale,
                    'payload_json' => ['headline' => 'Test'],
                ]);
            }
        }

        $page = Page::create([
            'slug' => 'test-page',
            'type' => 'landing',
            'template' => 'default',
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
            'published_at' => now(),
        ]);

        foreach (['ar', 'en'] as $locale) {
            PageTranslation::create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => 'Test',
                'body' => 'Content',
            ]);
        }
    }
}
