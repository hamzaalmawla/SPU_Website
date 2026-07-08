<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyImportNewsReviewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_review_command_reports_cleanup_required_without_mutating_data(): void
    {
        $categoryId = (int) DB::table('news_categories')->insertGetId([
            'slug' => 'news',
            'type' => 'news',
            'sort_order' => 1,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $articleId = (int) DB::table('news_articles')->insertGetId([
            'news_category_id' => $categoryId,
            'slug' => str_repeat('legacy-news-', 9),
            'status' => 'published',
            'published_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 300,
            'legacy_service_type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['ar' => 'عنوان', 'en' => 'Title'] as $locale => $title) {
            DB::table('news_article_translations')->insert([
                'news_article_id' => $articleId,
                'locale' => $locale,
                'title' => $title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('news_article_seo_meta')->insert([
                'news_article_id' => $articleId,
                'locale' => $locale,
                'meta_title' => $title,
                'robots' => 'index,follow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('legacy-import:news-review')
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy News Import Review')
            ->expectsOutputToContain('Status: cleanup_required')
            ->expectsOutputToContain('News import should remain in review/quarantine');

        $this->assertDatabaseHas('news_articles', ['id' => $articleId]);
        $this->assertDatabaseCount('news_articles', 1);
    }

    public function test_news_review_command_can_output_json(): void
    {
        $this->artisan('legacy-import:news-review', ['--json' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('"status": "review_ready"');
    }
}
