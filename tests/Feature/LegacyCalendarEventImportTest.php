<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyCalendarEventImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_old_calendar_events_are_added_as_past_events_once(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey()])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertNull(app(NewsServiceInterface::class)->findNewsEvent('legacy-cal-5385', 'ar'));

        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])
            ->expectsOutputToContain('Saved and published.')
            ->assertSuccessful();

        $news = app(NewsServiceInterface::class);
        $past = $news->listNewsEvents('ar', true);
        // The sample page events are not published along with the import.
        $this->assertSame(['legacy-cal-5765', 'legacy-cal-5385', 'legacy-cal-5281', 'legacy-cal-5260'], $past->pluck('id')->all());
        $this->assertSame([], $news->listNewsEvents('ar')->all());

        $fair = $news->findNewsEvent('legacy-cal-5385', 'en');
        $this->assertNotNull($fair);
        $this->assertStringContainsString('Damascus International Book Fair', $fair->title);
        $this->assertSame('/downloads/files/1769416189_3708547162.jpg', $fair->imageUrl);
        $this->assertFalse($fair->isRegisterable);

        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])
            ->expectsOutputToContain('Nothing to do.')
            ->assertSuccessful();
        $this->assertCount(4, $news->listNewsEvents('ar', true));
    }

    public function test_the_homepage_calendar_shows_the_events_page(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])->assertSuccessful();

        $section = collect(app(HomepageSectionServiceInterface::class)->getPublicHomepage('ar')->sections)->firstWhere('key', 'events_activities');

        $this->assertNotNull($section);
        $this->assertSame(
            ['/ar/news/events-list/legacy-cal-5765', '/ar/news/events-list/legacy-cal-5385', '/ar/news/events-list/legacy-cal-5281', '/ar/news/events-list/legacy-cal-5260'],
            array_map(static fn ($event): ?string => $event->url, $section->payload->events),
        );
        $this->assertSame('2026-02-16', $section->payload->events[1]->endsAt);

        $this->get('/ar')->assertOk()->assertSee('legacy-cal-5385', false)->assertDontSee('open-campus-seminar', false);
    }
}
