<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Models\Form\DynamicFormSubmission;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleEvent;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsCalendarEventMoveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $pastId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
        $this->publishEventsPage();
    }

    public function test_dry_run_changes_nothing_and_write_moves_real_events_once(): void
    {
        $this->registerForLegacyEvent();

        $this->artisan('news:move-calendar-events', ['--user' => (string) $this->admin->getKey()])
            ->expectsOutputToContain('Would move:')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);
        $this->assertSame(0, NewsArticleEvent::query()->count());

        $this->artisan('news:move-calendar-events', ['--user' => $this->admin->email, '--write' => true])
            ->expectsOutputToContain('Moved:')
            ->expectsOutputToContain('Sample events left out')
            ->assertExitCode(0);

        // Only the two events editors rewrote are moved; untouched samples stay out.
        $this->assertSame(2, NewsArticleEvent::query()->count());
        $event = NewsArticleEvent::query()->with('sessions')->where('legacy_event_id', 'evt-001')->firstOrFail();
        $article = NewsArticle::query()->with('translations')->findOrFail($event->news_article_id);

        $this->assertSame('published', $article->status);
        $this->assertSame('Health Data Symposium', $article->translations->firstWhere('locale', 'en')?->title);
        $this->assertSame('ندوة البيانات الصحية', $article->translations->firstWhere('locale', 'ar')?->title);
        $this->assertTrue($event->show_on_calendar);
        $this->assertTrue($event->requires_registration);
        $this->assertSame('conference-registration', $event->form_key);
        $this->assertSame(10, $event->capacity);
        $this->assertSame(2, $event->prior_registrations);
        $this->assertSame(['2030-11-02', '2030-11-03'], $event->sessions->map(fn ($session): string => $session->date->format('Y-m-d'))->all());
        $this->assertSame('09:00', substr((string) $event->sessions->first()?->starts_at, 0, 5));
        $this->assertSame('17:00', substr((string) $event->sessions->first()?->ends_at, 0, 5));

        $past = NewsArticleEvent::query()->where('legacy_event_id', $this->pastId)->firstOrFail();
        $this->assertFalse($past->requires_registration);

        // The old registration now counts against the article's seats.
        $submission = DynamicFormSubmission::query()->firstOrFail();
        $this->assertSame('news-articles', $submission->payload_json['_context']['source']);
        $this->assertSame((string) $article->getKey(), $submission->payload_json['_context']['event_id']);
        $this->assertSame('evt-001', $submission->payload_json['_context']['legacy_event_id']);
        $this->get('/en/news/'.$article->getKey())->assertOk()->assertSee('7 seats left');

        $this->artisan('news:move-calendar-events', ['--user' => (string) $this->admin->getKey(), '--write' => true])
            ->expectsOutputToContain('Already moved')
            ->assertExitCode(0);
        $this->assertSame(2, NewsArticleEvent::query()->count());
    }

    public function test_moved_entries_leave_the_old_list_and_their_addresses_redirect(): void
    {
        $this->artisan('news:move-calendar-events', ['--user' => (string) $this->admin->getKey(), '--write' => true])->assertExitCode(0);
        $articleId = (int) NewsArticleEvent::query()->where('legacy_event_id', 'evt-001')->value('news_article_id');
        $pastArticleId = (int) NewsArticleEvent::query()->where('legacy_event_id', $this->pastId)->value('news_article_id');

        $ids = app(NewsServiceInterface::class)->listNewsEvents('en')->pluck('id')->all();
        $this->assertContains('article-'.$articleId, $ids);
        $this->assertNotContains('evt-001', $ids);
        $this->assertNull(app(NewsServiceInterface::class)->findNewsEvent('evt-001', 'en'));

        $this->get('/en/news/events-list/evt-001')->assertRedirect('/en/news/'.$articleId)->assertStatus(301);
        $this->get('/ar/news/events-list/register?event=evt-001')->assertRedirect('/ar/news/'.$articleId.'/register')->assertStatus(301);
        $this->get('/en/news/events-list/past?event='.$this->pastId)->assertRedirect('/en/news/'.$pastArticleId)->assertStatus(301);

        // The old registration path no longer takes this event.
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Late Applicant',
            'email' => 'late@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'news-events',
            'event_id' => 'evt-001',
        ])->assertUnprocessable()->assertJsonValidationErrors('event_id');
    }

    public function test_the_command_needs_a_known_user(): void
    {
        $this->artisan('news:move-calendar-events')->assertExitCode(2);
        $this->artisan('news:move-calendar-events', ['--user' => 'nobody@example.com'])->assertExitCode(2);
    }

    /** Publishes the bundled page list with two entries rewritten by an editor. */
    private function publishEventsPage(): void
    {
        $payload = app(NewsServiceInterface::class)->getEditablePayload('news.events');
        $this->pastId = (string) $payload['translations']['en']['past'][0]['id'];

        foreach (['ar', 'en'] as $locale) {
            foreach ($payload['translations'][$locale]['upcoming'] as &$event) {
                if ($event['id'] === 'evt-001') {
                    $event['title'] = $locale === 'ar' ? 'ندوة البيانات الصحية' : 'Health Data Symposium';
                    $event['startsAt'] = '2030-11-02T09:00:00+03:00';
                    $event['endsAt'] = '2030-11-03T17:00:00+03:00';
                    $event['formId'] = 'conference-registration';
                    $event['capacity'] = 10;
                    $event['registered'] = 2;
                    $event['speakers'] = [['name' => $locale === 'ar' ? 'هالة سعيد' : 'Hala Said', 'title' => 'Keynote']];
                }
            }
            unset($event);

            foreach ($payload['translations'][$locale]['past'] as &$event) {
                if ($event['id'] === $this->pastId) {
                    $event['title'] = $locale === 'ar' ? 'يوم الابتكار' : 'Innovation Day';
                }
            }
            unset($event);
        }

        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft('news.events', $payload, (int) $this->admin->getKey());
        $this->assertTrue($workflow->publish('news.events', (int) $this->admin->getKey()));
    }

    private function registerForLegacyEvent(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Early Applicant',
            'email' => 'early@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'news-events',
            'event_id' => 'evt-001',
        ])->assertCreated();
    }
}
