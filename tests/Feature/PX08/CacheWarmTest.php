<?php

declare(strict_types=1);

namespace Tests\Feature\PX08;

use App\Contracts\Seo\SitemapServiceInterface;
use App\DTOs\Seo\SitemapEntryDTO;
use App\Models\Homepage\HomepageSection;
use App\Models\Homepage\HomepageSectionTranslation;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature tests for cache:warm command.
 *
 * Requirements: 34.3
 */
class CacheWarmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The sitemap covers every public URL; the landing-page warm resolves only
     * CMS pages. Every news article and publication in the sitemap therefore
     * came back null and was reported as "unavailable" — 4,544 warnings on the
     * deploy of 1 September, against a site with nothing wrong with it. A log
     * that noisy is a log nobody reads, which is how a separate bug that turned
     * every public page into a 503 sat in the same output unnoticed.
     */
    public function test_a_url_served_by_another_controller_is_not_reported_as_a_problem(): void
    {
        $this->seedData();
        // A real news URL: served by public.news.show, with no CMS page behind it.
        $this->fakeSitemapEntries(['https://example.test/ar/news/some-article-1234']);

        $output = $this->warmAndCapture();

        $this->assertStringNotContainsString(
            'some-article-1234',
            $output,
            'A news article has no CMS page behind it by design; that is not a warning.',
        );
    }

    /**
     * The distinction worth keeping. A sitemap entry routed to the CMS page
     * controller with nothing published behind it advertises a 404 to search
     * engines, and it used to be indistinguishable from the thousands of benign
     * lines around it.
     */
    public function test_a_sitemap_entry_with_no_published_page_is_still_reported(): void
    {
        $this->seedData();
        $this->fakeSitemapEntries(['https://example.test/ar/no-such-section/nothing-here']);

        $output = $this->warmAndCapture();

        $this->assertStringContainsString('but nothing publishes it', $output);
    }

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
    /**
     * @param  list<string>  $urls
     */
    private function fakeSitemapEntries(array $urls): void
    {
        $entries = collect($urls)->map(fn (string $url): SitemapEntryDTO => new SitemapEntryDTO(
            loc: $url,
            lastmod: '2026-09-01',
            changefreq: null,
            priority: null,
            alternates: [],
        ));

        $sitemap = \Mockery::mock(SitemapServiceInterface::class);
        $sitemap->shouldReceive('generateEntries')->andReturn($entries);
        $sitemap->shouldReceive('generateSitemapXml')->andReturn('<urlset/>');
        $sitemap->shouldIgnoreMissing();

        $this->app->instance(SitemapServiceInterface::class, $sitemap);
    }

    private function warmAndCapture(): string
    {
        $exitCode = Artisan::call('cache:warm');

        $this->assertSame(0, $exitCode);

        return Artisan::output();
    }

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
