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

    public function test_resolves_audited_public_business_subsite_home(): void
    {
        $result = $this->resolver->resolve('/admin/index.php', 'lang=2');

        $this->assertNotNull($result);
        $this->assertSame(301, $result->statusCode);
        $this->assertSame('/en/facilities/business-administration', $result->destinationUrl);
    }

    public function test_resolves_audited_dental_clinic_subsite_home(): void
    {
        $result = $this->resolver->resolve('/dent_clinic/index.php', 'lang=1');

        $this->assertNotNull($result);
        $this->assertSame('/ar/campus-life/dental', $result->destinationUrl);
    }

    public function test_resolves_reviewed_root_navigation_categories_by_exact_id_and_service(): void
    {
        $cases = [
            [12, 1, 1, '/ar'],
            [1263, 2, 2, '/en/facilities/dentistry'],
            [28, 1, 2, '/en/about/accreditation'],
            [60, 2, 1, '/ar/e-services/suggestions-complaints'],
        ];

        foreach ($cases as [$sourceId, $service, $language, $target]) {
            $result = $this->resolver->resolve(
                '/index.php',
                "page=show&dir=items&service={$service}&cat_id={$sourceId}&lang={$language}",
            );

            $this->assertNotNull($result);
            $this->assertSame(301, $result->statusCode);
            $this->assertSame($target, $result->destinationUrl);
        }
    }

    public function test_root_navigation_category_mapping_rejects_wrong_service_and_unknown_id(): void
    {
        $this->assertNull($this->resolver->resolve('/index.php', 'page=show&dir=items&service=2&cat_id=12&lang=1'));
        $this->assertNull($this->resolver->resolve('/index.php', 'page=show&dir=items&service=1&cat_id=999999&lang=1'));
        $this->assertNull($this->resolver->resolve('/med/index.php', 'page=show&dir=items&service=1&cat_id=299&lang=1'));
    }

    public function test_resolves_exact_reviewed_functional_route_signatures(): void
    {
        $cases = [
            ['dir=html&ex=1&lang=1&page=contactus', '/ar/contact'],
            ['page=contactus&lang=2&ex=1&dir=html', '/en/contact'],
            ['dir=html&ex=1&lang=1&page=complaint', '/ar/e-services/suggestions-complaints'],
            ['service=49&page=list&lang=1&ex=2&dir=jobs', '/ar/campus-life/career-development/jobs'],
        ];

        foreach ($cases as [$query, $target]) {
            $result = $this->resolver->resolve('/index.php', $query);
            $this->assertNotNull($result);
            $this->assertSame($target, $result->destinationUrl);
        }
    }

    public function test_functional_route_mapping_rejects_near_miss_queries(): void
    {
        $this->assertNull($this->resolver->resolve('/index.php', 'dir=html&lang=1&page=contactus'));
        $this->assertNull($this->resolver->resolve('/index.php', 'act=1&dir=html&ex=1&lang=1&page=contactus'));
        $this->assertNull($this->resolver->resolve('/index.php', 'dir=jobs&ex=2&lang=1&page=list&service=48'));
        $this->assertNull($this->resolver->resolve('/med/index.php', 'dir=html&ex=1&lang=1&page=contactus'));
    }

    public function test_does_not_redirect_imported_static_snippet_as_standalone_page(): void
    {
        $pageId = $this->createPublishedPage('legacy-community-service');
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 12, 'pages', $pageId);

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&item_id=12&lang=2');

        $this->assertNull($result);
    }

    public function test_static_page_resolver_does_not_guess_from_generic_cat_id(): void
    {
        $pageId = $this->createPublishedPage('legacy-static-page');
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 14, 'pages', $pageId);

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&cat_id=14&lang=1');

        $this->assertNull($result);
    }

    public function test_retired_legacy_languages_temporarily_fall_back_to_english_homepage(): void
    {
        foreach ([3, 6, 7] as $languageId) {
            $result = $this->resolver->resolve(
                '/index.php',
                'page=show&dir=items&service=3&cat_id=5362&lang='.$languageId,
            );

            $this->assertNotNull($result);
            $this->assertSame(302, $result->statusCode);
            $this->assertSame('/en', $result->destinationUrl);
        }
    }

    public function test_supported_members_routes_remain_private_and_unresolved(): void
    {
        foreach ([1, 2] as $languageId) {
            $result = $this->resolver->resolve(
                '/members/index.php',
                'page=show&dir=items&service=1&cat_id=10&lang='.$languageId,
            );

            $this->assertNull($result);
        }
    }

    public function test_unrecognized_language_id_does_not_receive_homepage_fallback(): void
    {
        $result = $this->resolver->resolve('/index.php', 'lang=99');

        $this->assertNull($result);
    }

    public function test_disabled_draft_legacy_news_does_not_receive_public_redirect(): void
    {
        DB::table('news_articles')->insert([
            'slug' => 'disabled-legacy-news', 'status' => 'draft', 'published_at' => null, 'scheduled_at' => null,
            'is_enabled' => false, 'is_featured' => false, 'sort_order' => 0,
            'legacy_source_table' => 'jx_categories', 'legacy_source_id' => 9001, 'legacy_service_type' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->resolver->resolve('/index.php', 'page=show&dir=items&service=3&cat_id=9001&lang=1');

        $this->assertNull($result);
    }

    private function createLegacyNewsArticle(int $legacySourceId, int $serviceType, string $slug): int
    {
        $articleId = (int) DB::table('news_articles')->insertGetId([
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
        DB::table('news_article_translations')->insert([
            ['news_article_id' => $articleId, 'locale' => 'ar', 'title' => 'خبر', 'excerpt' => null, 'body' => null, 'created_at' => now(), 'updated_at' => now()],
            ['news_article_id' => $articleId, 'locale' => 'en', 'title' => 'News', 'excerpt' => null, 'body' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $articleId;
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
