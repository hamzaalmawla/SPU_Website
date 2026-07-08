<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsSlugCleanupApplyServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyNewsSlugCleanupApplyServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyNewsSlugCleanupApplyServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LegacyNewsSlugCleanupApplyServiceInterface::class);
    }

    public function test_apply_updates_slug_and_creates_exact_redirects_in_one_run(): void
    {
        $oldSlug = str_repeat('legacy-apply-slug-', 6);
        $articleId = $this->createArticle($oldSlug);

        $result = $this->service->apply(null);
        $article = DB::table('news_articles')->where('id', $articleId)->first();

        $this->assertSame('applied', $result->status);
        $this->assertSame(1, $result->plannedRows);
        $this->assertSame(1, $result->updatedArticles);
        $this->assertSame(2, $result->createdRedirects);
        $this->assertSame(0, $result->updatedRedirects);
        $this->assertNotNull($article);
        $this->assertNotSame($oldSlug, $article->slug);
        $this->assertLessThanOrEqual(80, strlen((string) $article->slug));
        $this->assertDatabaseHas('legacy_exact_redirects', [
            'legacy_path' => '/ar/news/'.$oldSlug,
            'destination_url' => '/ar/news/'.$article->slug,
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('legacy_exact_redirects', [
            'legacy_path' => '/en/news/'.$oldSlug,
            'destination_url' => '/en/news/'.$article->slug,
            'status_code' => 301,
            'locale' => 'en',
            'is_active' => true,
        ]);
    }

    public function test_apply_updates_existing_redirects_instead_of_duplicating(): void
    {
        $oldSlug = str_repeat('legacy-existing-redirect-', 4);
        $articleId = $this->createArticle($oldSlug);
        DB::table('legacy_exact_redirects')->insert([
            'legacy_path' => '/ar/news/'.$oldSlug,
            'destination_url' => '/ar/news/wrong-destination',
            'status_code' => 302,
            'locale' => 'ar',
            'is_active' => false,
            'hit_count' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->apply(null);
        $article = DB::table('news_articles')->where('id', $articleId)->first();

        $this->assertSame(1, $result->createdRedirects);
        $this->assertSame(1, $result->updatedRedirects);
        $this->assertDatabaseCount('legacy_exact_redirects', 2);
        $this->assertDatabaseHas('legacy_exact_redirects', [
            'legacy_path' => '/ar/news/'.$oldSlug,
            'destination_url' => '/ar/news/'.$article->slug,
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);
    }

    public function test_apply_is_idempotent_after_cleanup(): void
    {
        $this->createArticle(str_repeat('legacy-idempotent-slug-', 4));

        $first = $this->service->apply(null);
        $second = $this->service->apply(null);

        $this->assertSame('applied', $first->status);
        $this->assertSame('no_changes', $second->status);
        $this->assertDatabaseCount('legacy_exact_redirects', 2);
    }

    public function test_apply_limit_mutates_only_limited_rows(): void
    {
        $this->createArticle(str_repeat('legacy-limited-one-', 5));
        $this->createArticle(str_repeat('legacy-limited-two-', 5));

        $result = $this->service->apply(1);

        $this->assertSame(1, $result->plannedRows);
        $this->assertSame(1, $result->updatedArticles);
        $this->assertSame(1, DB::table('news_articles')->whereRaw('LENGTH(slug) > 80')->count());
        $this->assertDatabaseCount('legacy_exact_redirects', 2);
    }

    private function createArticle(string $slug): int
    {
        $legacySourceId = 1001 + DB::table('news_articles')->count();

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
