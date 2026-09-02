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
     * v2 runs with APP_ENV=production so it behaves like the real thing, while
     * its docroot carries a Disallow overlay because it must not be indexed
     * before cutover. Judging the file against the runtime environment demanded
     * Allow: / from a host whose entire job is to stay out of the index, and so
     * failed on every staging deploy. What the file must agree with is the
     * environment the deploy is FOR.
     */
    public function test_a_staging_deploy_is_not_asked_to_invite_crawling(): void
    {
        $output = $this->runValidate('staging');

        $this->assertStringContainsString('robots.txt correctness', $output);
        $this->assertStringNotContainsString('[FAIL] robots.txt correctness', $output);
    }

    /**
     * The trap this check exists to catch. The overlay is deleted by hand at
     * cutover (Docs/V2_PRE_CUTOVER_ACTIONS.md section C); if it is forgotten,
     * the live university site serves Disallow: / and leaves the search index.
     * Nothing else in the pipeline would notice.
     */
    public function test_a_production_deploy_still_behind_the_staging_overlay_fails_loudly(): void
    {
        $overlay = public_path('robots.txt');
        $existing = is_file($overlay) ? file_get_contents($overlay) : null;

        file_put_contents($overlay, "User-agent: *\nDisallow: /\n");

        try {
            $output = $this->runValidate('production');

            $this->assertStringContainsString('[FAIL] robots.txt correctness', $output);
            $this->assertStringContainsString('disappears from search results', $output);
        } finally {
            $existing === null ? @unlink($overlay) : file_put_contents($overlay, $existing);
        }
    }

    /**
     * A Sitemap line does nothing on a host that forbids crawling, and
     * requiring one failed this check for the absence of a line that would have
     * had no effect. It is still required where it matters.
     */
    public function test_the_sitemap_line_is_required_only_where_crawling_is_invited(): void
    {
        $overlay = public_path('robots.txt');
        $existing = is_file($overlay) ? file_get_contents($overlay) : null;

        file_put_contents($overlay, "User-agent: *\nAllow: /\n");

        try {
            $output = $this->runValidate('production');

            $this->assertStringContainsString('[FAIL] robots.txt correctness', $output);
            $this->assertStringContainsString('does not advertise', $output);
        } finally {
            $existing === null ? @unlink($overlay) : file_put_contents($overlay, $existing);
        }
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
