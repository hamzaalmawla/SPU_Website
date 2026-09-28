<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class SocietyEventsNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_migration_provisions_enabled_localized_category_without_articles(): void
    {
        $category = NewsCategory::query()->with('translations')->where('slug', 'society-events')->firstOrFail();

        $this->assertSame('news', $category->type);
        $this->assertTrue($category->is_enabled);
        $this->assertSame('فعاليات المجتمع', $category->translations->firstWhere('locale', 'ar')?->name);
        $this->assertSame("Society's Events", $category->translations->firstWhere('locale', 'en')?->name);
        $this->assertSame(0, $category->articles()->count());
    }

    public function test_society_events_route_precedes_article_show_and_filters_archive(): void
    {
        $this->createPublishedArticle('society-events', 'society-event-one', 'Society Event One');
        $this->createPublishedArticle('news', 'general-news-one', 'General News One');

        $this->get('/en/news/society-events')
            ->assertOk()
            ->assertSee("Society's Events")
            ->assertSee('Society Event One')
            ->assertDontSee('General News One');
    }

    public function test_society_events_is_an_admin_article_classification(): void
    {
        $category = NewsCategory::query()->where('slug', 'society-events')->firstOrFail();
        $options = app(NewsArticleCmsServiceInterface::class)->editorialTypeOptions();

        $this->assertSame('society_events', $options[(int) $category->getKey()] ?? null);
        $this->assertSame("Society's Events", __('admin.news_article.types.society_events', [], 'en'));
        $this->assertSame('فعاليات المجتمع', __('admin.news_article.types.society_events', [], 'ar'));
    }

    public function test_latest_society_event_cards_returns_only_the_newest_four(): void
    {
        $excluded = $this->createPublishedArticle('news', 'general-news', 'General News');
        $expectedTitles = [];

        for ($index = 1; $index <= 5; $index++) {
            $article = $this->createPublishedArticle('society-events', 'society-event-'.$index, 'Society Event '.$index);
            $article->forceFill(['published_at' => now()->subDays(5 - $index)])->save();
            if ($index > 1) {
                $expectedTitles[] = 'Society Event '.$index;
            }
        }

        $cards = app(NewsServiceInterface::class)->getLatestSocietyEventCards('en');

        $this->assertCount(4, $cards);
        $this->assertSame(array_reverse($expectedTitles), $cards->pluck('title')->all());
        $this->assertNotContains((int) $excluded->getKey(), $cards->pluck('id')->all());

        $generalHomepageCards = app(NewsServiceInterface::class)->getHomepageArticleCards('en', [], null, 4);
        $this->assertSame(['General News'], $generalHomepageCards->pluck('title')->all());
    }

    public function test_homepage_renders_society_events_as_a_four_column_non_carousel_section(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->createPublishedArticle('society-events', 'homepage-society-event', 'Homepage Society Event');

        $this->get('/en')
            ->assertOk()
            ->assertSee('id="home-society-events"', false)
            ->assertSee('Homepage Society Event')
            ->assertSee('xl:grid-cols-4', false)
            ->assertDontSee('id="home-society-events" class="carousel', false);
    }

    private function createPublishedArticle(string $categorySlug, string $slug, string $title): NewsArticle
    {
        $category = NewsCategory::query()->firstOrCreate(
            ['slug' => $categorySlug],
            ['type' => 'news', 'sort_order' => 10, 'is_enabled' => true],
        );
        if (! $category->translations()->where('locale', 'en')->exists()) {
            $category->translations()->createMany([
                ['locale' => 'ar', 'name' => $categorySlug],
                ['locale' => 'en', 'name' => $categorySlug],
            ]);
        }

        $article = NewsArticle::query()->create([
            'news_category_id' => $category->getKey(),
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'is_enabled' => true,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => $title, 'excerpt' => $title, 'body' => '<p>'.$title.'</p>'],
            ['locale' => 'en', 'title' => $title, 'excerpt' => $title, 'body' => '<p>'.$title.'</p>'],
        ]);

        return $article;
    }
}
