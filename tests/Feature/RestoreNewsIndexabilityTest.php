<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RestoreNewsIndexabilityTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $slug, string $status, bool $enabled, string $title, string $body): NewsArticle
    {
        $category = NewsCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['type' => 'news', 'is_enabled' => true, 'sort_order' => 1],
        );

        $article = NewsArticle::query()->create([
            'news_category_id' => $category->getKey(),
            'slug' => $slug,
            'status' => $status,
            'is_enabled' => $enabled,
            'legacy_source_table' => 'jx_items',
            'legacy_source_id' => random_int(10000, 99999),
        ]);

        NewsArticleTranslation::query()->create([
            'news_article_id' => $article->getKey(),
            'locale' => 'ar',
            'title' => $title,
            'body' => $body,
        ]);

        NewsArticleSeoMeta::query()->create([
            'news_article_id' => $article->getKey(),
            'locale' => 'ar',
            'robots' => 'noindex,nofollow',
        ]);

        return $article;
    }

    public function test_it_restores_indexability_only_for_readable_published_articles(): void
    {
        $good = $this->article('good', 'published', true, 'عنوان حقيقي', '<p>نص المقال</p>');
        $draft = $this->article('draft', 'draft', false, 'عنوان', '<p>نص</p>');
        $emptyBody = $this->article('empty-body', 'published', true, 'عنوان', '   ');
        $emptyTitle = $this->article('empty-title', 'published', true, '  ', '<p>نص</p>');

        $migration = require database_path('migrations/2026_10_04_000002_restore_indexability_on_published_legacy_news.php');
        $migration->up();

        $robots = fn (NewsArticle $a): ?string => NewsArticleSeoMeta::query()
            ->where('news_article_id', $a->getKey())->where('locale', 'ar')->value('robots');

        self::assertSame('index,follow', $robots($good), 'A readable published article must become indexable');
        self::assertSame('noindex,nofollow', $robots($draft), 'A draft must stay noindex');
        self::assertSame('noindex,nofollow', $robots($emptyBody), 'An article with no body must stay noindex');
        self::assertSame('noindex,nofollow', $robots($emptyTitle), 'An article with no title must stay noindex');

        $migration->down();
        self::assertSame('noindex,nofollow', $robots($good), 'down() must restore what up() cleared');
    }

    public function test_it_leaves_editor_set_robots_values_alone_and_is_idempotent(): void
    {
        $a = $this->article('custom', 'published', true, 'عنوان', '<p>نص</p>');
        NewsArticleSeoMeta::query()->where('news_article_id', $a->getKey())->update(['robots' => 'index,follow,max-snippet:-1']);

        $migration = require database_path('migrations/2026_10_04_000002_restore_indexability_on_published_legacy_news.php');
        $migration->up();
        $migration->up();

        self::assertSame(
            'index,follow,max-snippet:-1',
            NewsArticleSeoMeta::query()->where('news_article_id', $a->getKey())->value('robots'),
            'A robots value that does not say noindex must not be touched',
        );
    }
}
