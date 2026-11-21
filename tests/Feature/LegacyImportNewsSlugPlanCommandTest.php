<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyImportNewsSlugPlanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_plan_command_outputs_dry_run_summary_without_mutating(): void
    {
        $oldSlug = str_repeat('legacy-command-slug-', 5);
        $articleId = $this->createArticle($oldSlug);

        $this->artisan('legacy-import:news-slug-plan', ['--limit' => 5])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy News Slug Cleanup Plan')
            ->expectsOutputToContain('Status: dry_run_only')
            ->expectsOutputToContain('Dry-run only');

        $this->assertDatabaseHas('news_articles', ['id' => $articleId, 'slug' => $oldSlug]);
    }

    public function test_slug_plan_command_can_output_json(): void
    {
        $this->createArticle(str_repeat('legacy-json-slug-', 6));

        $this->artisan('legacy-import:news-slug-plan', ['--json' => true, '--all' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('"status": "dry_run_only"');
    }

    private function createArticle(string $slug): int
    {
        return (int) DB::table('news_articles')->insertGetId([
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 901,
            'legacy_service_type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
