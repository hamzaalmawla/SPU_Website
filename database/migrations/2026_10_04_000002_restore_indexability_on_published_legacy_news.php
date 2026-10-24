<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Restores indexability on the published legacy news archive.
 *
 * LegacyNewsImportService writes robots='noindex,nofollow' on every SEO row it
 * creates, and publishing never touches that column. So 2,145 articles have been
 * live and rendering at 200 while sitemap-news.xml stayed empty and no crawler
 * was allowed to look at any of them.
 *
 * Deliberately narrow. A row is only cleared when its article is published AND
 * enabled AND has a complete Arabic translation - the same bar
 * LegacyNewsPublicationService::isCompleteTranslation() applies - so the 194
 * records it refused for incomplete_ar_content keep their noindex and stay out
 * of the sitemap. An article nobody can read should not be offered to Google.
 *
 * Only rows that actually say noindex are touched, so anything an editor set by
 * hand to something else is left alone, and re-running changes nothing.
 *
 * On 404s: the sitemap emits a URL per locale only where a translation exists,
 * and uses the article id rather than a slug, so clearing this cannot introduce
 * a dead entry. Arabic-only articles simply never get an /en URL listed.
 */
return new class extends Migration
{
    /**
     * Set rather than cleared.
     *
     * robots is NOT NULL on news_article_seo_meta and the column's own default is
     * 'index,follow', so this restores the value the schema always intended
     * rather than inventing one. Nulling it was not even possible, and would
     * have been worse if it were: an empty column means "nobody said", which
     * looks identical to a decision nobody made.
     *
     * It also makes down() exact: it reverses only the rows carrying this
     * value, so a row an editor edits afterwards is not dragged back.
     */
    private const INDEXABLE = 'index,follow';

    public function up(): void
    {
        $affected = DB::table('news_article_seo_meta')
            ->whereIn('news_article_id', $this->publishableArticleIds())
            ->whereNotNull('robots')
            ->whereRaw('LOWER(robots) LIKE ?', ['%noindex%'])
            ->update(['robots' => self::INDEXABLE, 'updated_at' => now()]);

        if (app()->runningInConsole()) {
            echo "  Restored index,follow on {$affected} legacy news SEO row(s).".PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::table('news_article_seo_meta')
            ->whereIn('news_article_id', $this->publishableArticleIds())
            ->where('robots', self::INDEXABLE)
            ->update(['robots' => 'noindex,nofollow', 'updated_at' => now()]);
    }

    /**
     * Published, enabled, legacy-imported articles whose Arabic is readable.
     *
     * @return Builder
     */
    private function publishableArticleIds()
    {
        return DB::table('news_articles')
            ->select('news_articles.id')
            ->join('news_article_translations as t', function ($join): void {
                $join->on('t.news_article_id', '=', 'news_articles.id')->where('t.locale', '=', 'ar');
            })
            ->where('news_articles.status', 'published')
            ->where('news_articles.is_enabled', true)
            ->whereNotNull('news_articles.legacy_source_table')
            ->whereNotNull('t.title')
            ->whereRaw("TRIM(t.title) <> ''")
            ->whereNotNull('t.body')
            ->whereRaw("TRIM(t.body) <> ''");
    }
};
