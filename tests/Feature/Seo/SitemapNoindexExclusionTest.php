<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A sitemap is an invitation to crawl and index. On 2 September, 3,416 of the
 * 4,560 URLs it advertised rendered `noindex,nofollow` — every legacy news
 * article, which LegacyNewsImportService marks that way on import pending
 * editorial review. Three quarters of the sitemap was asking crawlers to fetch
 * pages it then told them to throw away, and on this host each of those fetches
 * is a full render on a five-worker pool.
 */
final class SitemapNoindexExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_noindex_article_is_not_advertised_for_crawling(): void
    {
        $article = $this->article(['ar' => 'noindex,nofollow', 'en' => 'noindex,nofollow']);

        $this->assertNotContains(
            "/ar/news/{$article->getKey()}",
            $this->sitemapPaths(),
            'A page that tells crawlers not to index it has no business in the sitemap.',
        );
    }

    /**
     * The rule must not quietly hide content that is ready. An editor reviewing
     * an article and setting it to index,follow is the intended path into the
     * sitemap, and it must need no second switch.
     */
    public function test_an_article_cleared_for_indexing_is_advertised(): void
    {
        $article = $this->article(['ar' => 'index,follow', 'en' => 'index,follow']);

        $this->assertContains("/ar/news/{$article->getKey()}", $this->sitemapPaths());
    }

    /**
     * robots is stored per locale, so the two can disagree — an article
     * reviewed in Arabic but not yet in English. Excluding the whole article
     * would hide finished work; including it would advertise unfinished work.
     */
    public function test_the_decision_is_made_per_locale(): void
    {
        $article = $this->article(['ar' => 'index,follow', 'en' => 'noindex,nofollow']);

        $paths = $this->sitemapPaths();

        $this->assertContains("/ar/news/{$article->getKey()}", $paths);
        $this->assertNotContains("/en/news/{$article->getKey()}", $paths);
    }

    /** An article with no SEO row at all is indexable, per the column default. */
    public function test_an_article_without_seo_metadata_is_still_advertised(): void
    {
        $article = $this->article([]);

        $this->assertContains("/ar/news/{$article->getKey()}", $this->sitemapPaths());
    }

    /**
     * @param  array<string, string>  $robotsByLocale
     */
    private function article(array $robotsByLocale): NewsArticle
    {
        $article = NewsArticle::query()->create([
            'slug' => 'sitemap-robots-probe',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now()->subDay(),
        ]);

        foreach (['ar', 'en'] as $locale) {
            NewsArticleTranslation::query()->create([
                'news_article_id' => $article->getKey(),
                'locale' => $locale,
                'title' => 'Probe '.$locale,
                'excerpt' => 'Probe',
                'body' => '<p>Probe</p>',
            ]);

            if (isset($robotsByLocale[$locale])) {
                NewsArticleSeoMeta::query()->create([
                    'news_article_id' => $article->getKey(),
                    'locale' => $locale,
                    'robots' => $robotsByLocale[$locale],
                ]);
            }
        }

        return $article->refresh();
    }

    /** @return list<string> */
    private function sitemapPaths(): array
    {
        return app(SitemapServiceInterface::class)
            ->generateEntries()
            ->map(fn ($entry): string => (string) parse_url($entry->loc, PHP_URL_PATH))
            ->values()
            ->all();
    }
}
