<?php

declare(strict_types=1);

namespace Tests\Feature\PX08;

use App\Contracts\Search\SearchIndexServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

        $output = $this->runValidate('production');

        $this->assertStringContainsString('CRITICAL: CACHE_STORE', $output);
        $this->assertStringContainsString('CRITICAL: SESSION_DRIVER', $output);
    }

    public function test_an_inline_queue_is_accepted_because_it_needs_no_worker(): void
    {
        // On a host where the worker cron cannot be installed, sync is the
        // configuration that actually delivers contact messages. Rejecting it
        // on principle would push the site toward the silent-loss setup.
        config()->set('queue.default', 'sync');

        $this->assertStringContainsString('delivered without a worker', $this->runValidate());
    }

    public function test_a_queue_nobody_is_consuming_fails_the_gate(): void
    {
        config()->set('queue.default', 'database');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->getTimestamp(),
            'created_at' => now()->subHour()->getTimestamp(),
        ]);

        $output = $this->runValidate();

        $this->assertStringContainsString('No worker is consuming the queue', $output);
        $this->assertStringContainsString('silently discarded', $output);
    }

    /**
     * The gate used to judge robots.txt against the environment it was asked to
     * validate FOR rather than the one the app is running AS. Before cutover
     * those differ on purpose, so this went red on every staging deploy — and a
     * gate that is red every time is a gate nobody reads.
     */
    public function test_robots_is_judged_against_the_running_environment_not_the_target(): void
    {
        $output = $this->runValidate('production');

        $this->assertStringContainsString('robots.txt correctness', $output);
        $this->assertStringNotContainsString(
            'robots.txt does not match',
            $output,
            'Serving Disallow: / while running as testing is correct, not a failure.',
        );
    }

    /**
     * Green above is not the whole truth: the site is invisible to search
     * engines until APP_ENV flips. The gate has to say that out loud, because
     * forgetting the flip launches a university homepage nothing will index.
     */
    public function test_it_warns_that_the_domain_is_not_indexable_before_cutover(): void
    {
        $output = $this->runValidate('production');

        $this->assertStringContainsString('robots.txt indexing policy', $output);
        $this->assertStringContainsString('APP_ENV=production', $output);
    }

    /**
     * The check built a request for host "localhost", so EnforcePublicOrigin
     * returned a 301 before the preview controller ran, and the gate called
     * that redirect "responded successfully without a token" — a security
     * failure reported on every deploy that was really a misaddressed request.
     */
    public function test_preview_safety_is_checked_against_the_canonical_host(): void
    {
        config()->set('edge.enforce_canonical_host', true);
        config()->set('edge.canonical_url', 'https://v2.spu.edu.sy');

        $output = $this->runValidate();

        $this->assertStringContainsString('Preview route rejects missing token access', $output);
        $this->assertStringNotContainsString('responded successfully without a token', $output);
    }

    private function runValidate(string $environment = 'staging'): string
    {
        Artisan::call('launch:validate', ['--environment' => $environment]);

        return Artisan::output();
    }

    private function seedSearchableContent(): void
    {
        $this->seed(DatabaseSeeder::class);
    }
}
