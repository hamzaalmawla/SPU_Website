<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\EventRegistrationReceived;
use App\Models\Form\DynamicFormSubmission;
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
    }

    public function test_conference_registration_submission_is_stored(): void
    {
        $this->postJson('/en/forms/conference-registration/submissions', [
            'fullName' => 'Jane Applicant',
            'email' => 'jane@example.com',
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
}
