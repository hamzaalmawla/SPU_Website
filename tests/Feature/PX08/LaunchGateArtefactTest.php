<?php

declare(strict_types=1);

namespace Tests\Feature\PX08;

use App\Contracts\Search\SearchIndexServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The search index and the pre-generated sitemaps are build artefacts: neither is
 * in git, and neither appears by deploying code. Forget the commands and the site
 * comes up with a search box that finds nothing and a sitemap served from PHP on
 * a five-worker pool — both silent, both visible only to users.
 *
 * These also pin the production driver gate, which previously demanded Redis on a
 * host that has none and so could never pass.
 */
final class LaunchGateArtefactTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_when_the_search_index_was_never_built(): void
    {
        $output = $this->runValidate();

        $this->assertStringContainsString('Search index', $output);
        $this->assertStringContainsString('search:index', $output);
    }

    public function test_it_passes_once_the_search_index_is_populated(): void
    {
        $this->seedSearchableContent();
        app(SearchIndexServiceInterface::class)->rebuild();

        $this->assertStringContainsString('Search index is populated', $this->runValidate());
    }

    public function test_it_flags_a_sitemap_that_was_never_generated(): void
    {
        File::delete(public_path('sitemap.xml'));
        File::deleteDirectory(public_path('sitemaps'));

        $this->assertStringContainsString('sitemap:generate', $this->runValidate());
    }

    public function test_the_production_gate_accepts_the_drivers_this_host_actually_runs(): void
    {
        // No Redis exists on the deployed host; file and database are the
        // documented production choice, not a misconfiguration.
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'database');
        config()->set('queue.default', 'database');

        $output = $this->runValidate('production');

        foreach (['CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION'] as $setting) {
            $this->assertStringNotContainsString(
                "CRITICAL: {$setting}",
                $output,
                $setting.' must not be rejected for using a persistent non-Redis driver.',
            );
        }
    }

    public function test_the_production_gate_still_rejects_drivers_that_forget(): void
    {
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('queue.default', 'sync');

        $output = $this->runValidate('production');

        $this->assertStringContainsString('CRITICAL: CACHE_STORE', $output);
        $this->assertStringContainsString('CRITICAL: SESSION_DRIVER', $output);
        $this->assertStringContainsString('CRITICAL: QUEUE_CONNECTION', $output);
    }

    private function runValidate(string $environment = 'staging'): string
    {
        Artisan::call('launch:validate', ['--environment' => $environment]);

        return Artisan::output();
    }

    private function seedSearchableContent(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }
}
