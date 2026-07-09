<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form\DynamicFormSubmission;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        ])->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();

        $this->assertSame('job-application', $submission->form_id);
        $this->assertSame('أحمد الخطيب', $submission->applicant_name);
        $this->assertArrayHasKey('cvFile', $submission->files_json ?? []);

        Storage::disk('local')->assertExists($submission->files_json['cvFile']['path']);
    }
}
