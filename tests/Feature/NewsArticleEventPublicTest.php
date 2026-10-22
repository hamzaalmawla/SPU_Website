<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Mail\EventRegistrationReceived;
use App\Models\Faculty\Faculty;
use App\Models\Form\DynamicFormSubmission;
use App\Models\News\NewsCategory;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class NewsArticleEventPublicTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }

    public function test_event_page_shows_sessions_speakers_seats_and_registration(): void
    {
        $member = $this->facultyMember();
        $articleId = $this->publishEvent('Digital Health Conference', 'events', [
            'kind' => 'conference',
            'requires_registration' => true,
            'show_on_calendar' => true,
            'formId' => 'activity-registration',
            'capacity' => 3,
            'prior_registrations' => 1,
            'language' => 'both',
            'audience' => ['students', 'public'],
            'location_ar' => 'المدرج الرئيسي',
            'location_en' => 'Main auditorium',
            'sessions' => [
                ['date' => '2030-01-17', 'starts_at' => '10:00', 'ends_at' => '12:00', 'title_en' => 'Workshops', 'title_ar' => 'ورشات', 'speakers' => [
                    ['source' => 'external', 'name_ar' => 'سامر خليل', 'name_en' => 'Samer Khalil', 'organization_en' => 'Damascus University'],
                ]],
                ['date' => '2030-01-10', 'starts_at' => '09:00', 'ends_at' => '11:30', 'title_en' => 'Opening', 'title_ar' => 'الافتتاح'],
            ],
            'speakers' => [['source' => 'university', 'profile' => 'faculty_member:'.$member->getKey()]],
        ]);

        $page = $this->get('/en/news/'.$articleId)->assertOk();
        $page->assertSee('Event details')
            ->assertSee('Conference')
            ->assertSeeInOrder(['Opening', 'Workshops'])
            ->assertSee('Main auditorium')
            ->assertSee('Students, Public')
            ->assertSee('Rania Haddad')
            ->assertSee('/en/about/profile/rania-haddad', false)
            ->assertSee('Samer Khalil')
            ->assertSee('/en/news/'.$articleId.'/register', false)
            ->assertSee('2 seats left')
            ->assertSee('/en/news/'.$articleId.'/calendar.ics', false)
            ->assertSee('"@type":"Event"', false)
            ->assertSee('"startDate":"2030-01-10T09:00:00+03:00"', false);

        $this->get('/ar/news/'.$articleId)->assertOk()
            ->assertSee('سجّل الآن')
            ->assertSee('رانيا حداد')
            ->assertSee('المدرج الرئيسي');
    }

    public function test_registration_counts_seats_and_closes_when_full(): void
    {
        Mail::fake();
        $articleId = $this->publishEvent('AI Workshop', 'events', [
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'capacity' => 2,
            'prior_registrations' => 1,
            'sessions' => [['date' => '2030-03-01', 'starts_at' => '10:00']],
        ]);

        $this->get('/en/news/'.$articleId.'/register')->assertOk()
            ->assertSee('data-event-source="news-articles"', false)
            ->assertSee('data-event-id="'.$articleId.'"', false)
            ->assertSee('1 seat left');

        $this->postJson('/en/forms/activity-registration/submissions', [
            'fullName' => 'Lina Aziz',
            'email' => 'lina@example.com',
            'event_source' => 'news-articles',
            'event_id' => (string) $articleId,
        ])->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();
        $this->assertSame('news-articles', $submission->payload_json['_context']['source'] ?? null);
        $this->assertSame((string) $articleId, $submission->payload_json['_context']['event_id'] ?? null);
        $this->assertSame('AI Workshop', $submission->payload_json['_context']['event_title'] ?? null);
        Mail::assertQueued(EventRegistrationReceived::class, fn (EventRegistrationReceived $mail): bool => $mail->hasTo('lina@example.com'));

        $this->postJson('/en/forms/activity-registration/submissions', [
            'fullName' => 'Omar Fares',
            'email' => 'omar@example.com',
            'event_source' => 'news-articles',
            'event_id' => (string) $articleId,
        ])->assertUnprocessable()->assertJsonValidationErrors('event_id');

        $this->get('/en/news/'.$articleId.'/register')->assertOk()
            ->assertSee('All seats are taken.')
            ->assertDontSee('data-event-source', false);
    }

    public function test_registration_rejects_another_form_and_a_second_registration(): void
    {
        $articleId = $this->publishEvent('Career Day', 'events', [
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'sessions' => [['date' => '2030-04-01', 'starts_at' => '09:00']],
        ]);
        $registration = [
            'fullName' => 'Lina Aziz',
            'email' => 'lina@example.com',
            'event_source' => 'news-articles',
            'event_id' => (string) $articleId,
        ];

        $this->postJson('/en/forms/conference-registration/submissions', $registration + ['affiliation' => 'SPU', 'role' => 'attendee'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');
        $this->postJson('/en/forms/activity-registration/submissions', $registration)->assertCreated();
        $this->postJson('/en/forms/activity-registration/submissions', $registration)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_society_event_shows_its_dates_without_registration(): void
    {
        $articleId = $this->publishEvent('Blood Donation Day', 'society-events', [
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'show_on_calendar' => true,
            'location_en' => 'University plaza',
            'location_ar' => 'ساحة الجامعة',
            'sessions' => [['date' => '2030-05-02', 'starts_at' => '08:30', 'ends_at' => '13:00']],
        ]);

        $this->get('/en/news/'.$articleId)->assertOk()
            ->assertSee("Society's Event")
            ->assertSee('University plaza')
            ->assertSee('/en/news/'.$articleId.'/calendar.ics', false)
            ->assertDontSee('/en/news/'.$articleId.'/register', false);
        $this->get('/en/news/'.$articleId.'/register')->assertNotFound();
    }

    public function test_past_event_shows_results_and_takes_no_registrations(): void
    {
        $articleId = $this->publishEvent('Spring Symposium', 'events', [
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'results_en' => 'Forty papers were presented.',
            'results_ar' => 'قُدّم أربعون بحثاً.',
            'sessions' => [['date' => '2024-03-01', 'starts_at' => '10:00']],
        ]);

        $this->get('/en/news/'.$articleId)->assertOk()
            ->assertSee('This event has ended')
            ->assertSee('Registration is closed.')
            ->assertSee('Forty papers were presented.')
            ->assertDontSee('/en/news/'.$articleId.'/register', false)
            ->assertDontSee('/en/news/'.$articleId.'/calendar.ics', false);
        $this->get('/en/news/'.$articleId.'/calendar.ics')->assertNotFound();
        $this->postJson('/en/forms/activity-registration/submissions', [
            'fullName' => 'Late Visitor',
            'email' => 'late@example.com',
            'event_source' => 'news-articles',
            'event_id' => (string) $articleId,
        ])->assertUnprocessable()->assertJsonValidationErrors('event_id');
    }

    public function test_calendar_file_has_one_entry_per_session_in_utc(): void
    {
        $articleId = $this->publishEvent('Three Day Course', 'events', [
            'location_en' => 'Lab 3',
            'sessions' => [
                ['date' => '2030-10-24', 'starts_at' => '09:00', 'ends_at' => '11:00'],
                ['date' => '2030-10-31', 'starts_at' => '09:00', 'ends_at' => '11:00', 'title_en' => 'Part two, practice'],
                ['date' => '2030-11-02', 'starts_at' => '10:00'],
            ],
        ]);

        $response = $this->get('/en/news/'.$articleId.'/calendar.ics')->assertOk();
        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $ics = (string) $response->getContent();

        $this->assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString("DTSTART:20301024T060000Z\r\nDTEND:20301024T080000Z", $ics);
        $this->assertStringContainsString('SUMMARY:Three Day Course — Part two\\, practice', $ics);
        $this->assertStringContainsString('LOCATION:Lab 3', $ics);
        $this->assertStringContainsString('UID:news-article-'.$articleId.'-3@', $ics);
    }

    public function test_draft_event_stays_private_and_preview_offers_no_registration(): void
    {
        $prepared = $this->prepare('Private Seminar', 'events', [
            'kind' => 'seminar',
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'sessions' => [['date' => '2030-06-01', 'starts_at' => '10:00']],
        ]);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());

        $this->get('/en/news/'.$prepared->articleId)->assertNotFound();
        $this->get('/en/news/'.$prepared->articleId.'/register')->assertNotFound();
        $this->get('/en/news/'.$prepared->articleId.'/calendar.ics')->assertNotFound();

        $preview = $workflow->preview((string) $prepared->targetKey, 'en', (int) $this->editor->getKey());
        $this->get($preview->previewUrl)->assertOk()
            ->assertSee('Seminar')
            ->assertSee('Registration is not available in preview.')
            ->assertDontSee('/en/news/'.$prepared->articleId.'/register', false);
    }

    /** @param array<string, mixed> $event */
    private function publishEvent(string $title, string $category, array $event): int
    {
        $prepared = $this->prepare($title, $category, $event);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish((string) $prepared->targetKey, (int) $this->editor->getKey()));

        return (int) $prepared->articleId;
    }

    /** @param array<string, mixed> $event */
    private function prepare(string $title, string $category, array $event): NewsArticleCmsDataDTO
    {
        return app(NewsArticleCmsServiceInterface::class)->prepareDraft(new NewsArticleCmsDataDTO(null, [
            'news_category_id' => (int) NewsCategory::query()->where('slug', $category)->value('id'),
            'is_enabled' => true,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'ar', 'title' => 'فعالية: '.$title, 'excerpt' => 'ملخص', 'body' => '<p>محتوى عربي</p>'],
                ['locale' => 'en', 'title' => $title, 'excerpt' => 'Summary', 'body' => '<p>English body</p>'],
            ],
            'attachments' => [],
            'seoMeta' => [],
            'event' => $event,
        ]), (int) $this->editor->getKey());
    }

    private function facultyMember(): FacultyMember
    {
        $faculty = Faculty::query()->create(['slug' => 'medicine', 'is_enabled' => true]);
        $faculty->translations()->createMany([
            ['locale' => 'ar', 'name' => 'الطب'],
            ['locale' => 'en', 'name' => 'Medicine'],
        ]);
        $member = FacultyMember::query()->create([
            'slug' => 'rania-haddad',
            'faculty_id' => $faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->createMany([
            ['locale' => 'ar', 'full_name' => 'رانيا حداد', 'title' => 'أستاذة'],
            ['locale' => 'en', 'full_name' => 'Rania Haddad', 'title' => 'Professor'],
        ]);

        return $member;
    }
}
