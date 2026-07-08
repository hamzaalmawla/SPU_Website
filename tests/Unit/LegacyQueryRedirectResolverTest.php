<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyQueryRedirectResolverTest extends TestCase
{
    use RefreshDatabase;

    private LegacyQueryRedirectResolverInterface $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(LegacyQueryRedirectResolverInterface::class);
    }

    public function test_resolves_old_root_news_query_to_numeric_arabic_article_url(): void
    {
        $articleId = $this->createLegacyNewsArticle(5362, 3, 'legacy-news-slug');

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&service=3&cat_id=5362&lang=1');

        $this->assertNotNull($result);
        $this->assertSame(301, $result->statusCode);
        $this->assertSame('/ar/news/'.$articleId, $result->destinationUrl);
        $this->assertSame('legacy_query', $result->matchType);
    }

    public function test_resolves_old_root_announcement_query_to_numeric_english_article_url_with_aliases(): void
    {
        $articleId = $this->createLegacyNewsArticle(7001, 4, 'legacy-announcement-slug');

        $result = $this->resolver->resolve('/index.php', 'cat_id=7001&Ser=4&lang=2&dir=items&page=show');

        $this->assertNotNull($result);
        $this->assertSame('/en/news/'.$articleId, $result->destinationUrl);
    }

    public function test_parameter_order_does_not_break_legacy_query_resolution(): void
    {
        $articleId = $this->createLegacyNewsArticle(8001, 3, 'ordered-news-slug');

        $first = $this->resolver->resolve('/index.php', 'page=show&dir=items&service=3&cat_id=8001&lang=1');
        $second = $this->resolver->resolve('/index.php', 'lang=1&cat_id=8001&service=3&dir=items&page=show');

        $this->assertSame('/ar/news/'.$articleId, $first?->destinationUrl);
        $this->assertSame('/ar/news/'.$articleId, $second?->destinationUrl);
    }

    public function test_unmatched_whole_site_query_returns_null_instead_of_homepage_redirect(): void
    {
        $result = $this->resolver->resolve('/med/index.php', 'page=show&dir=items&service=3&cat_id=5362&lang=1');

        $this->assertNull($result);
    }

    public function test_resolves_imported_static_page_query_through_migration_log_mapping(): void
    {
        $pageId = $this->createPublishedPage('legacy-community-service');
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 12, 'pages', $pageId);

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&item_id=12&lang=2');

        $this->assertNotNull($result);
        $this->assertSame('/en/legacy-community-service', $result->destinationUrl);
        $this->assertSame('legacy_query', $result->matchType);
    }

    public function test_static_page_resolver_builds_parent_page_paths(): void
    {
        $parentId = $this->createPublishedPage('about-legacy');
        $pageId = $this->createPublishedPage('community-service', $parentId);
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 13, 'pages', $pageId);

        $result = $this->resolver->resolve('/index.php', 'lang=1&item_id=13&dir=items&page=show');

        $this->assertNotNull($result);
        $this->assertSame('/ar/about-legacy/community-service', $result->destinationUrl);
    }

    public function test_static_page_resolver_does_not_guess_from_generic_cat_id(): void
    {
        $pageId = $this->createPublishedPage('legacy-static-page');
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 14, 'pages', $pageId);

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&cat_id=14&lang=1');

        $this->assertNull($result);
    }

    private function createLegacyNewsArticle(int $legacySourceId, int $serviceType, string $slug): int
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
            'legacy_source_id' => $legacySourceId,
            'legacy_service_type' => $serviceType,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPublishedPage(string $slug, ?int $parentId = null): int
    {
        return (int) DB::table('pages')->insertGetId([
            'parent_id' => $parentId,
            'type' => 'landing_page',
            'template' => 'landing-page',
            'slug' => $slug,
            'status' => 'published',
            'sort_order' => 0,
            'is_enabled' => true,
            'show_in_breadcrumbs' => true,
            'show_in_nav' => false,
            'is_homepage_shell' => false,
            'published_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMigrationLog(string $module, string $sourceTable, int $sourceId, string $targetTable, int $targetId): void
    {
        DB::table('migration_logs')->insert([
            'module' => $module,
            'batch_name' => 'legacy-query-test',
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported.',
            'created_at' => now(),
        ]);
    }
}
