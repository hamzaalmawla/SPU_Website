<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyNewsSlugCleanupPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyNewsSlugCleanupPlannerServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LegacyNewsSlugCleanupPlannerServiceInterface::class);
    }

    public function test_plan_proposes_short_slug_and_redirect_pairs_without_mutating_article(): void
    {
        $oldSlug = str_repeat('legacy-news-title-', 7);
        $articleId = $this->createArticle($oldSlug, 501, 3);

        $plan = $this->service->plan(10);
        $item = $plan->items->first();

        $this->assertSame('dry_run_only', $plan->status);
        $this->assertSame(1, $plan->totalLongSlugRows);
        $this->assertSame(1, $plan->plannedRows);
        $this->assertSame(0, $plan->omittedRows);
        $this->assertSame($articleId, $item->articleId);
        $this->assertSame($oldSlug, $item->oldSlug);
        $this->assertLessThanOrEqual(80, $item->proposedSlugLength);
        $this->assertTrue($item->redirectRequired);
        $this->assertSame('/ar/news/'.$oldSlug, $item->redirectFromAr);
        $this->assertSame('/ar/news/'.$item->proposedSlug, $item->redirectToAr);
        $this->assertSame('/en/news/'.$oldSlug, $item->redirectFromEn);
        $this->assertSame('/en/news/'.$item->proposedSlug, $item->redirectToEn);
        $this->assertDatabaseHas('news_articles', ['id' => $articleId, 'slug' => $oldSlug]);
    }

    public function test_plan_reserves_existing_and_planned_slugs_for_collisions(): void
    {
        $oldSlug = str_repeat('same-prefix-', 9);
        $base = rtrim(substr($oldSlug, 0, 80), '-');
        $this->createArticle($base, null, null);
        $firstLongId = $this->createArticle($oldSlug, 601, 3);
        $secondLongId = $this->createArticle($oldSlug.'-second', 602, 3);

        $plan = $this->service->plan(null);
        $items = $plan->items->keyBy('articleId');

        $this->assertSame(2, $plan->totalLongSlugRows);
        $this->assertSame(2, $plan->plannedRows);
        $this->assertSame(2, $plan->collisionAdjustedRows);
        $this->assertSame(rtrim(substr($base, 0, 78), '-').'-1', $items[$firstLongId]->proposedSlug);
        $this->assertSame(rtrim(substr($base, 0, 78), '-').'-2', $items[$secondLongId]->proposedSlug);
    }

    public function test_plan_limit_reports_omitted_rows(): void
    {
        $this->createArticle(str_repeat('first-long-slug-', 6), 701, 3);
        $this->createArticle(str_repeat('second-long-slug-', 6), 702, 4);

        $plan = $this->service->plan(1);

        $this->assertSame(2, $plan->totalLongSlugRows);
        $this->assertSame(1, $plan->plannedRows);
        $this->assertSame(1, $plan->omittedRows);
        $this->assertSame(1, $plan->items->count());
    }

    public function test_plan_reports_no_changes_when_all_slugs_are_short(): void
    {
        $this->createArticle('short-news-slug', null, null);

        $plan = $this->service->plan(10);

        $this->assertSame('no_changes', $plan->status);
        $this->assertSame(0, $plan->totalLongSlugRows);
        $this->assertSame(0, $plan->plannedRows);
        $this->assertTrue($plan->items->isEmpty());
    }

    private function createArticle(string $slug, ?int $legacySourceId, ?int $legacyServiceType): int
    {
        return (int) DB::table('news_articles')->insertGetId([
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => $legacySourceId !== null ? 'jx_categories' : null,
            'legacy_source_id' => $legacySourceId,
            'legacy_service_type' => $legacyServiceType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
