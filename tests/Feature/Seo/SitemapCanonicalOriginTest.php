<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Contracts\Seo\SitemapServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The pre-generated sitemaps hold absolute URLs, so they are only valid for the
 * host they were written under. A domain cutover changes that host and touches
 * nothing else — no content changes, so no cache tag is flushed, so the
 * freshness marker keeps reporting current while every <loc> advertises the
 * domain being retired.
 *
 * Apache serves those files before PHP is reached, and this host has no shell,
 * so the mistake would be both invisible from inside the application and
 * unfixable from outside it. This is the check that turns it into a red gate
 * before DNS moves rather than an SEO problem discovered afterwards.
 */
final class SitemapCanonicalOriginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config()->set('edge.canonical_url', 'https://v2.spu.edu.sy');
        Artisan::call('sitemap:generate');
    }

    protected function tearDown(): void
    {
        File::delete(public_path('sitemap.xml'));
        File::deleteDirectory(public_path('sitemaps'));

        parent::tearDown();
    }

    public function test_files_written_for_the_current_origin_are_not_foreign(): void
    {
        $service = app(SitemapServiceInterface::class);

        $this->assertStringContainsString('v2.spu.edu.sy', (string) file_get_contents(public_path('sitemap.xml')));
        $this->assertFalse($service->staticFilesAdvertiseAForeignOrigin());
        $this->assertFalse($service->staticFilesAreStale());
    }

    public function test_moving_the_canonical_origin_makes_the_files_stale(): void
    {
        // Exactly what happens at cutover: the config moves, the files do not.
        config()->set('edge.canonical_url', 'https://spu.edu.sy');

        $service = app(SitemapServiceInterface::class);

        $this->assertTrue(
            $service->staticFilesAdvertiseAForeignOrigin(),
            'A sitemap advertising the retired host must be detected.',
        );
        $this->assertTrue(
            $service->staticFilesAreStale(),
            'Staleness must account for the origin, not only for content changes.',
        );
    }

    public function test_the_launch_gate_fails_on_a_sitemap_written_for_another_host(): void
    {
        config()->set('edge.canonical_url', 'https://spu.edu.sy');

        Artisan::call('launch:validate');

        $this->assertStringContainsString('advertises a different host', Artisan::output());
    }

    public function test_regenerating_after_the_move_clears_it(): void
    {
        config()->set('edge.canonical_url', 'https://spu.edu.sy');
        Artisan::call('sitemap:generate');

        $service = app(SitemapServiceInterface::class);

        $this->assertFalse($service->staticFilesAdvertiseAForeignOrigin());
        $this->assertStringContainsString('https://spu.edu.sy', (string) file_get_contents(public_path('sitemap.xml')));
    }
}
