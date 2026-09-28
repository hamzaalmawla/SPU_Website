<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Achievement\AchievementCardDTO;
use App\DTOs\Achievement\AchievementCategoryDTO;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\Services\Homepage\HomepageContentSelectionService;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

final class HomepageContentSelectionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_news_selection_replaces_snapshots_with_live_cards_in_selected_order(): void
    {
        $news = Mockery::mock(NewsServiceInterface::class);
        $research = Mockery::mock(ResearchPageServiceInterface::class);
        $achievements = Mockery::mock(AchievementServiceInterface::class);
        $news->shouldReceive('getHomepageArticleCards')
            ->once()
            ->with('en', [9, 3], null, 2)
            ->andReturn(new Collection([
                new ArticleCardDTO(9, 'en', 'Selected first', 'first', null, '/first.jpg', '2026-08-01', '/en/news/9', 'News'),
                new ArticleCardDTO(3, 'en', 'Selected second', 'second', null, '/second.jpg', '2026-07-01', '/en/news/3', 'News'),
            ]));
        $news->shouldReceive('getLatestSocietyEventCards')
            ->once()
            ->with('en', 4)
            ->andReturn(new Collection([
                new ArticleCardDTO(12, 'en', 'Society event', 'society-event', 'Event excerpt', '/event.jpg', '2026-09-01', '/en/news/12', "Society's Events"),
            ]));
        $service = new HomepageContentSelectionService($news, $research, $achievements);
        $payload = new HomepageSectionDataDTO(
            title: 'News',
            articles: [new ArticleCardDTO(1, 'en', 'Old mock', 'mock', null, null, null, '/en/news', 'Mock')],
            content: ['selectionMode' => 'manual', 'selectedArticleIds' => [9, 3]],
        );

        $hydrated = $service->hydratePayload($payload, 'university_news', 'en');

        self::assertSame(['Selected first', 'Selected second'], array_map(fn (ArticleCardDTO $card): string => $card->title, $hydrated->articles));
        self::assertSame(['/en/news/9', '/en/news/3'], array_map(fn (ArticleCardDTO $card): ?string => $card->url, $hydrated->articles));
        self::assertSame('Society event', $hydrated->content['societyEventArticles'][0]['title']);
    }

    public function test_manual_research_selection_uses_canonical_publication_cards(): void
    {
        $news = Mockery::mock(NewsServiceInterface::class);
        $research = Mockery::mock(ResearchPageServiceInterface::class);
        $achievements = Mockery::mock(AchievementServiceInterface::class);
        $research->shouldReceive('getHomepagePublicationCards')
            ->once()
            ->with('ar', ['publication-b', 'publication-a'], null, 2)
            ->andReturn(new Collection([
                new ResearchCardDTO(2, 'ar', 'البحث الثاني', 'publication-b', null, null, '2026', '/ar/research/publications/publication-b', 'بحث'),
                new ResearchCardDTO(1, 'ar', 'البحث الأول', 'publication-a', null, null, '2025', '/ar/research/publications/publication-a', 'بحث'),
            ]));
        $service = new HomepageContentSelectionService($news, $research, $achievements);
        $payload = new HomepageSectionDataDTO(
            title: 'الأبحاث',
            content: ['selectionMode' => 'manual', 'selectedResearchSlugs' => ['publication-b', 'publication-a']],
        );

        $hydrated = $service->hydratePayload($payload, 'research_studies', 'ar');

        self::assertSame(['publication-b', 'publication-a'], array_map(fn (ResearchCardDTO $card): string => $card->slug, $hydrated->researchItems));
        self::assertSame('/ar/research/publications/publication-b', $hydrated->researchItems[0]->url);
    }

    public function test_achievement_section_replaces_json_cards_with_live_domain_records(): void
    {
        $news = Mockery::mock(NewsServiceInterface::class);
        $research = Mockery::mock(ResearchPageServiceInterface::class);
        $achievements = Mockery::mock(AchievementServiceInterface::class);
        $category = new AchievementCategoryDTO(1, 'academic', 'Academic');
        $achievements->shouldReceive('homepage')->once()->with('en', 3)->andReturn(collect([
            new AchievementCardDTO(7, 'Live achievement', 'Academic', 'Live summary', '/image.jpg', '2026', ['label' => 'Details', 'url' => '/en/details'], [$category], '2026-01-01T00:00:00+00:00'),
        ]));
        $service = new HomepageContentSelectionService($news, $research, $achievements);
        $payload = new HomepageSectionDataDTO(
            title: 'Honor',
            items: [['title' => 'Stale JSON card']],
        );

        $hydrated = $service->hydratePayload($payload, 'achievements_highlights', 'en');

        self::assertSame('Live achievement', $hydrated->items[0]['title']);
        self::assertSame('Academic', $hydrated->items[0]['typeTag']);
        self::assertSame('Live achievement', $hydrated->featuredItems[0]->title);
    }
}
