<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Form\DynamicFormSubmissionReviewServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionDetailSectionDTO;
use App\Enums\FormSubmissionInbox;
use App\Enums\FormSubmissionStatus;
use App\Exceptions\ConflictException;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DynamicFormSubmissionReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private DynamicFormSubmissionReviewServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DynamicFormSubmissionReviewServiceInterface::class);
    }

    public function test_inbox_maps_all_supported_forms_and_statuses_have_inbox_specific_transitions(): void
    {
        $this->assertSame(FormSubmissionInbox::EVENT_REGISTRATIONS, FormSubmissionInbox::fromFormId('conference-registration'));
        $this->assertSame(FormSubmissionInbox::EVENT_REGISTRATIONS, FormSubmissionInbox::fromFormId('symposium-registration'));
        $this->assertSame(FormSubmissionInbox::EVENT_REGISTRATIONS, FormSubmissionInbox::fromFormId('activity-registration'));
        $this->assertSame(FormSubmissionInbox::JOBS, FormSubmissionInbox::fromFormId('job-application'));
        $this->assertSame(FormSubmissionInbox::ADMISSIONS, FormSubmissionInbox::fromFormId('admissions-application'));
        $this->assertSame(FormSubmissionInbox::SUGGESTIONS, FormSubmissionInbox::fromFormId('suggestions-complaints'));
        $this->assertNull(FormSubmissionInbox::tryFromFormId('legacy-form'));

        $this->assertTrue(FormSubmissionStatus::NEW->canTransitionTo(FormSubmissionStatus::IN_REVIEW, FormSubmissionInbox::JOBS));
        $this->assertTrue(FormSubmissionStatus::IN_REVIEW->canTransitionTo(FormSubmissionStatus::ACCEPTED, FormSubmissionInbox::ADMISSIONS));
        $this->assertTrue(FormSubmissionStatus::IN_REVIEW->canTransitionTo(FormSubmissionStatus::REJECTED, FormSubmissionInbox::EVENT_REGISTRATIONS));
        $this->assertFalse(FormSubmissionStatus::IN_REVIEW->canTransitionTo(FormSubmissionStatus::RESOLVED, FormSubmissionInbox::JOBS));
        $this->assertTrue(FormSubmissionStatus::IN_REVIEW->canTransitionTo(FormSubmissionStatus::RESOLVED, FormSubmissionInbox::SUGGESTIONS));
        $this->assertFalse(FormSubmissionStatus::IN_REVIEW->canTransitionTo(FormSubmissionStatus::ACCEPTED, FormSubmissionInbox::SUGGESTIONS));
        $this->assertTrue(FormSubmissionStatus::RESOLVED->canTransitionTo(FormSubmissionStatus::CLOSED, FormSubmissionInbox::SUGGESTIONS));
    }

    public function test_details_follow_schema_order_for_all_six_forms(): void
    {
        $forms = [
            'conference-registration' => [['role' => 'presenter', 'fullName' => 'Conference Applicant'], ['fullName', 'role']],
            'symposium-registration' => [['year' => 'master', 'fullName' => 'Symposium Applicant'], ['fullName', 'year']],
            'activity-registration' => [['notes' => 'Activity note', 'fullName' => 'Activity Applicant'], ['fullName', 'notes']],
            'admissions-application' => [['applicantType' => 'transfer', 'fullName' => 'Admissions Applicant'], ['fullName', 'applicantType']],
            'job-application' => [['gender' => 'female', 'firstNameAr' => 'سارة'], ['firstNameAr', 'gender']],
            'suggestions-complaints' => [['requestType' => 'complaint', 'fullName' => 'Suggestion Applicant'], ['fullName', 'requestType']],
        ];

        foreach ($forms as $formId => [$payload, $expectedOrder]) {
            $submission = $this->submission($formId, $payload);
            $details = $this->service->getDetails((int) $submission->getKey(), 'en');
            $submittedFields = $this->section($details->sections, 'submitted_fields')->fields;

            $this->assertSame($expectedOrder, array_map(static fn ($field): string => $field->key, $submittedFields));
            $this->assertNotSame($formId, $details->formLabel);
            $this->assertNotSame($expectedOrder[0], $submittedFields[0]->label);
        }
    }

    public function test_details_localize_enum_values_preserve_free_text_and_separate_context_and_technical_data(): void
    {
        $submission = $this->submission('conference-registration', [
            'role' => 'presenter',
            'specialNeeds' => "  Keep this text exactly.\nSecond line.  ",
            'fullName' => 'Applicant',
            'legacy_code' => ['old' => true],
            '_context' => [
                'source' => 'research-conferences',
                'event_title' => 'Research Event',
                'event_id' => 'evt-1',
            ],
        ]);

        $details = $this->service->getDetails((int) $submission->getKey(), 'ar');
        $submittedFields = $this->section($details->sections, 'submitted_fields')->fields;
        $role = $this->field($submittedFields, 'role');
        $specialNeeds = $this->field($submittedFields, 'specialNeeds');
        $legacy = $this->field($submittedFields, 'legacy_code');
        $context = $this->section($details->sections, 'context');
        $technical = $this->section($details->sections, 'technical_request');

        $this->assertSame('متحدث', $role->displayValue);
        $this->assertSame("  Keep this text exactly.\nSecond line.  ", $specialNeeds->displayValue);
        $this->assertTrue($legacy->isLegacyField);
        $this->assertSame('{"old":true}', $legacy->displayValue);
        $this->assertSame('المؤتمرات البحثية', $this->field($context->fields, 'source')->displayValue);
        $this->assertSame('Research Event', $this->field($context->fields, 'event_title')->displayValue);
        $this->assertTrue($technical->isTechnical);
        $this->assertSame('127.0.0.1', $this->field($technical->fields, 'ip_address')->displayValue);
    }

    public function test_unknown_legacy_select_value_is_included_without_translation_or_failure(): void
    {
        $submission = $this->submission('conference-registration', [
            'fullName' => 'Legacy Applicant',
            'role' => 'retired-speaker-role',
        ]);

        $details = $this->service->getDetails((int) $submission->getKey(), 'ar');
        $role = $this->field($this->section($details->sections, 'submitted_fields')->fields, 'role');

        $this->assertTrue($role->isLegacyValue);
        $this->assertSame('retired-speaker-role', $role->rawValue);
        $this->assertSame('retired-speaker-role', $role->displayValue);
    }

    public function test_legal_transition_uses_expected_status_and_records_complete_audit_metadata(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('admissions-application', ['fullName' => 'Applicant']);

        $this->assertTrue($this->service->transitionStatus(
            (int) $submission->getKey(),
            FormSubmissionStatus::NEW,
            FormSubmissionStatus::IN_REVIEW,
            (int) $editor->getKey(),
        ));

        $this->assertSame('in_review', $submission->fresh()->status);
        $audit = AuditLog::query()->where('action', 'dynamic_form_submission.status_changed')->firstOrFail();
        $this->assertSame((int) $editor->getKey(), $audit->metadata['actor'] ?? null);
        $this->assertSame('new', $audit->metadata['from'] ?? null);
        $this->assertSame('in_review', $audit->metadata['to'] ?? null);
        $this->assertSame('admissions-application', $audit->metadata['form'] ?? null);
        $this->assertSame('admissions', $audit->metadata['inbox'] ?? null);
    }

    public function test_stale_transition_is_rejected_without_second_audit_event(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('job-application', ['firstNameAr' => 'متقدم']);
        $this->service->transitionStatus(
            (int) $submission->getKey(),
            FormSubmissionStatus::NEW,
            FormSubmissionStatus::IN_REVIEW,
            (int) $editor->getKey(),
        );

        try {
            $this->service->transitionStatus(
                (int) $submission->getKey(),
                FormSubmissionStatus::NEW,
                FormSubmissionStatus::IN_REVIEW,
                (int) $editor->getKey(),
            );
            $this->fail('A stale transition must be rejected.');
        } catch (ConflictException) {
            $this->assertSame('in_review', $submission->fresh()->status);
            $this->assertDatabaseCount('audit_logs', 1);
        }
    }

    public function test_illegal_inbox_transition_is_rejected_without_mutation(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('suggestions-complaints', ['fullName' => 'Applicant'], status: 'in_review');

        $this->expectException(\DomainException::class);

        try {
            $this->service->transitionStatus(
                (int) $submission->getKey(),
                FormSubmissionStatus::IN_REVIEW,
                FormSubmissionStatus::ACCEPTED,
                (int) $editor->getKey(),
            );
        } finally {
            $this->assertSame('in_review', $submission->fresh()->status);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_policy_grants_only_explicit_review_abilities_to_global_editors(): void
    {
        $submission = $this->submission('activity-registration', ['fullName' => 'Applicant']);
        $superAdmin = User::factory()->create(['role_slug' => 'super_admin']);
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $facultyEditor = User::factory()->create(['role_slug' => 'faculty_editor']);

        foreach ([$superAdmin, $editor] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('transitionStatus', $submission));
            $this->assertTrue(Gate::forUser($user)->allows('downloadAttachment', $submission));
            $this->assertFalse(Gate::forUser($user)->allows('update', $submission));
            $this->assertFalse(Gate::forUser($user)->allows('delete', $submission));
        }

        $this->assertFalse(Gate::forUser($facultyEditor)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('transitionStatus', $submission));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('downloadAttachment', $submission));
    }

    public function test_faculty_editor_cannot_transition_submission_status(): void
    {
        $facultyEditor = User::factory()->create(['role_slug' => 'faculty_editor']);
        $submission = $this->submission('activity-registration', ['fullName' => 'Applicant']);

        $this->expectException(AuthorizationException::class);

        $this->service->transitionStatus(
            (int) $submission->getKey(),
            FormSubmissionStatus::NEW,
            FormSubmissionStatus::IN_REVIEW,
            (int) $facultyEditor->getKey(),
        );
    }

    public function test_secure_attachment_resolution_returns_private_metadata_with_sanitized_basename(): void
    {
        Storage::fake('local');
        $path = 'dynamic-form-submissions/job-application/2026/07/private.pdf';
        Storage::disk('local')->put($path, 'private cv content');
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('job-application', ['firstNameAr' => 'متقدم'], [
            'cvFile' => [
                'disk' => 'local',
                'path' => $path,
                'original_name' => "../unsafe/CV\r\n.pdf",
                'mime_type' => 'application/pdf',
                'size' => 18,
            ],
        ]);

        $download = $this->service->resolveAttachment(
            (int) $submission->getKey(),
            'cvFile',
            (int) $editor->getKey(),
        );

        $this->assertSame('local', $download->disk);
        $this->assertSame($path, $download->path);
        $this->assertSame('CV.pdf', $download->downloadName);
        $this->assertSame(strlen('private cv content'), $download->size);
        $this->assertFalse(str_contains($download->path, '://'));
    }

    public function test_attachment_resolution_rejects_forged_traversal_path_even_when_target_exists(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dynamic-form-submissions/secret.pdf', 'secret');
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('job-application', ['firstNameAr' => 'متقدم'], [
            'cvFile' => [
                'disk' => 'local',
                'path' => 'dynamic-form-submissions/job-application/../../secret.pdf',
                'original_name' => 'secret.pdf',
            ],
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->resolveAttachment(
            (int) $submission->getKey(),
            'cvFile',
            (int) $editor->getKey(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $files
     */
    private function submission(
        string $formId,
        array $payload,
        array $files = [],
        string $status = 'new',
    ): DynamicFormSubmission {
        return DynamicFormSubmission::query()->create([
            'form_id' => $formId,
            'locale' => 'en',
            'applicant_name' => is_string($payload['fullName'] ?? null) ? $payload['fullName'] : 'Applicant',
            'applicant_email' => 'applicant@example.com',
            'status' => $status,
            'payload_json' => $payload,
            'files_json' => $files,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Review service test agent',
        ]);
    }

    /**
     * @param  list<DynamicFormSubmissionDetailSectionDTO>  $sections
     */
    private function section(array $sections, string $key): DynamicFormSubmissionDetailSectionDTO
    {
        foreach ($sections as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        $this->fail('Missing detail section: '.$key);
    }

    /** @param array<int, object{key: string}> $fields */
    private function field(array $fields, string $key): object
    {
        foreach ($fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        $this->fail('Missing detail field: '.$key);
    }
}
