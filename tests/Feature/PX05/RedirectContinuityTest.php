<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyFileInventory;
use App\Models\Legacy\LegacyPatternRule;
use App\Models\Page\UnresolvedLegacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for redirect continuity middleware.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 17.5
 */
class RedirectContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_match_returns_301_redirect(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/old-about',
            'destination_url' => '/ar/about',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old-about');

        $response->assertRedirect('/ar/about');
        $response->assertStatus(301);
    }

    public function test_exact_match_with_unsafe_external_destination_is_blocked(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/unsafe-external',
            'destination_url' => 'https://evil.example/phishing',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/unsafe-external')->assertNotFound();
    }

    public function test_exact_match_with_unsafe_scheme_destination_is_blocked(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/unsafe-scheme',
            'destination_url' => 'javascript:alert(1)',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/unsafe-scheme')->assertNotFound();
    }

    public function test_pattern_match_returns_301_redirect(): void
    {
        LegacyPatternRule::create([
            'pattern' => '#^/faculty/(.+)$#',
            'replacement' => '/ar/faculties/$1',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $response = $this->get('/faculty/engineering');

        $response->assertRedirect('/ar/faculties/engineering');
        $response->assertStatus(301);
    }

    public function test_no_match_passes_through_to_normal_routing(): void
    {
        $response = $this->get('/nonexistent-legacy-path-xyz');

        // Should pass through middleware and hit normal routing (404)
        $response->assertNotFound();
    }

    public function test_unresolved_request_is_logged(): void
    {
        $this->get('/some-missing-page');

        $this->assertDatabaseHas('unresolved_legacy_requests', [
            'method' => 'GET',
            'request_type' => 'page',
        ]);
    }

    public function test_unresolved_legacy_index_request_logs_normalized_metadata(): void
    {
        $this->get('/index.php?page=show&ex=2&dir=items&lang=2&ser=4&cat_id=123');

        $record = UnresolvedLegacyRequest::query()->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertSame('root:items:show', $record->handler);
        $this->assertSame('unresolved', $record->outcome);
        $this->assertSame('root', $record->subsite);
        $this->assertSame(0, $record->old_site_id);
        $this->assertSame(2, $record->old_language_id);
        $this->assertSame('en', $record->old_language_symbol);
        $this->assertSame('4', $record->normalized_json['service'] ?? null);
    }

    public function test_old_news_query_redirects_to_numeric_public_article_url(): void
    {
        $articleId = $this->createLegacyNewsArticle(5362, 3, 'legacy-news-slug');

        $response = $this->get('/index.php?page=show&ex=2&dir=items&lang=1&ser=3&cat_id=5362');

        $response->assertStatus(301);
        $response->assertRedirect('/ar/news/'.$articleId);
    }

    public function test_old_news_query_resolution_wins_before_generic_pattern_rule(): void
    {
        $articleId = $this->createLegacyNewsArticle(7001, 4, 'legacy-announcement-slug');
        LegacyPatternRule::create([
            'pattern' => '#^/index\.php$#',
            'replacement' => '/ar/generic-legacy-index',
            'status_code' => 301,
            'priority' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/index.php?page=show&dir=items&lang=2&Ser=4&cat_id=7001');

        $response->assertStatus(301);
        $response->assertRedirect('/en/news/'.$articleId);
    }

    public function test_old_static_snippet_query_does_not_become_standalone_redirect(): void
    {
        $pageId = $this->createPublishedPage('legacy-community-service');
        $this->createMigrationLog('static_pages', 'jx_site_static_pages', 12, 'pages', $pageId);

        $response = $this->get('/index.php?page=show&dir=items&item_id=12&lang=2');

        $response->assertNotFound();
    }

    public function test_legacy_public_admin_index_resolves_to_the_faculty_but_admin_login_still_skips(): void
    {
        // service=73 is the Business faculty subsite (tens digit 7) publishing
        // faculty news (units digit 3), so it resolves to that faculty's page.
        // It must never be treated as the new Laravel /admin panel.
        $this->get('/admin/index.php?page=list&dir=items&service=73&lang=1')
            ->assertRedirect('/ar/faculties/business-administration')
            ->assertStatus(301);

        // A service that does not belong to the admin subsite's decade is a
        // mismatch and stays unresolved for triage.
        $this->get('/admin/index.php?page=list&dir=items&service=3&lang=1')
            ->assertNotFound();

        $this->assertDatabaseHas('unresolved_legacy_requests', [
            'subsite' => 'admin',
            'old_site_id' => 7,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/admin/login',
            'destination_url' => '/ar/admin-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/login');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_legacy_public_business_subsite_home_redirects_before_admin_routes(): void
    {
        $this->get('/admin/index.php?lang=1')
            ->assertRedirect('/ar/faculties/business-administration')
            ->assertStatus(301);
    }

    public function test_reviewed_root_category_queries_redirect_through_runtime_continuity(): void
    {
        $this->get('/index.php?page=show&ex=2&dir=items&lang=2&ser=2&cat_id=1263')
            ->assertRedirect('/en/faculties/dentistry')
            ->assertStatus(301);

        $this->get('/index.php?page=show&ex=2&dir=items&lang=1&ser=1&cat_id=28')
            ->assertRedirect('/ar/about/accreditation')
            ->assertStatus(301);

        $this->get('/index.php?page=show&ex=2&dir=items&lang=1&ser=2&cat_id=12')
            ->assertNotFound();
    }

    public function test_reviewed_functional_queries_redirect_through_runtime_continuity(): void
    {
        $this->get('/index.php?mylang=1&dir=html&ex=1&page=contactus')
            ->assertRedirect('/ar/contact')
            ->assertStatus(301);

        $this->get('/index.php?dir=jobs&ex=2&lang=1&page=list&service=49')
            ->assertRedirect('/ar/campus-life/career-development/jobs')
            ->assertStatus(301);

        $this->get('/index.php?dir=jobs&ex=2&lang=1&page=list&service=48')->assertNotFound();
    }

    public function test_repeated_unresolved_request_increments_hit_count(): void
    {
        $this->get('/same-missing-page');
        $this->get('/same-missing-page');

        $record = UnresolvedLegacyRequest::query()
            ->where('method', 'GET')
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(1, UnresolvedLegacyRequest::query()->count());
        $this->assertSame(2, $record->hit_count);
    }

    public function test_admin_prefix_is_skipped_by_middleware(): void
    {
        // Admin routes should not be processed by redirect middleware
        LegacyExactRedirect::create([
            'legacy_path' => '/admin/login',
            'destination_url' => '/ar/admin-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/login');

        // Should NOT redirect — admin prefix is skipped
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_livewire_prefix_is_skipped_by_middleware(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/livewire/something',
            'destination_url' => '/ar/livewire-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/livewire/something');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_filament_prefix_is_skipped_by_middleware(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/filament/resource',
            'destination_url' => '/ar/filament-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/filament/resource');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_redirect_loops_terminate_within_max_hops(): void
    {
        // Create a loop: /a -> /b -> /c -> /a
        LegacyExactRedirect::create([
            'legacy_path' => '/loop-a',
            'destination_url' => '/loop-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/loop-b',
            'destination_url' => '/loop-c',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/loop-c',
            'destination_url' => '/loop-a',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/loop-a');

        // Should terminate (not hang) and return a redirect to the last valid destination
        $this->assertContains($response->getStatusCode(), [301, 302]);
    }

    public function test_retired_legacy_languages_redirect_to_english_homepage_before_exact_rules(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/index.php',
            'query_signature' => 'lang=3',
            'destination_url' => '/ar',
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);

        foreach ([3, 6, 7] as $languageId) {
            $this->get('/index.php?lang='.$languageId)
                ->assertStatus(302)
                ->assertRedirect('/en');
        }
    }

    public function test_private_members_archive_blocks_exact_pattern_and_file_redirects(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/members/index.php',
            'query_signature' => 'lang=1',
            'destination_url' => '/ar',
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);
        LegacyPatternRule::create([
            'pattern' => '#^/members/#',
            'replacement' => '/ar',
            'status_code' => 301,
            'priority' => 1,
            'is_active' => true,
        ]);
        LegacyFileInventory::create([
            'legacy_path' => '/members/private-cv.pdf',
            'current_path' => '/media/private-cv.pdf',
            'status' => 'mapped',
        ]);

        $this->get('/members/index.php?lang=1')->assertNotFound();
        $this->get('/members/unknown?lang=2')->assertNotFound();
        $this->get('/members/private-cv.pdf')->assertNotFound();

        $this->assertDatabaseHas('unresolved_legacy_requests', [
            'request_type' => 'file',
            'outcome' => 'unresolved',
        ]);
    }

    public function test_unrecognized_language_id_cannot_use_exact_or_pattern_homepage_fallback(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/index.php',
            'query_signature' => 'lang=99',
            'destination_url' => '/en',
            'status_code' => 302,
            'locale' => 'en',
            'is_active' => true,
        ]);
        LegacyPatternRule::create([
            'pattern' => '#^/index\.php$#',
            'replacement' => '/en',
            'status_code' => 302,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->get('/index.php?lang=99')->assertNotFound();
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
