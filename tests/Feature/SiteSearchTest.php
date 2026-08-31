<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Search\SearchIndexServiceInterface;
use App\Contracts\Search\SiteSearchServiceInterface;
use App\DTOs\Search\SearchResultDTO;
use App\Http\Controllers\Public\SearchController;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleTranslation;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Search\SearchDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Public site-wide search.
 *
 * The seeder runs under WithoutModelEvents, so nothing it creates reaches the
 * search index. Everything these tests create afterwards is written normally
 * and is therefore indexed by the observers, which keeps every assertion here
 * about records this test put there on purpose.
 */
final class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    // ── Matching ─────────────────────────────────────────────────────────────

    public function test_arabic_query_returns_a_published_news_article(): void
    {
        $this->publishedArticle(
            slug: 'medical-conference-2026',
            titleAr: 'مؤتمر كلية الطب البشري السنوي',
            titleEn: 'Annual Faculty of Human Medicine Conference',
            bodyAr: 'تنظم الجامعة السورية الخاصة مؤتمرها السنوي في دمشق.',
        );

        $this->get('/ar/search?q='.rawurlencode('مؤتمر كلية الطب'))
            ->assertOk()
            ->assertSee('مؤتمر كلية الطب البشري السنوي', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_english_query_returns_a_published_news_article(): void
    {
        $this->publishedArticle(
            slug: 'medical-conference-2026',
            titleAr: 'مؤتمر كلية الطب البشري السنوي',
            titleEn: 'Annual Faculty of Human Medicine Conference',
        );

        $this->get('/en/search?q=Medicine+Conference')
            ->assertOk()
            ->assertSee('Annual Faculty of Human Medicine Conference', false)
            ->assertSee('dir="ltr"', false);
    }

    public function test_search_matches_body_text_not_only_titles(): void
    {
        $this->publishedArticle(
            slug: 'scholarship-news',
            titleAr: 'خبر جامعي',
            titleEn: 'University update',
            bodyAr: 'أعلنت الجامعة عن منحة دراسية جديدة لطلاب الهندسة.',
        );

        $this->get('/ar/search?q='.rawurlencode('منحة'))
            ->assertOk()
            ->assertSee('خبر جامعي', false);
    }

    public function test_search_covers_research_pages_and_people(): void
    {
        $this->publishedPublication('Machine Learning in Cardiology', 'التعلم الآلي في طب القلب');
        $this->publishedPage('quality-assurance', 'Quality assurance office', 'مكتب ضمان الجودة');
        $this->publishedPerson('rana-haddad', 'Dr. Rana Haddad', 'الدكتورة رنا حداد');

        $this->get('/en/search?q=Cardiology')->assertOk()->assertSee('Machine Learning in Cardiology', false);
        $this->get('/en/search?q=Quality')->assertOk()->assertSee('Quality assurance office', false);
        $this->get('/en/search?q=Haddad')->assertOk()->assertSee('Dr. Rana Haddad', false);
    }

    public function test_multi_word_queries_require_every_term_to_match(): void
    {
        $this->publishedArticle(slug: 'both-terms', titleEn: 'Dentistry research grant', titleAr: 'منحة أبحاث طب الأسنان');
        $this->publishedArticle(slug: 'one-term', titleEn: 'Dentistry open day', titleAr: 'يوم مفتوح لطب الأسنان');

        $this->get('/en/search?q=Dentistry+grant')
            ->assertOk()
            ->assertSee('Dentistry research grant', false)
            ->assertDontSee('Dentistry open day', false);
    }

    // ── Arabic normalization ─────────────────────────────────────────────────

    public function test_undiacritized_query_matches_diacritized_content(): void
    {
        $this->publishedArticle(
            slug: 'vocalized-title',
            titleAr: 'الجَامِعَةُ السُّورِيَّةُ الخَاصَّةُ',
            titleEn: 'Syrian Private University',
        );

        $this->get('/ar/search?q='.rawurlencode('الجامعة السورية'))
            ->assertOk()
            ->assertSee('الجَامِعَةُ السُّورِيَّةُ الخَاصَّةُ', false);
    }

    public function test_hamza_and_ta_marbuta_variants_match_either_spelling(): void
    {
        $this->publishedArticle(slug: 'hamza-title', titleAr: 'إدارة كلية الصيدلة', titleEn: 'Pharmacy administration');

        // Typed without the hamza, and with ه instead of ة.
        $this->get('/ar/search?q='.rawurlencode('اداره'))
            ->assertOk()
            ->assertSee('إدارة كلية الصيدلة', false);

        // And the mirror case: content stored plainly, query carrying a hamza.
        $this->publishedArticle(slug: 'plain-title', titleAr: 'احمد في المستشفى', titleEn: 'Ahmad at the hospital');

        $this->get('/ar/search?q='.rawurlencode('أحمد'))
            ->assertOk()
            ->assertSee('احمد في المستشفى', false);
    }

    public function test_arabic_indic_digits_match_western_digits(): void
    {
        $this->publishedArticle(slug: 'class-2025', titleAr: 'حفل تخريج دفعة 2025', titleEn: 'Class of 2025 graduation');

        $this->get('/ar/search?q='.rawurlencode('دفعة ٢٠٢٥'))
            ->assertOk()
            ->assertSee('حفل تخريج دفعة 2025', false);
    }

    // ── Visibility ───────────────────────────────────────────────────────────

    public function test_drafts_disabled_and_soft_deleted_records_never_appear(): void
    {
        $draft = $this->publishedArticle(slug: 'draft-article', titleEn: 'Hidden draft bulletin', titleAr: 'نشرة مسودة');
        $draft->update(['status' => 'draft']);

        $disabled = $this->publishedArticle(slug: 'disabled-article', titleEn: 'Hidden disabled bulletin', titleAr: 'نشرة معطلة');
        $disabled->update(['is_enabled' => false]);

        $deleted = $this->publishedArticle(slug: 'deleted-article', titleEn: 'Hidden deleted bulletin', titleAr: 'نشرة محذوفة');
        $deleted->delete();

        $future = $this->publishedArticle(slug: 'future-article', titleEn: 'Hidden future bulletin', titleAr: 'نشرة مستقبلية');
        $future->update(['published_at' => now()->addMonth()]);

        $visible = $this->publishedArticle(slug: 'live-article', titleEn: 'Visible live bulletin', titleAr: 'نشرة منشورة');

        $response = $this->get('/en/search?q=bulletin')->assertOk();

        $response->assertSee('Visible live bulletin', false);
        $response->assertDontSee('Hidden draft bulletin', false);
        $response->assertDontSee('Hidden disabled bulletin', false);
        $response->assertDontSee('Hidden deleted bulletin', false);
        $response->assertDontSee('Hidden future bulletin', false);

        $this->assertSame(
            2,
            SearchDocument::query()->where('searchable_id', $visible->getKey())->where('searchable_type', NewsArticle::class)->count(),
            'A published article should hold one index document per locale',
        );
    }

    public function test_unpublishing_a_record_removes_it_from_search_immediately(): void
    {
        $article = $this->publishedArticle(slug: 'retracted', titleEn: 'Retractable announcement', titleAr: 'إعلان قابل للسحب');

        $this->get('/en/search?q=Retractable')->assertOk()->assertSee('Retractable announcement', false);

        $article->update(['status' => 'draft']);
        Cache::flush();

        $this->get('/en/search?q=Retractable')->assertOk()->assertDontSee('Retractable announcement', false);
        $this->assertSame(0, SearchDocument::query()->where('searchable_id', $article->getKey())->where('searchable_type', NewsArticle::class)->count());
    }

    public function test_placeholder_research_titles_are_never_indexed(): void
    {
        $this->publishedPublication('Legacy research publication 4211', 'منشور قديم');

        $this->assertSame(0, SearchDocument::query()->where('searchable_type', ResearchPublication::class)->count());
    }

    // ── Filtering, counts and pagination ─────────────────────────────────────

    public function test_type_filter_narrows_results_and_reports_counts(): void
    {
        $this->publishedArticle(slug: 'filter-news', titleEn: 'Damascus news roundup', titleAr: 'حصاد أخبار دمشق');
        $this->publishedPublication('Damascus water quality study', 'دراسة جودة مياه دمشق');

        $all = $this->get('/en/search?q=Damascus')->assertOk();
        $all->assertSee('Damascus news roundup', false);
        $all->assertSee('Damascus water quality study', false);

        $newsOnly = $this->get('/en/search?q=Damascus&type=news')->assertOk();
        $newsOnly->assertSee('Damascus news roundup', false);
        $newsOnly->assertDontSee('Damascus water quality study', false);

        $researchOnly = $this->get('/en/search?q=Damascus&type=research')->assertOk();
        $researchOnly->assertSee('Damascus water quality study', false);
        $researchOnly->assertDontSee('Damascus news roundup', false);
    }

    public function test_an_unknown_type_falls_back_to_all_rather_than_erroring(): void
    {
        $this->publishedArticle(slug: 'fallback-news', titleEn: 'Fallback bulletin', titleAr: 'نشرة احتياطية');

        $this->get('/en/search?q=Fallback&type=not-a-type')
            ->assertOk()
            ->assertSee('Fallback bulletin', false);
    }

    public function test_pagination_preserves_the_query_the_filter_and_the_locale(): void
    {
        for ($index = 1; $index <= 14; $index++) {
            $this->publishedArticle(
                slug: 'paged-article-'.$index,
                titleEn: 'Paged bulletin number '.$index,
                titleAr: 'نشرة مرقمة رقم '.$index,
            );
        }

        $firstPage = $this->get('/en/search?q=Paged+bulletin&type=news')->assertOk();
        $firstPage->assertSee('/en/search?q=Paged%20bulletin&amp;type=news&amp;page=2', false);

        $secondPage = $this->get('/en/search?q=Paged+bulletin&type=news&page=2')->assertOk();
        $secondPage->assertSee('Paged bulletin number', false);
        $secondPage->assertSee('dir="ltr"', false);

        // The language switch keeps the visitor's search rather than dropping it.
        $secondPage->assertSee('/ar/search?q=Paged%20bulletin&amp;type=news', false);
    }

    // ── Query hygiene ────────────────────────────────────────────────────────

    public function test_an_empty_query_renders_the_prompt_without_running_a_search(): void
    {
        $this->publishedArticle(slug: 'unsearched', titleEn: 'Never listed bulletin', titleAr: 'نشرة غير مدرجة');

        $this->get('/en/search')
            ->assertOk()
            ->assertSee('Search the university site', false)
            ->assertDontSee('Never listed bulletin', false);

        $this->get('/en/search?q=%20%20')
            ->assertOk()
            ->assertSee('Search the university site', false);
    }

    public function test_a_single_character_query_is_rejected_as_too_short(): void
    {
        $this->publishedArticle(slug: 'short-query', titleEn: 'A bulletin', titleAr: 'نشرة');

        $this->get('/en/search?q=a')
            ->assertOk()
            ->assertSee('Type at least 2 characters to search.', false)
            ->assertDontSee('A bulletin', false);
    }

    public function test_like_wildcards_in_the_query_are_escaped_and_cannot_broaden_results(): void
    {
        $this->publishedArticle(slug: 'wildcard-target', titleEn: 'Engineering symposium', titleAr: 'ندوة هندسية');

        // Unescaped, '%' would match everything and '_' would match any single
        // character, so both of these would otherwise return the article.
        $this->get('/en/search?q='.rawurlencode('%'))->assertOk()->assertDontSee('Engineering symposium', false);
        $this->get('/en/search?q='.rawurlencode('%%'))->assertOk()->assertDontSee('Engineering symposium', false);
        $this->get('/en/search?q='.rawurlencode('Engineering%symposium'))->assertOk()->assertDontSee('Engineering symposium', false);
        $this->get('/en/search?q='.rawurlencode('Engineerin_'))->assertOk()->assertDontSee('Engineering symposium', false);

        // The escape character itself must not leak into the pattern either.
        $this->get('/en/search?q='.rawurlencode('!%'))->assertOk()->assertDontSee('Engineering symposium', false);

        // The literal term still matches, proving the escaping did not break it.
        $this->get('/en/search?q=Engineering')->assertOk()->assertSee('Engineering symposium', false);
    }

    public function test_an_over_long_query_is_truncated_rather_than_rejected(): void
    {
        $this->publishedArticle(slug: 'long-query', titleEn: 'Robotics laboratory opening', titleAr: 'افتتاح مختبر الروبوتات');

        $query = 'Robotics'.str_repeat('x', 400);

        $this->get('/en/search?q='.rawurlencode($query))->assertOk();
    }

    public function test_the_query_is_echoed_back_escaped(): void
    {
        $this->get('/en/search?q='.rawurlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ── SEO, routing and rate limiting ───────────────────────────────────────

    public function test_the_results_page_is_noindex_follow(): void
    {
        $this->get('/en/search?q=anything')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get('/ar/search')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_the_search_page_is_absent_from_the_sitemap(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertIsString($sitemap);
        $this->assertStringNotContainsString('/en/search', $sitemap);
        $this->assertStringNotContainsString('/ar/search', $sitemap);
    }

    public function test_the_search_route_resolves_ahead_of_the_page_catch_all(): void
    {
        // /{locale}/{slugPath} would happily swallow /ar/search and 404 looking
        // for a CMS page, so route order is load-bearing here.
        foreach (['ar', 'en'] as $locale) {
            $route = Route::getRoutes()->match(
                Request::create('/'.$locale.'/search', 'GET')
            );

            $this->assertSame('public.search', $route->getName());
            $this->assertSame(SearchController::class, $route->getAction('controller'));
        }
    }

    public function test_the_search_route_is_rate_limited(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn (\Illuminate\Routing\Route $route): bool => $route->getName() === 'public.search'
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:public-search', $route->gatherMiddleware());
    }

    public function test_search_responses_bypass_the_public_page_cache(): void
    {
        // Caching whole rendered pages per arbitrary query would fill the page
        // cache with single-use entries, and could serve one visitor's results
        // to another if the key ever missed a parameter.
        $this->publishedArticle(slug: 'cache-bypass', titleEn: 'Cache bypass bulletin', titleAr: 'نشرة تجاوز الذاكرة');

        $this->get('/en/search?q=bypass')
            ->assertOk()
            ->assertHeader('X-Cache', 'BYPASS');
    }

    public function test_two_different_queries_do_not_share_a_response(): void
    {
        $this->publishedArticle(slug: 'alpha-news', titleEn: 'Alpha bulletin', titleAr: 'نشرة ألفا');
        $this->publishedArticle(slug: 'beta-news', titleEn: 'Beta bulletin', titleAr: 'نشرة بيتا');

        $this->get('/en/search?q=Alpha')->assertOk()->assertSee('Alpha bulletin', false)->assertDontSee('Beta bulletin', false);
        $this->get('/en/search?q=Beta')->assertOk()->assertSee('Beta bulletin', false)->assertDontSee('Alpha bulletin', false);
    }

    // ── Index maintenance ────────────────────────────────────────────────────

    public function test_the_backfill_command_indexes_content_created_without_events(): void
    {
        SearchDocument::query()->delete();

        NewsArticle::withoutEvents(function (): void {
            $article = NewsArticle::query()->create([
                'slug' => 'silent-import',
                'status' => 'published',
                'is_enabled' => true,
                'published_at' => now()->subDay(),
            ]);

            foreach (['ar' => 'خبر مستورد بصمت', 'en' => 'Silently imported bulletin'] as $locale => $title) {
                NewsArticleTranslation::query()->create([
                    'news_article_id' => $article->getKey(),
                    'locale' => $locale,
                    'title' => $title,
                ]);
            }
        });

        $this->get('/en/search?q=Silently')->assertOk()->assertDontSee('Silently imported bulletin', false);

        $this->artisan('search:index')->assertSuccessful();
        Cache::flush();

        $this->get('/en/search?q=Silently')->assertOk()->assertSee('Silently imported bulletin', false);
    }

    public function test_the_backfill_is_idempotent(): void
    {
        $this->publishedArticle(slug: 'idempotent', titleEn: 'Idempotent bulletin', titleAr: 'نشرة متكررة');

        $this->artisan('search:index')->assertSuccessful();
        $afterFirst = SearchDocument::query()->count();

        $this->artisan('search:index')->assertSuccessful();
        $this->assertSame($afterFirst, SearchDocument::query()->count());

        $this->artisan('search:index', ['--fresh' => true])->assertSuccessful();
        $this->assertSame($afterFirst, SearchDocument::query()->count());
    }

    public function test_the_backfill_rejects_an_unknown_source(): void
    {
        $this->artisan('search:index', ['--source' => 'nonsense'])->assertFailed();
    }

    public function test_the_service_returns_dtos_not_models(): void
    {
        $this->publishedArticle(
            slug: 'dto-check',
            titleEn: 'Contract bulletin',
            titleAr: 'نشرة تعاقدية',
            bodyEn: 'The registrar published a contract bulletin for suppliers.',
        );

        $results = app(SiteSearchServiceInterface::class)->search('en', 'Contract bulletin');
        $first = $results->items->first();

        $this->assertSame(1, $results->total);
        $this->assertInstanceOf(SearchResultDTO::class, $first);
        $this->assertSame('news', $first->type);
        $this->assertSame('Contract bulletin', $first->title);
        $this->assertStringStartsWith('/en/news/', $first->url);
    }

    public function test_a_body_match_produces_a_highlighted_snippet(): void
    {
        $this->publishedArticle(
            slug: 'snippet-source',
            titleEn: 'Registrar notice',
            titleAr: 'إشعار المسجل',
            bodyEn: 'Applications for the postgraduate scholarship close at the end of the month.',
        );

        $results = app(SiteSearchServiceInterface::class)->search('en', 'scholarship');
        $snippet = $results->items->first()?->snippet ?? [];

        $this->assertNotSame([], $snippet);

        $highlighted = array_values(array_filter($snippet, fn (array $segment): bool => $segment['highlighted']));
        $this->assertNotSame([], $highlighted, 'The matched term should be marked for highlighting');
        $this->assertSame('scholarship', $highlighted[0]['text']);
    }

    public function test_a_snippet_highlights_the_original_spelling_of_a_folded_match(): void
    {
        // The stored text carries diacritics the query does not. Highlighting has
        // to land on the original characters, not on the folded ones.
        $this->publishedArticle(
            slug: 'folded-snippet',
            titleEn: 'Vocalized body',
            titleAr: 'نص مشكول',
            bodyAr: 'تحدث الدكتور أَحْمَد عن نتائج البحث.',
        );

        $results = app(SiteSearchServiceInterface::class)->search('ar', 'احمد');
        $snippet = $results->items->first()?->snippet ?? [];

        $highlighted = array_values(array_filter($snippet, fn (array $segment): bool => $segment['highlighted']));

        $this->assertNotSame([], $highlighted);
        $this->assertSame('أَحْمَد', $highlighted[0]['text']);
    }

    public function test_every_search_source_is_covered_by_the_index_service(): void
    {
        // A new content domain must be added to SOURCES deliberately, not
        // silently forgotten; this pins the list the backfill walks.
        $this->assertSame(
            ['news', 'research', 'pages', 'faculty-members', 'persons', 'faculties', 'faculty-pages'],
            SearchIndexServiceInterface::SOURCES,
        );
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function publishedArticle(
        string $slug,
        string $titleEn = 'Bulletin',
        string $titleAr = 'نشرة',
        string $bodyAr = '',
        string $bodyEn = '',
    ): NewsArticle {
        $article = NewsArticle::query()->create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subDay(),
        ]);

        foreach ([['ar', $titleAr, $bodyAr], ['en', $titleEn, $bodyEn]] as [$locale, $title, $body]) {
            NewsArticleTranslation::query()->create([
                'news_article_id' => $article->getKey(),
                'locale' => $locale,
                'title' => $title,
                'body' => $body === '' ? null : '<p>'.$body.'</p>',
            ]);
        }

        return $article->refresh();
    }

    private function publishedPublication(string $titleEn, string $titleAr): ResearchPublication
    {
        $publication = ResearchPublication::query()->create([
            'is_enabled' => true,
            'published_at' => now()->subDay(),
            'publication_year' => 2026,
        ]);

        foreach ([['ar', $titleAr], ['en', $titleEn]] as [$locale, $title]) {
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => $publication->getKey(),
                'locale' => $locale,
                'title' => $title,
                'authors' => 'Dr. Sample Author',
                'abstract' => 'An abstract used by the search index.',
            ]);
        }

        return $publication->refresh();
    }

    private function publishedPage(string $slug, string $titleEn, string $titleAr): Page
    {
        $page = Page::query()->create([
            'type' => 'landing',
            'template' => 'default',
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subDay(),
        ]);

        foreach ([['ar', $titleAr], ['en', $titleEn]] as [$locale, $title]) {
            PageTranslation::query()->create([
                'page_id' => $page->getKey(),
                'locale' => $locale,
                'title' => $title,
                'excerpt' => $title,
            ]);
        }

        return $page->refresh();
    }

    private function publishedPerson(string $slug, string $nameEn, string $nameAr): Person
    {
        $person = Person::query()->create([
            'slug' => $slug,
            'is_enabled' => true,
            'publication_status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        foreach ([['ar', $nameAr], ['en', $nameEn]] as [$locale, $name]) {
            PersonTranslation::query()->create([
                'person_id' => $person->getKey(),
                'locale' => $locale,
                'name' => $name,
                'role' => 'Faculty member',
            ]);
        }

        return $person->refresh();
    }
}
