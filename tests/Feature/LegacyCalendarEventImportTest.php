<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\News\LegacyCalendarEventImportServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Filament\Pages\ManageEvents;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_imported_events_pass_the_admin_publish_check(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])->assertSuccessful();
        $this->actingAs($user, 'web');

        $component = Livewire::test(ManageEvents::class);
        $component->instance()->form->getState();

        $fair = app(NewsServiceInterface::class)->findNewsEvent('legacy-cal-5385', 'ar');
        $this->assertSame('نشاطات الجامعة', $fair?->categoryLabel);
        $this->assertSame('خلال ساعات المعرض', $fair?->timeLabel);
    }

    public function test_a_re_run_fills_in_what_an_earlier_import_left_empty(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])->assertSuccessful();

        // What the first version of the import saved: no category, time or location.
        $payload = $workflow->getPublishedPayload('news.events');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['categories'] = [];
            foreach ($payload['translations'][$locale]['past'] as &$event) {
                $event['categoryId'] = $event['categoryLabel'] = $event['timeLabel'] = '';
                $event['location'] = $event['id'] === 'legacy-cal-5260' ? '' : $event['location'];
            }
            unset($event);
        }
        $payload['translations']['ar']['past'][0]['timeLabel'] = 'كتبه محرر';
        $workflow->saveDraft('news.events', $payload, (int) $user->getKey());

        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])
            ->expectsOutputToContain('Missing fields filled in: legacy-cal-5765, legacy-cal-5385, legacy-cal-5281, legacy-cal-5260')
            ->assertSuccessful();

        $draft = $workflow->latestEditableDraftPayload('news.events', (int) $user->getKey());
        $past = collect($draft['translations']['ar']['past'])->keyBy('id');
        $this->assertSame('كتبه محرر', $past['legacy-cal-5765']['timeLabel']);
        $this->assertSame('طوال اليوم', $past['legacy-cal-5281']['timeLabel']);
        $this->assertSame('خارج الجامعة', $past['legacy-cal-5260']['location']);
        $this->assertSame('conferences', $past['legacy-cal-5260']['categoryId']);
        $this->assertContains('conferences', array_column($draft['translations']['en']['categories'], 'id'));

        $this->actingAs($user, 'web');
        Livewire::test(ManageEvents::class)->instance()->form->getState();
    }

    public function test_untouched_sample_events_are_removed_and_incomplete_ones_reported(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $this->actingAs($user, 'web');

        // The never-published Events page opens on the sample content; saving makes it the draft.
        Livewire::test(ManageEvents::class)->call('save');
        $draft = $workflow->latestEditableDraftPayload('news.events', (int) $user->getKey());
        foreach (['ar', 'en'] as $locale) {
            $draft['translations'][$locale]['upcoming'][1]['title'] = 'ورشة معدلة '.$locale;
            $draft['translations'][$locale]['upcoming'][] = array_merge($draft['translations'][$locale]['upcoming'][0], ['id' => 'event-mine', 'title' => 'ورشة الذكاء الاصطناعي', 'image' => '']);
        }
        $workflow->saveDraft('news.events', $draft, (int) $user->getKey());

        $result = app(LegacyCalendarEventImportServiceInterface::class)->import(
            json_decode((string) file_get_contents(database_path('seeders/Data/legacy-calendar-events.json')), true),
            (string) $user->getKey(),
            true,
        );

        $this->assertSame(['evt-001', 'evt-003', 'evt-004', 'evt-005', 'evt-past-001', 'evt-past-002', 'evt-past-003'], $result->removedSamples);
        $this->assertSame(['upcoming event-mine "ورشة الذكاء الاصطناعي" (ar): image', 'upcoming event-mine "ورشة الذكاء الاصطناعي" (en): image'], $result->incomplete);

        $draft = $workflow->latestEditableDraftPayload('news.events', (int) $user->getKey());
        $this->assertSame(['evt-002', 'event-mine'], array_column($draft['translations']['en']['upcoming'], 'id'));
        $this->assertSame(['legacy-cal-5765', 'legacy-cal-5385', 'legacy-cal-5281', 'legacy-cal-5260'], array_column($draft['translations']['ar']['past'], 'id'));
    }

    public function test_the_events_page_publishes_from_the_admin(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->actingAs($user, 'web');

        // The v2 situation: the sample page saved as a draft, then the import on top.
        Livewire::test(ManageEvents::class)->call('save');
        $this->artisan('news:import-legacy-calendar-events', ['--user' => (string) $user->getKey(), '--write' => true])->assertSuccessful();

        Livewire::test(ManageEvents::class)
            ->call('publish')
            ->assertNotified(__('admin.editorial_workspace.notifications.published'));

        $published = app(CmsWorkflowServiceInterface::class)->getPublishedPayload('news.events');
        $this->assertIsArray($published);
        $this->assertSame(['legacy-cal-5765', 'legacy-cal-5385', 'legacy-cal-5281', 'legacy-cal-5260'], array_column($published['translations']['ar']['past'], 'id'));
        $this->assertSame([], $published['translations']['en']['upcoming']);
        $this->assertNotNull(app(NewsServiceInterface::class)->findNewsEvent('legacy-cal-5385', 'en', true));
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
