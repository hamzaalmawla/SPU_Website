<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyExactRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyRedirectDecisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $articleId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->articleId = (int) DB::table('news_articles')->insertGetId([
            'slug' => 'legacy-story',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 325,
            'legacy_service_type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('news_article_translations')->insert([
            ['news_article_id' => $this->articleId, 'locale' => 'ar', 'title' => 'خبر', 'excerpt' => null, 'body' => null, 'created_at' => now(), 'updated_at' => now()],
            ['news_article_id' => $this->articleId, 'locale' => 'en', 'title' => 'News', 'excerpt' => null, 'body' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Storage::disk('local')->put('redirect-decisions.csv', $this->packet());
    }

    public function test_decisions_are_dry_run_by_default_and_require_write_approval(): void
    {
        $this->artisan('legacy-import:redirect-decisions', ['input' => 'redirect-decisions.csv', '--batch' => 'redirect-batch-1'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Redirect Decisions Dry Run')
            ->expectsOutputToContain('Eligible rows: 1')
            ->expectsOutputToContain('Created rows: 0');

        $this->assertDatabaseCount('legacy_exact_redirects', 0);

        $this->artisan('legacy-import:redirect-decisions', [
            'input' => 'redirect-decisions.csv', '--batch' => 'redirect-batch-1', '--write' => true,
        ])->assertExitCode(1)->expectsOutputToContain('requires --approve=legacy-redirect-apply');
    }

    public function test_approved_query_redirect_is_idempotent_alias_aware_and_rollback_safe(): void
    {
        $arguments = [
            'input' => 'redirect-decisions.csv',
            '--batch' => 'redirect-batch-1',
            '--write' => true,
            '--approve' => 'legacy-redirect-apply',
        ];

        $this->artisan('legacy-import:redirect-decisions', $arguments)
            ->assertExitCode(0)
            ->expectsOutputToContain('Created rows: 1');

        $this->assertDatabaseHas('legacy_exact_redirects', [
            'legacy_path' => '/index.php',
            'query_signature' => 'cat_id=325&dir=items&ex=2&lang=1&page=show&service=3',
            'destination_url' => '/ar/news/'.$this->articleId,
            'decision_batch' => 'redirect-batch-1',
        ]);
        $this->assertDatabaseHas('migration_logs', [
            'module' => 'redirect_continuity',
            'batch_name' => 'redirect-batch-1',
            'source_table' => 'jx_categories',
            'source_id' => 325,
            'target_table' => 'legacy_exact_redirects',
            'status' => 'success',
        ]);

        $this->get('/index.php?lang=1&cat_id=325&page=show&dir=items&ser=3&ex=2')
            ->assertStatus(301)
            ->assertRedirect('/ar/news/'.$this->articleId);

        $this->get('/index.php?lang=1&cat_id=999&page=show&dir=items&ser=3&ex=2')->assertNotFound();

        $this->artisan('legacy-import:redirect-decisions', $arguments)
            ->assertExitCode(0)
            ->expectsOutputToContain('Created rows: 0')
            ->expectsOutputToContain('Idempotent rows: 1');
        $this->assertDatabaseCount('legacy_exact_redirects', 1);

        $this->artisan('legacy-import:redirect-rollback', ['batch' => 'redirect-batch-1'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Batch redirects: 1')
            ->expectsOutputToContain('Deleted redirects: 0');
        $this->assertDatabaseCount('legacy_exact_redirects', 1);

        $this->artisan('legacy-import:redirect-rollback', ['batch' => 'redirect-batch-1', '--write' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('requires --approve=legacy-redirect-rollback');

        $this->artisan('legacy-import:redirect-rollback', [
            'batch' => 'redirect-batch-1', '--write' => true, '--approve' => 'legacy-redirect-rollback',
        ])->assertExitCode(0)->expectsOutputToContain('Deleted redirects: 1');

        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $this->assertSame('rolled_back', DB::table('legacy_redirect_decision_batches')->value('status'));
        $this->assertDatabaseHas('migration_logs', [
            'module' => 'redirect_continuity',
            'batch_name' => 'redirect-batch-1',
            'source_table' => 'legacy_exact_redirects',
            'status' => 'rolled_back',
        ]);
    }

    public function test_unsupported_locale_and_existing_conflicts_are_rejected(): void
    {
        Storage::disk('local')->put('unsupported.csv', $this->packet(locale: 'fr', target: '/ar/news/'.$this->articleId));

        $this->artisan('legacy-import:redirect-decisions', ['input' => 'unsupported.csv'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Eligible rows: 0')
            ->expectsOutputToContain('unsupported_locale: 1');

        LegacyExactRedirect::query()->create([
            'legacy_path' => '/index.php',
            'query_signature' => 'cat_id=325&dir=items&ex=2&lang=1&page=show&service=3',
            'destination_url' => '/ar/news/different-story',
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);

        $this->artisan('legacy-import:redirect-decisions', ['input' => 'redirect-decisions.csv'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Eligible rows: 0')
            ->expectsOutputToContain('existing_redirect_conflict: 1');
    }

    private function packet(string $locale = 'ar', ?string $target = null): string
    {
        $target ??= '/ar/news/'.$this->articleId;
        $headers = [
            'redirect_readiness', 'evidence_status', 'approval_status', 'approval_decision', 'approved_by',
            'approval_notes', 'blockers', 'legacy_path', 'normalized_path', 'query_signature', 'target_url',
            'status_code', 'handler_key', 'subsite', 'locale', 'source_table', 'source_id',
        ];
        $row = [
            'preview_ready', 'resolver_ready', 'runtime_resolver', 'redirect', 'continuity-reviewer',
            'Reviewed target', '', '/index.php?page=show&ex=2&dir=items&lang=1&ser=3&cat_id=325',
            '/index.php', 'cat_id=325&dir=items&ex=2&lang=1&page=show&service=3', $target,
            '301', 'root:items:show', 'root', $locale, 'jx_categories', '325',
        ];
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        fputcsv($stream, $row);
        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }
}
