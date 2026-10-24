<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Models\Legacy\LegacyExactRedirect;
use Database\Seeders\LegacyEntryPointRedirectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Findings from the 2026-10-11 log-driven continuity audit: every URL the old
 * site answered in Sep–Oct 2026 and the full old sitemap, replayed against the
 * deployed code and database. Each test pins one finding.
 */
final class LegacySeoContinuityAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FR/ES/DE copies of a record are three fifths of the old sitemap. They
     * must land on the same record in English, permanently — not on the
     * English homepage, which search engines read as a soft 404.
     */
    public function test_a_retired_language_record_redirects_permanently_to_its_english_equivalent(): void
    {
        $articleId = $this->publishedLegacyArticle(sourceId: 5362, service: 3, locales: ['ar', 'en']);

        foreach ([3, 6, 7] as $languageId) {
            $this->get('/index.php?page=show&ex=2&dir=items&service=3&cat_id=5362&lang='.$languageId)
                ->assertStatus(301)
                ->assertRedirect('/en/news/'.$articleId);
        }
    }

    /** The English twin is resolved by the full chain, so exact rules apply too. */
    public function test_a_retired_language_url_uses_the_exact_rule_of_its_english_twin(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/index.php',
            'query_signature' => 'dir=html&ex=1&lang=2&page=reviewed',
            'destination_url' => '/en/about',
            'status_code' => 301,
            'locale' => 'en',
            'is_active' => true,
        ]);

        $this->get('/index.php?lang=6&page=reviewed&ex=1&dir=html')
            ->assertStatus(301)
            ->assertRedirect('/en/about');
    }

    /** The retired homepage is the English homepage, so that move is permanent too. */
    public function test_a_retired_language_homepage_redirects_permanently_to_the_english_homepage(): void
    {
        foreach ([3, 6, 7] as $languageId) {
            $this->get('/index.php?lang='.$languageId)
                ->assertStatus(301)
                ->assertRedirect('/en');
        }
    }

    /**
     * Where the English twin has nowhere to go, the approved temporary fallback
     * is unchanged — it never becomes a 404 and never borrows an Arabic rule.
     */
    public function test_a_retired_language_url_without_an_english_equivalent_keeps_the_approved_fallback(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/index.php',
            'query_signature' => 'cat_id=999999&dir=items&lang=1&page=show&service=4',
            'destination_url' => '/ar/news',
            'status_code' => 301,
            'locale' => 'ar',
            'is_active' => true,
        ]);

        $this->get('/index.php?page=show&dir=items&service=4&cat_id=999999&lang=3')
            ->assertStatus(302)
            ->assertRedirect('/en');
    }

    /** The private members boundary holds for the English twin as well. */
    public function test_a_retired_language_members_url_never_resolves_to_a_private_record(): void
    {
        $response = $this->get('/members/index.php?page=show&ex=2&dir=councils&service=131&cat_id=10&lang=7');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('/about/profile/', $location);
    }

    /**
     * Legacy academic-warnings links went through an intermediate route that
     * itself redirects, costing a second hop. They now land in one.
     */
    public function test_the_academic_warnings_category_lands_in_one_hop(): void
    {
        foreach (['1' => 'ar', '2' => 'en'] as $languageId => $locale) {
            $this->get('/index.php?page=show&ex=2&dir=items&lang='.$languageId.'&service=1&cat_id=1237')
                ->assertStatus(301)
                ->assertRedirect('/'.$locale.'/admissions/documents?tab=academic-warnings');
        }
    }

    /**
     * Apache served a directory's index.php for a query on the directory, so
     * /?cat_id=… and /med/?lang=… were the same pages as their index.php
     * spelling. They must resolve the same way.
     */
    public function test_a_legacy_query_on_a_directory_resolves_like_its_index_php(): void
    {
        $articleId = $this->publishedLegacyArticle(sourceId: 5879, service: 4, locales: ['ar']);

        $this->get('/?cat_id=5879&dir=items&ex=2&lang=1&page=show&ser=4')
            ->assertStatus(301)
            ->assertRedirect('/ar/news/'.$articleId);

        $this->get('/med/?lang=2')
            ->assertStatus(301)
            ->assertRedirect('/en/faculties/medicine');
    }

    /**
     * Where the index.php spelling names nothing precise, the directory keeps
     * the subsite-home destination it already had - the rewrite must never
     * turn a resolving URL into a 404.
     */
    public function test_a_directory_query_without_a_precise_match_keeps_its_subsite_home(): void
    {
        $this->seed(LegacyEntryPointRedirectSeeder::class);

        $this->get('/info/?cat_id=5231&dir=items&ex=2&lang=1&page=show&ser=4')
            ->assertStatus(301)
            ->assertRedirect('/ar/faculties/artificial-intelligence');

        $this->get('/dent_clinic?77=&lang=3&page=show&ser=101')
            ->assertStatus(301)
            ->assertRedirect('/ar/campus-life/dental');
    }

    /** A directory with tracking parameters alone is not a legacy router URL. */
    public function test_a_directory_with_only_tracking_parameters_keeps_normal_handling(): void
    {
        $response = $this->get('/?utm_source=newsletter');

        $this->assertNotSame(301, $response->getStatusCode());
    }

    /** "&amp;" printed into old hrefs reached crawlers literally. */
    public function test_html_escaped_query_keys_resolve_like_their_decoded_spelling(): void
    {
        $articleId = $this->publishedLegacyArticle(sourceId: 4360, service: 4, locales: ['en']);

        $this->get('/index.php?page=show&amp;ex=2&amp;dir=items&amp;lang=2&amp;ser=4&amp;cat_id=4360')
            ->assertStatus(301)
            ->assertRedirect('/en/news/'.$articleId);
    }

    /**
     * The dental clinic and the hospital listed their doctors under
     * dir=councils / councils7. Like faculty staff, they land on the section
     * that now presents them - never on an individual record.
     */
    public function test_section_subsite_staff_pages_land_on_their_section(): void
    {
        $cases = [
            '/dent_clinic/index.php?page=show&ex=2&dir=councils7&service=6&cat_id=488&lang=1' => '/ar/campus-life/dental',
            '/dent_clinic/index.php?page=list&ex=2&dir=councils&service=6&lang=2' => '/en/campus-life/dental',
            '/hospital/index.php?First=0&cat_id=84&dir=councils&ex=2&lang=2&page=show&service=4' => '/en/campus-life/hospital',
        ];

        foreach ($cases as $legacy => $destination) {
            $this->get($legacy)->assertStatus(301)->assertRedirect($destination);
        }
    }

    /**
     * The old homepage linked the news and announcements lists with act=0, and
     * crawlers hold thousands of paging variants of them - after the homepage,
     * the most requested legacy URLs in the Sep–Oct 2026 logs.
     */
    public function test_legacy_list_pages_resolve_with_their_paging_state(): void
    {
        $cases = [
            '/index.php?page=list&ex=2&dir=items&lang=1&service=4&act=0' => '/ar/news/articles?type=announcement',
            '/index.php?CurrentPage=7&Last=1342&act=0&dir=items&ex=2&lang=2&page=list&service=4' => '/en/news/articles?type=announcement',
            '/index.php?page=list&ex=2&dir=items&lang=1&service=3&act=0' => '/ar/news',
            '/index.php?First=6&Last=1113&act=12&dir=items&ex=2&lang=2&page=list&service=3' => '/en/news',
        ];

        foreach ($cases as $legacy => $destination) {
            $this->get($legacy)->assertStatus(301)->assertRedirect($destination);
        }
    }

    /** A parameter that changes what the list is still keeps it unresolved. */
    public function test_a_legacy_list_with_an_unreviewed_parameter_stays_unresolved(): void
    {
        $this->get('/index.php?page=list&ex=2&dir=items&lang=1&service=4&cat_id=99')->assertNotFound();
    }

    /** The old site search carries its keywords to the new search. */
    public function test_the_legacy_site_search_resolves_to_search_with_its_keywords(): void
    {
        $this->get('/index.php?Keywords=%D9%83%D9%84%D9%8A%D8%A9&StartSearch=1&dir=search&ex=2&lang=1&page=search')
            ->assertStatus(301)
            ->assertRedirect('/ar/search?q=%D9%83%D9%84%D9%8A%D8%A9');

        $this->get('/index.php?Keywords=&StartSearch=1&dir=search&ex=2&lang=2&page=search')
            ->assertStatus(301)
            ->assertRedirect('/en/search');
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function publishedLegacyArticle(int $sourceId, int $service, array $locales): int
    {
        $articleId = (int) DB::table('news_articles')->insertGetId([
            'slug' => 'legacy-'.$sourceId,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => $sourceId,
            'legacy_service_type' => $service,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($locales as $locale) {
            DB::table('news_article_translations')->insert([
                'news_article_id' => $articleId,
                'locale' => $locale,
                'title' => 'Legacy article '.$locale,
                'excerpt' => null,
                'body' => '<p>Body</p>',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $articleId;
    }
}
