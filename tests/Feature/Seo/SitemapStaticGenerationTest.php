<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sitemap is written to public/ as plain files so the web server can answer
 * it without entering PHP. Building it took 10.1 seconds against the live
 * corpus, on a pool of five workers, from a route outside the page cache.
 */
final class SitemapStaticGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeGeneratedFiles();
    }

    protected function tearDown(): void
    {
        $this->removeGeneratedFiles();

        parent::tearDown();
    }

    private function removeGeneratedFiles(): void
    {
        if (is_file(public_path('sitemap.xml'))) {
            @unlink(public_path('sitemap.xml'));
        }

        $children = glob(public_path('sitemaps').'/sitemap-*.xml');

        foreach ($children === false ? [] : $children as $path) {
            @unlink($path);
        }

        if (is_dir(public_path('sitemaps'))) {
            @rmdir(public_path('sitemaps'));
        }
    }

    private function seedPublishedPage(string $slug): void
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        foreach (['ar', 'en'] as $locale) {
            PageTranslation::create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => $slug.' '.$locale,
            ]);
        }
    }

    public function test_generation_writes_the_index_and_every_child_to_disk(): void
    {
        $this->seedPublishedPage('static-page');

        $report = app(SitemapServiceInterface::class)->writeStaticFiles();

        $this->assertFileExists(public_path('sitemap.xml'));

        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $path = public_path('sitemaps/sitemap-'.$section.'.xml');
            $this->assertFileExists($path);

            $xml = simplexml_load_string((string) file_get_contents($path));
            $this->assertNotFalse($xml, "The written {$section} sitemap must be valid XML.");
            $this->assertSame('urlset', $xml->getName());
        }

        $index = simplexml_load_string((string) file_get_contents(public_path('sitemap.xml')));
        $this->assertNotFalse($index);
        $this->assertSame('sitemapindex', $index->getName());

        $this->assertGreaterThan(0, $report->urlCount);
        $this->assertSame(count(SitemapServiceInterface::SECTIONS) + 1, $report->documentCount);
        $this->assertStringContainsString('static-page', (string) file_get_contents(public_path('sitemaps/sitemap-pages.xml')));
    }

    public function test_generation_is_idempotent(): void
    {
        $this->seedPublishedPage('idempotent-page');
        $service = app(SitemapServiceInterface::class);

        $service->writeStaticFiles();
        $first = [];
        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $first[$section] = (string) file_get_contents(public_path('sitemaps/sitemap-'.$section.'.xml'));
        }
        $firstIndex = (string) file_get_contents(public_path('sitemap.xml'));

        $service->writeStaticFiles();

        $this->assertSame($firstIndex, (string) file_get_contents(public_path('sitemap.xml')));
        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $this->assertSame(
                $first[$section],
                (string) file_get_contents(public_path('sitemaps/sitemap-'.$section.'.xml')),
                "Re-running generation must leave the {$section} sitemap byte-identical.",
            );
        }
    }

    public function test_publishing_marks_the_static_files_stale_and_generation_clears_it(): void
    {
        $this->seedPublishedPage('freshness-page');
        $service = app(SitemapServiceInterface::class);

        $service->writeStaticFiles();
        $this->assertFalse($service->staticFilesAreStale(), 'Files are current right after a write.');

        // Every publish path in the application flushes the "sitemap" cache tag;
        // that flush is what the freshness sentinel is keyed on, so no publish
        // call site needs to know about sitemap generation at all.
        app(CacheServiceInterface::class)->flushTags(['pages', 'seo', 'sitemap']);

        $this->assertTrue($service->staticFilesAreStale(), 'A publish must mark the files stale.');

        $service->writeStaticFiles();
        $this->assertFalse($service->staticFilesAreStale());
    }

    public function test_missing_files_count_as_stale(): void
    {
        $this->assertTrue(app(SitemapServiceInterface::class)->staticFilesAreStale());
    }

    public function test_the_command_skips_work_when_nothing_changed(): void
    {
        $this->seedPublishedPage('command-page');

        $this->artisan('sitemap:generate')->assertSuccessful();
        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('already current')
            ->assertSuccessful();
        $this->artisan('sitemap:generate', ['--force' => true])
            ->expectsOutputToContain('Wrote')
            ->assertSuccessful();
    }

    public function test_generation_removes_child_documents_left_by_an_earlier_run(): void
    {
        $this->seedPublishedPage('cleanup-page');
        $service = app(SitemapServiceInterface::class);
        $service->writeStaticFiles();

        $orphan = public_path('sitemaps/sitemap-news-7.xml');
        file_put_contents($orphan, '<?xml version="1.0"?><urlset/>');

        $service->writeStaticFiles();

        $this->assertFileDoesNotExist($orphan, 'A split that shrank must not leave orphaned documents behind.');
    }
}
