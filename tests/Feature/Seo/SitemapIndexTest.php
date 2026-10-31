<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /sitemap.xml is a sitemap index over per-section child documents, and both it
 * and the children are pre-generated to disk so the web server answers them
 * without entering PHP.
 *
 * The dynamic routes covered here are the fallback for a host that has not run
 * the generator yet.
 */
final class SitemapIndexTest extends TestCase
{
    use RefreshDatabase;

    private function seedPublishedPage(string $slug): Page
    {
        $page = Page::create([
            'slug' => $slug,
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        foreach (['ar', 'en'] as $locale) {
            PageTranslation::create([
                'page_id' => $page->id,
                'locale' => $locale,
                'title' => $slug.' '.$locale,
            ]);
        }

        return $page;
    }

    public function test_the_entry_point_is_a_valid_sitemap_index_listing_every_child(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml, 'The sitemap index must be valid XML.');
        $this->assertSame('sitemapindex', $xml->getName());

        $listed = [];
        foreach ($xml->sitemap as $child) {
            $listed[] = (string) $child->loc;
        }

        $this->assertCount(count(SitemapServiceInterface::SECTIONS), $listed);

        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $this->assertContains(
                rtrim((string) config('edge.canonical_url'), '/').'/sitemaps/sitemap-'.$section.'.xml',
                $listed,
                "The index must reference the {$section} child sitemap.",
            );
        }
    }

    public function test_the_index_uses_the_canonical_origin(): void
    {
        $canonical = rtrim((string) config('edge.canonical_url'), '/');

        $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        preg_match_all('#<loc>([^<]+)</loc>#', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $loc) {
            $this->assertStringStartsWith($canonical.'/', $loc);
        }
    }

    /**
     * The index is the document every crawler hits first. Generating it must not
     * touch the database at all: /sitemap.xml sits outside the public page cache
     * and the account runs five PHP-FPM workers.
     */
    public function test_rendering_the_index_touches_no_tables_at_all(): void
    {
        $this->seedPublishedPage('indexed-page');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $xml = app(SitemapServiceInterface::class)->renderIndexXml();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertStringContainsString('<sitemapindex', $xml);
        $this->assertSame(
            [],
            array_map(static fn (array $query): string => (string) $query['query'], $queries),
            'Building the sitemap index must not query the database.',
        );
    }

    /**
     * The old single-document sitemap read every page, article and publication
     * on every request, which is how it reached a 10.1 second response. The
     * index must cost the same whether the corpus holds one page or a hundred.
     */
    public function test_the_index_response_cost_does_not_grow_with_the_corpus(): void
    {
        $this->seedPublishedPage('baseline-page');
        $baseline = $this->queryCountForIndex();

        for ($i = 0; $i < 40; $i++) {
            $this->seedPublishedPage('bulk-page-'.$i);
        }

        $this->assertSame(
            $baseline,
            $this->queryCountForIndex(),
            'The sitemap index must not read the corpus it indexes.',
        );
    }

    private function queryCountForIndex(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/sitemap.xml')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_every_child_sitemap_is_a_valid_urlset(): void
    {
        $this->seedPublishedPage('child-valid-page');

        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $response = $this->get('/sitemaps/sitemap-'.$section.'.xml');

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/xml');

            $xml = simplexml_load_string((string) $response->getContent());
            $this->assertNotFalse($xml, "The {$section} sitemap must be valid XML.");
            $this->assertSame('urlset', $xml->getName());
        }
    }

    public function test_an_unknown_child_sitemap_is_a_404(): void
    {
        $this->get('/sitemaps/sitemap-nonsense.xml')->assertNotFound();
        $this->get('/sitemaps/sitemap-pages-1.xml')->assertNotFound();
    }

    public function test_published_pages_appear_in_the_pages_child_and_drafts_never_do(): void
    {
        $this->seedPublishedPage('live-page');

        Page::create([
            'slug' => 'draft-page',
            'status' => 'draft',
            'is_enabled' => true,
            'template' => 'default',
            'type' => 'landing',
        ])->translations()->create(['locale' => 'ar', 'title' => 'draft']);

        Page::create([
            'slug' => 'disabled-page',
            'status' => 'published',
            'is_enabled' => false,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ])->translations()->create(['locale' => 'ar', 'title' => 'disabled']);

        Page::create([
            'slug' => 'scheduled-page',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subDay(),
            'publish_at' => now()->addDay(),
            'template' => 'default',
            'type' => 'landing',
        ])->translations()->create(['locale' => 'ar', 'title' => 'scheduled']);

        $pages = (string) $this->get('/sitemaps/sitemap-pages.xml')->assertOk()->getContent();

        $this->assertStringContainsString('live-page', $pages);
        $this->assertStringContainsString('hreflang="ar"', $pages);
        $this->assertStringContainsString('hreflang="en"', $pages);
        $this->assertStringContainsString('hreflang="x-default"', $pages);

        // No child sitemap may leak an ineligible URL.
        foreach (SitemapServiceInterface::SECTIONS as $section) {
            $content = (string) $this->get('/sitemaps/sitemap-'.$section.'.xml')->assertOk()->getContent();

            foreach (['draft-page', 'disabled-page', 'scheduled-page', '/admin', '/preview'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $content,
                    "The {$section} sitemap must not contain {$forbidden}.",
                );
            }
        }
    }

    public function test_the_union_of_the_children_matches_the_full_entry_set(): void
    {
        $this->seedPublishedPage('union-page');

        $service = app(SitemapServiceInterface::class);

        $all = $service->generateEntries()->pluck('loc')->sort()->values()->all();

        $fromSections = collect(SitemapServiceInterface::SECTIONS)
            ->flatMap(fn (string $section): array => $service->generateSectionEntries($section)->pluck('loc')->all())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $all,
            $fromSections,
            'Splitting the sitemap must not add or drop a single URL.',
        );
    }
}
