<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyImportNewsSlugApplyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_command_refuses_without_approval_token(): void
    {
        $oldSlug = str_repeat('legacy-command-apply-', 5);
        $this->createArticle($oldSlug);

        $this->artisan('legacy-import:news-slug-apply', ['--all' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('Refusing to mutate news slugs');

        $this->assertDatabaseHas('news_articles', ['slug' => $oldSlug]);
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
    }

    public function test_apply_command_updates_with_approval_token(): void
    {
        $oldSlug = str_repeat('legacy-command-approved-', 4);
        $articleId = $this->createArticle($oldSlug);

        $this->artisan('legacy-import:news-slug-apply', [
            '--all' => true,
            '--approve' => 'news-slug-cleanup',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy News Slug Cleanup Apply')
            ->expectsOutputToContain('Status: applied');

        $article = DB::table('news_articles')->where('id', $articleId)->first();
        $this->assertNotSame($oldSlug, $article->slug);
        $this->assertDatabaseCount('legacy_exact_redirects', 2);
    }

    public function test_plan_command_exports_json_file(): void
    {
        $output = storage_path('app/legacy-news-slug-plan-test.json');
        @unlink($output);
        $this->createArticle(str_repeat('legacy-export-plan-', 5));

        $this->artisan('legacy-import:news-slug-plan', [
            '--all' => true,
            '--output' => $output,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Exported slug cleanup plan');

        $this->assertFileExists($output);
        $this->assertStringContainsString('"status": "dry_run_only"', (string) file_get_contents($output));
        @unlink($output);
    }

    private function createArticle(string $slug): int
    {
        $legacySourceId = 1101 + DB::table('news_articles')->count();

        return (int) DB::table('news_articles')->insertGetId([
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => $legacySourceId,
            'legacy_service_type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
