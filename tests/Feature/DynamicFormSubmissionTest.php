<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Enums\FormSubmissionInbox;
use App\Mail\EventRegistrationReceived;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DynamicFormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->publishRegistrationContexts();
    }

    public function test_conference_registration_submission_is_stored(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Jane Applicant',
            'email' => 'Jane@Example.COM',
            'phone' => '+963 000 000 000',
            'affiliation' => 'Syrian Private University',
            'role' => 'presenter',
            'dietary' => 'halal',
            'specialNeeds' => 'Projector access.',
        ])->assertCreated()
            ->assertJson(['message' => 'Form submitted successfully.']);

        $this->assertDatabaseHas('dynamic_form_submissions', [
            'form_id' => 'conference-registration',
            'locale' => 'en',
            'applicant_name' => 'Jane Applicant',
            'applicant_email' => 'jane@example.com',
            'status' => 'new',
        ]);

        $submission = DynamicFormSubmission::query()->firstOrFail();

        $this->assertSame('presenter', $submission->payload_json['role'] ?? null);
        $this->assertSame('jane@example.com', $submission->payload_json['email'] ?? null);
    }

    public function test_dynamic_form_submission_validates_required_fields(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Jane Applicant',
            'role' => 'invalid-role',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'affiliation', 'role']);

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_dynamic_form_honeypot_rejects_automated_submission(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Automated Applicant',
            'email' => 'bot@example.com',
            'affiliation' => 'Spam service',
            'role' => 'attendee',
            'website' => 'https://spam.example',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_job_application_submission_stores_cv_file_privately(): void
    {
        Storage::fake('local');

        $this->post('/en/forms/job-application/submissions', [
            'firstNameAr' => 'أحمد',
            'lastNameAr' => 'الخطيب',
            'email' => 'ahmad@example.com',
            'phone' => '+963 999 999 999',
            'gender' => 'male',
            'profession' => 'Lecturer',
            'birthDate' => '1990-01-01',
            'educationLevel' => 'phd',
            'highestUniversity' => 'SPU',
            'academicExperience' => '5',
            'englishLevel' => 'advanced',
            'personalSkills' => 'excellent',
            'targetFaculty' => 'ai',
            'generalSpecialization' => 'Computer Science',
            'preciseSpecialization' => 'Machine Learning',
            'academicRank' => 'assistant-professor',
            'contractType' => 'full-time',
            'cvFile' => UploadedFile::fake()->create('cv.pdf', 128, 'application/pdf'),
            'coverLetter' => 'I would like to apply.',
            'hasPriorCriminalRecord' => 'no',
            'canProvideReferences' => 'yes',
            'agreeToTerms' => '1',
            'job_id' => 'job-001',
            'job_slug' => 'lecturer-computer-science',
        ])->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();

        $this->assertSame('job-application', $submission->form_id);
        $this->assertSame('أحمد الخطيب', $submission->applicant_name);
        $this->assertArrayHasKey('cvFile', $submission->files_json ?? []);
        $this->assertSame('job-001', $submission->payload_json['_context']['job_id'] ?? null);
        $this->assertSame('lecturer-computer-science', $submission->payload_json['_context']['job_slug'] ?? null);
        $this->assertSame('Lecturer in Computer Science', $submission->payload_json['_context']['job_title'] ?? null);

        Storage::disk('local')->assertExists($submission->files_json['cvFile']['path']);
    }

    public function test_news_event_registration_stores_server_validated_context(): void
    {
        Mail::fake();

        $payload = [
            'fullName' => 'Event Applicant',
            'email' => 'event@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'news-events',
            'event_id' => 'evt-001',
        ];

        $this->postJson('/en/forms/conference-registration/submissions', $payload)->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();
        $this->assertSame('news-events', $submission->payload_json['_context']['source'] ?? null);
        $this->assertSame('evt-001', $submission->payload_json['_context']['event_id'] ?? null);
        $this->assertSame('Annual Research Symposium & Innovation Showcase', $submission->payload_json['_context']['event_title'] ?? null);
        Mail::assertQueued(EventRegistrationReceived::class, fn (EventRegistrationReceived $mail): bool => $mail->hasTo('event@example.com') && $mail->eventTitle === 'Annual Research Symposium & Innovation Showcase');

        $this->postJson('/en/forms/conference-registration/submissions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_news_event_registration_rejects_wrong_form_and_past_event(): void
    {
        $activity = [
            'fullName' => 'Event Applicant',
            'email' => 'event@example.com',
            'event_source' => 'news-events',
            'event_id' => 'evt-001',
        ];

        $this->postJson('/en/forms/activity-registration/submissions', $activity)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');

        $conference = $activity + ['affiliation' => 'SPU', 'role' => 'attendee'];
        $conference['event_id'] = 'evt-past-001';

        $this->postJson('/en/forms/conference-registration/submissions', $conference)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');
    }

    public function test_an_unticked_optional_tick_box_does_not_fail_the_form(): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->actingAs($author, 'web');
        app(FormDefinitionServiceInterface::class)->save(null, [
            'key' => 'ai-workshop',
            'nameAr' => 'ورشة',
            'nameEn' => 'Workshop',
            'inbox' => FormSubmissionInbox::EVENT_REGISTRATIONS->value,
            'multiStep' => false,
            'steps' => [['fields' => [
                ['name' => 'fullName', 'type' => 'text', 'labelAr' => 'الاسم', 'labelEn' => 'Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'labelAr' => 'البريد', 'labelEn' => 'Email', 'required' => true],
                ['name' => 'agree', 'type' => 'checkbox', 'labelAr' => 'أوافق', 'labelEn' => 'I agree', 'required' => true],
                ['name' => 'newsletter', 'type' => 'checkbox', 'labelAr' => 'النشرة', 'labelEn' => 'Newsletter', 'required' => false],
            ]]],
        ], (int) $author->getKey());
        auth()->logout();

        // What the page sends: tick boxes as the text "true" / "false".
        $this->post('/ar/forms/ai-workshop/submissions', [
            'fullName' => 'متقدم',
            'email' => 'applicant@example.com',
            'agree' => 'true',
            'newsletter' => 'false',
        ], ['Accept' => 'application/json'])->assertCreated();

        $submission = DynamicFormSubmission::query()->where('form_id', 'ai-workshop')->firstOrFail();
        $this->assertTrue($submission->payload_json['agree']);
        $this->assertFalse($submission->payload_json['newsletter']);

        $this->post('/ar/forms/ai-workshop/submissions', [
            'fullName' => 'متقدم',
            'email' => 'other@example.com',
            'agree' => 'false',
            'newsletter' => 'false',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('agree');
    }

    public function test_an_upcoming_event_that_has_ended_moves_to_past_and_closes_registration(): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $news = app(NewsServiceInterface::class);
        $events = $news->getEditablePayload('news.events');

        foreach (['ar', 'en'] as $locale) {
            $events['translations'][$locale]['upcoming'][0]['startsAt'] = now()->subDays(2)->toIso8601String();
            $events['translations'][$locale]['upcoming'][0]['endsAt'] = now()->subDay()->toIso8601String();
            // A bare date lasts the whole day.
            $events['translations'][$locale]['upcoming'][1]['startsAt'] = now('Asia/Damascus')->toDateString();
            $events['translations'][$locale]['upcoming'][1]['endsAt'] = null;
        }

        $workflow->saveDraft('news.events', $events, (int) $author->getKey());
        $this->assertTrue($workflow->publish('news.events', (int) $author->getKey()));

        $this->assertNotContains('evt-001', $news->listNewsEvents('en')->pluck('id')->all());
        $this->assertContains('evt-002', $news->listNewsEvents('en')->pluck('id')->all());
        $past = $news->listNewsEvents('en', true)->firstWhere('id', 'evt-001');
        $this->assertNotNull($past);
        $this->assertFalse($past->isRegisterable);
        $this->assertNull($past->registrationUrl);

        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Late Applicant',
            'email' => 'late@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'news-events',
            'event_id' => 'evt-001',
        ])->assertUnprocessable()->assertJsonValidationErrors('event_id');

        // The calendar keeps past events.
        $calendar = $news->getNewsEventCalendar('en', now()->subDays(2)->format('Y-m'));
        $this->assertContains('evt-001', $calendar['events']->pluck('id')->all());
    }

    public function test_an_event_with_a_long_identifier_takes_registrations(): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $events = app(NewsServiceInterface::class)->getEditablePayload('news.events');
        // Identifiers built from a full title run past 80 characters.
        $longId = 'workshop-'.str_repeat('artificial-intelligence-skills-', 4).'2026';

        foreach (['ar', 'en'] as $locale) {
            $events['translations'][$locale]['upcoming'][0]['id'] = $longId;
        }

        $workflow->saveDraft('news.events', $events, (int) $author->getKey());
        $this->assertTrue($workflow->publish('news.events', (int) $author->getKey()));
        $this->assertGreaterThan(80, strlen($longId));

        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Workshop Applicant',
            'email' => 'workshop@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'news-events',
            'event_id' => $longId,
        ])->assertCreated();

        $this->assertSame($longId, DynamicFormSubmission::query()->firstOrFail()->payload_json['_context']['event_id'] ?? null);
    }

    public function test_research_conference_and_symposium_registrations_store_trusted_localized_context(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Conference Applicant',
            'email' => 'conference@example.com',
            'affiliation' => 'SPU',
            'role' => 'presenter',
            'event_source' => 'research-conferences',
            'event_id' => 'conf-001',
        ])->assertCreated();

        $this->postJson('/ar/forms/symposium-registration/submissions', [
            'fullName' => 'متقدم الندوة',
            'email' => 'symposium@example.com',
            'department' => 'الصيدلة',
            'year' => 'master',
            'event_source' => 'research-conferences',
            'event_id' => 'conf-002',
        ])->assertCreated();

        $conference = DynamicFormSubmission::query()->where('form_id', 'conference-registration')->firstOrFail();
        $symposium = DynamicFormSubmission::query()->where('form_id', 'symposium-registration')->firstOrFail();

        $this->assertSame('research-conferences', $conference->payload_json['_context']['source'] ?? null);
        $this->assertSame('conf-001', $conference->payload_json['_context']['event_id'] ?? null);
        $this->assertSame('International Conference on AI in Healthcare 2026', $conference->payload_json['_context']['event_title'] ?? null);
        $this->assertSame('research-conferences', $symposium->payload_json['_context']['source'] ?? null);
        $this->assertSame('conf-002', $symposium->payload_json['_context']['event_id'] ?? null);
        $this->assertSame('ندوة الابتكار الصيدلاني', $symposium->payload_json['_context']['event_title'] ?? null);
    }

    public function test_research_registration_rejects_mismatched_form_and_non_upcoming_event(): void
    {
        $conference = [
            'fullName' => 'Mismatched Applicant',
            'email' => 'mismatch@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
            'event_source' => 'research-conferences',
            'event_id' => 'conf-002',
        ];

        $this->postJson('/en/forms/conference-registration/submissions', $conference)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');

        $conference['event_id'] = 'conf-past-001';

        $this->postJson('/en/forms/conference-registration/submissions', $conference)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_research_symposium_duplicate_email_is_compared_case_insensitively(): void
    {
        $payload = [
            'fullName' => 'Symposium Applicant',
            'email' => 'Research.Duplicate@Example.COM',
            'department' => 'Pharmacy',
            'year' => 'faculty',
            'event_source' => 'research-conferences',
            'event_id' => 'conf-002',
        ];

        $this->postJson('/en/forms/symposium-registration/submissions', $payload)->assertCreated();

        $payload['email'] = 'research.duplicate@example.com';

        $this->postJson('/en/forms/symposium-registration/submissions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('dynamic_form_submissions', 1);
    }

    public function test_event_context_fields_must_be_submitted_together(): void
    {
        $payload = [
            'fullName' => 'Context Applicant',
            'email' => 'context@example.com',
            'affiliation' => 'SPU',
            'role' => 'attendee',
        ];

        $this->postJson('/en/forms/conference-registration/submissions', $payload + ['event_id' => 'conf-001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_source');

        $this->postJson('/en/forms/conference-registration/submissions', $payload + ['event_source' => 'research-conferences'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');
    }

    public function test_admissions_application_is_validated_and_stores_server_owned_context(): void
    {
        $this->postJson('/en/forms/admissions-application/submissions', [
            'fullName' => 'Admissions Applicant',
            'email' => 'admissions.applicant@example.org',
            'phone' => '+963 900 000 000',
            'applicantType' => 'transfer',
            'targetFaculty' => 'pharmacy',
            'secondaryCertificate' => 'Secondary certificate',
            'certificateCountry' => 'Syria',
            'notes' => 'Please contact me about transfer review.',
            'agreeToTerms' => true,
            '_context' => ['source' => 'attacker-controlled'],
        ])->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();

        $this->assertSame('admissions-application', $submission->form_id);
        $this->assertSame('Admissions Applicant', $submission->applicant_name);
        $this->assertSame('admissions', $submission->payload_json['_context']['source'] ?? null);
        $this->assertSame('pharmacy', $submission->payload_json['targetFaculty'] ?? null);
        $this->assertSame([], $submission->files_json ?? []);
    }

    public function test_admissions_application_rejects_invalid_or_incomplete_context(): void
    {
        $this->postJson('/ar/forms/admissions-application/submissions', [
            'fullName' => 'متقدم',
            'email' => 'invalid',
            'applicantType' => 'invented-type',
            'targetFaculty' => 'invented-faculty',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'applicantType', 'targetFaculty', 'secondaryCertificate', 'certificateCountry', 'agreeToTerms']);

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    private function publishRegistrationContexts(): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $authorId = (int) $author->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $jobs = app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.jobs');
        foreach (['ar', 'en'] as $locale) {
            foreach ($jobs['translations'][$locale]['jobs'] as &$job) {
                $job['status'] = 'open';
                $job['applicationEligible'] = true;
                $job['postedDate'] = now()->subDay()->toDateString();
                $job['closeDate'] = now()->addYear()->toDateString();
            }
            unset($job);
        }
        $workflow->saveDraft('campus_life.jobs', $jobs, $authorId);
        $this->assertTrue($workflow->publish('campus_life.jobs', $authorId));

        $events = app(NewsServiceInterface::class)->getEditablePayload('news.events');
        foreach (['ar', 'en'] as $locale) {
            foreach ($events['translations'][$locale]['upcoming'] as $index => &$event) {
                $event['startsAt'] = now()->addMonths(3)->addDays($index)->toIso8601String();
                $event['endsAt'] = now()->addMonths(3)->addDays($index)->addHours(4)->toIso8601String();
            }
            unset($event);
        }
        $workflow->saveDraft('news.events', $events, $authorId);
        $this->assertTrue($workflow->publish('news.events', $authorId));

        $conferences = app(ResearchPageServiceInterface::class)->getEditablePayload('research.conferences');
        foreach (['ar', 'en'] as $locale) {
            foreach ($conferences['translations'][$locale]['upcoming'] as $index => &$conference) {
                $conference['date'] = now()->addMonths($index + 2)->format('F Y');
            }
            unset($conference);

            foreach ($conferences['translations'][$locale]['past'] as &$conference) {
                $conference['hasProceedings'] = false;
                unset($conference['proceedingsUrl']);
            }
            unset($conference);
        }
        $workflow->saveDraft('research.conferences', $conferences, $authorId);
        $this->assertTrue($workflow->publish('research.conferences', $authorId));
    }
}
