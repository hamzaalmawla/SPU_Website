<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\DTOs\News\NewsEventDTO;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsEventsCalendarArticlesTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
    }

    public function test_calendar_shows_marked_articles_per_session_and_marked_conferences_per_day(): void
    {
        $seminar = $this->publishEvent('Data Ethics Seminar', 'events', [
            'kind' => 'seminar',
            'show_on_calendar' => true,
            'sessions' => [
                ['date' => '2030-03-03', 'starts_at' => '10:00', 'title_en' => 'Part one'],
                ['date' => '2030-03-10', 'starts_at' => '10:00', 'title_en' => 'Part two'],
            ],
        ]);
        $this->publishEvent('Tree Planting Day', 'society-events', [
            'show_on_calendar' => true,
            'sessions' => [['date' => '2030-03-05', 'starts_at' => '08:00']],
        ]);
        $this->publishEvent('Off Calendar Workshop', 'events', [
            'sessions' => [['date' => '2030-03-07', 'starts_at' => '09:00']],
        ]);
        $this->publishConference('Medical AI Conference', '2030-03-20', '2030-03-21');

        $calendar = app(NewsServiceInterface::class)->getNewsEventCalendar('en', '2030-03');
        $titles = $calendar['events']->map(fn (NewsEventDTO $event): string => $event->title)->all();

        $this->assertContains('Data Ethics Seminar — Part one', $titles);
        $this->assertContains('Data Ethics Seminar — Part two', $titles);
        $this->assertContains('Tree Planting Day', $titles);
        $this->assertSame(2, collect($titles)->filter(fn (string $title): bool => $title === 'Medical AI Conference')->count());
        $this->assertNotContains('Off Calendar Workshop', $titles);
        $this->assertSame(['seminar', 'society', 'research-conference'], array_values(array_diff(array_column($calendar['types'], 'id'), ['event'])));

        $day = collect($calendar['days'])->firstWhere('date', '2030-03-10');
        $this->assertSame('/en/news/'.$seminar, $day['events']->first()?->detailUrl);

        $this->get('/en/news/events?month=2030-03')->assertOk()
            ->assertSee('Data Ethics Seminar — Part two')
            ->assertSee('Tree Planting Day')
            ->assertSee('Medical AI Conference')
            ->assertSee('<option value="seminar"', false);

        $this->get('/en/news/events?month=2030-03&type=seminar')->assertOk()
            ->assertSee('Data Ethics Seminar — Part one')
            ->assertDontSee('Tree Planting Day')
            ->assertDontSee('Medical AI Conference')
            ->assertSee('type=seminar', false);
    }

    public function test_events_list_and_homepage_listing_include_events_articles(): void
    {
        $upcoming = $this->publishEvent('Robotics Conference', 'events', [
            'kind' => 'conference',
            'sessions' => [['date' => '2030-09-01', 'starts_at' => '09:00'], ['date' => '2030-09-02', 'starts_at' => '09:00']],
        ]);
        $past = $this->publishEvent('Old Seminar', 'events', [
            'kind' => 'seminar',
            'sessions' => [['date' => '2024-02-01', 'starts_at' => '09:00']],
        ]);
        $this->publishEvent('Clean Campus Day', 'society-events', [
            'sessions' => [['date' => '2030-09-03', 'starts_at' => '09:00']],
        ]);

        $news = app(NewsServiceInterface::class);
        $this->assertContains('article-'.$upcoming, $news->listNewsEvents('en')->pluck('id')->all());
        $this->assertContains('article-'.$past, $news->listNewsEvents('en', true)->pluck('id')->all());
        $this->assertNotContains('Clean Campus Day', $news->listNewsEvents('en')->pluck('title')->all());
        // Event articles register as their own source, never through the page list lookup.
        $this->assertNull($news->findNewsEvent('article-'.$upcoming, 'en'));

        $this->get('/en/news/events-list')->assertOk()
            ->assertSee('Robotics Conference')
            ->assertSee('/en/news/'.$upcoming, false)
            ->assertSee('Old Seminar')
            ->assertSee('value="conference"', false)
            ->assertDontSee('Clean Campus Day');

        $this->get('/en/news/events-list?category=seminar')->assertOk()
            ->assertDontSee('Robotics Conference');
    }

    /** @param array<string, mixed> $event */
    private function publishEvent(string $title, string $category, array $event): int
    {
        $prepared = app(NewsArticleCmsServiceInterface::class)->prepareDraft(new NewsArticleCmsDataDTO(null, [
            'news_category_id' => (int) NewsCategory::query()->where('slug', $category)->value('id'),
            'is_enabled' => true,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'ar', 'title' => 'فعالية '.$title, 'excerpt' => 'ملخص', 'body' => '<p>محتوى</p>'],
                ['locale' => 'en', 'title' => $title, 'excerpt' => 'Summary', 'body' => '<p>Body</p>'],
            ],
            'attachments' => [],
            'seoMeta' => [],
            'event' => $event,
        ]), (int) $this->editor->getKey());
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish((string) $prepared->targetKey, (int) $this->editor->getKey()));

        return (int) $prepared->articleId;
    }

    private function publishConference(string $title, string $startsOn, string $endsOn): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $conferences = app(ResearchPageServiceInterface::class)->getEditablePayload('research.conferences');

        foreach (['ar', 'en'] as $locale) {
            foreach ($conferences['translations'][$locale]['upcoming'] as $index => &$conference) {
                $conference['date'] = 'March 2030';
                if ($index === 0) {
                    $conference['title'] = $title;
                    $conference['showOnCalendar'] = true;
                    $conference['startsOn'] = $startsOn;
                    $conference['endsOn'] = $endsOn;
                }
            }
            unset($conference);

            foreach ($conferences['translations'][$locale]['past'] as &$conference) {
                $conference['hasProceedings'] = false;
                unset($conference['proceedingsUrl']);
            }
            unset($conference);
        }

        $workflow->saveDraft('research.conferences', $conferences, (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish('research.conferences', (int) $this->editor->getKey()));
    }
}
