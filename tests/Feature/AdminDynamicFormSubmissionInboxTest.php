<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FormSubmissionInbox;
use App\Filament\Resources\DynamicFormSubmissionResource;
use App\Filament\Resources\DynamicFormSubmissionResource\Pages\ListDynamicFormSubmissions;
use App\Filament\Resources\DynamicFormSubmissionResource\Pages\ViewDynamicFormSubmission;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminDynamicFormSubmissionInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_persisted_task_tabs_have_disjoint_scopes_and_correct_counts(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $conference = $this->submission('conference-registration', ['fullName' => 'Conference Applicant']);
        $symposium = $this->submission('symposium-registration', ['fullName' => 'Symposium Applicant']);
        $activity = $this->submission('activity-registration', ['fullName' => 'Activity Applicant']);
        $job = $this->submission('job-application', [
            'firstNameAr' => 'متقدم',
            '_context' => ['job_title' => 'Open lecturer position'],
        ]);
        $admissions = $this->submission('admissions-application', ['fullName' => 'Admissions Applicant']);
        $suggestion = $this->submission('suggestions-complaints', ['fullName' => 'Feedback Applicant']);

        $this->actingAs($editor, 'web');

        $component = Livewire::withQueryParams(['activeTab' => FormSubmissionInbox::JOBS->value])
            ->test(ListDynamicFormSubmissions::class)
            ->assertSet('activeTab', FormSubmissionInbox::JOBS->value);

        $tabs = $component->instance()->getCachedTabs();
        $this->assertSame(3, $tabs[FormSubmissionInbox::EVENT_REGISTRATIONS->value]->getBadge());
        $this->assertSame(1, $tabs[FormSubmissionInbox::JOBS->value]->getBadge());
        $this->assertSame(1, $tabs[FormSubmissionInbox::ADMISSIONS->value]->getBadge());
        $this->assertSame(1, $tabs[FormSubmissionInbox::SUGGESTIONS->value]->getBadge());

        $component->set('tableSearch', 'Open lecturer position');
        $this->assertSame([(int) $job->id], $component->instance()->getTableRecords()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $component->set('tableSearch', '');
        $this->assertNull($component->instance()->getTable()->getAction('delete'));
        $this->assertNull($component->instance()->getTable()->getBulkAction('delete'));
        $this->assertFalse(DynamicFormSubmissionResource::canCreate());
        $this->assertFalse(DynamicFormSubmissionResource::canEdit($job));
        $this->assertFalse(DynamicFormSubmissionResource::canDelete($job));

        $expected = [
            FormSubmissionInbox::EVENT_REGISTRATIONS->value => [$conference->id, $symposium->id, $activity->id],
            FormSubmissionInbox::JOBS->value => [$job->id],
            FormSubmissionInbox::ADMISSIONS->value => [$admissions->id],
            FormSubmissionInbox::SUGGESTIONS->value => [$suggestion->id],
        ];
        $seen = [];

        foreach ($expected as $tab => $recordIds) {
            $component->set('activeTab', $tab);
            $actualIds = $component->instance()->getTableRecords()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            sort($actualIds);
            sort($recordIds);

            $this->assertSame($recordIds, $actualIds);
            $this->assertSame([], array_intersect($seen, $actualIds), 'A submission appeared in more than one inbox.');
            $seen = array_merge($seen, $actualIds);
        }
    }

    public function test_list_and_detail_are_localized_and_detail_leaks_no_raw_json_or_private_metadata(): void
    {
        Storage::fake('local');
        $path = 'dynamic-form-submissions/job-application/2026/07/private-cv.pdf';
        Storage::disk('local')->put($path, 'private cv');
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('job-application', [
            'firstNameAr' => '<script>alert(1)</script>',
            'gender' => 'female',
            'legacy_blob' => ['private_path' => 'must-not-render'],
            '_context' => [
                'source' => 'campus-life-jobs',
                'job_title' => 'Lecturer in Computer Science',
                'job_id' => 'internal-job-id',
                'job_slug' => 'internal-job-slug',
                'secret_internal_key' => 'hidden-context-value',
            ],
        ], [
            'cvFile' => [
                'disk' => 'local',
                'path' => $path,
                'original_name' => 'candidate-cv.pdf',
                'mime_type' => 'application/pdf',
                'size' => 10,
            ],
        ], applicantName: '<script>alert(1)</script>');

        $this->actingAs($editor, 'web');

        app()->setLocale('ar');
        Livewire::withQueryParams(['activeTab' => FormSubmissionInbox::JOBS->value])
            ->test(ListDynamicFormSubmissions::class)
            ->assertSee('صناديق الطلبات والمشاركات')
            ->assertSee('طلبات التوظيف')
            ->assertSee('مقدم الطلب')
            ->assertDontSee('Related event, job, or subject');

        $this->withSession(['admin_locale' => 'ar'])
            ->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('مراجعة طلب')
            ->assertSee('تنزيل آمن')
            ->assertDontSee('Secure download');

        $this->withSession(['admin_locale' => 'en'])
            ->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('Review &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('Unverified legacy context was hidden for safety.')
            ->assertSee('Secure download')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($path)
            ->assertDontSee('must-not-render')
            ->assertDontSee('payload_json')
            ->assertDontSee('files_json')
            ->assertDontSee('internal-job-id')
            ->assertDontSee('internal-job-slug')
            ->assertDontSee('secret_internal_key')
            ->assertDontSee('hidden-context-value');
    }

    public function test_legal_transition_action_updates_status_and_illegal_actions_are_absent(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $submission = $this->submission('admissions-application', ['fullName' => 'Applicant']);
        $this->actingAs($editor, 'web');
        app()->setLocale('en');

        Livewire::test(ViewDynamicFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertActionExists('transition_in_review')
            ->assertActionDoesNotExist('transition_accepted')
            ->callAction('transition_in_review')
            ->assertNotified(__('form_submissions.notifications.transitioned'))
            ->assertRedirect(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]));

        $this->assertDatabaseHas('dynamic_form_submissions', [
            'id' => $submission->id,
            'status' => 'in_review',
        ]);
    }

    public function test_cv_and_feedback_downloads_are_private_and_never_use_public_urls(): void
    {
        Storage::fake('local');
        $editor = User::factory()->create(['role_slug' => 'editor']);
        $this->actingAs($editor, 'web');

        $downloads = [
            ['job-application', 'cvFile', 'cv.pdf', 'private cv content'],
            ['suggestions-complaints', 'attachment', 'feedback.txt', 'private feedback content'],
        ];

        foreach ($downloads as [$formId, $field, $filename, $content]) {
            $path = 'dynamic-form-submissions/'.$formId.'/2026/07/'.$filename;
            Storage::disk('local')->put($path, $content);
            $submission = $this->submission($formId, ['fullName' => 'Applicant'], [
                $field => [
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $filename,
                    'mime_type' => $filename === 'cv.pdf' ? 'application/pdf' : 'text/plain',
                    'size' => strlen($content),
                ],
            ]);

            $response = $this->get(route('admin.form-submissions.attachments.download', [
                'submission' => $submission->id,
                'field' => $field,
            ]));

            $response->assertOk()
                ->assertDownload($filename)
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($content, $response->streamedContent());
            $this->assertStringNotContainsString('public/', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_faculty_editor_is_denied_inbox_detail_and_attachment_download(): void
    {
        Storage::fake('local');
        $path = 'dynamic-form-submissions/job-application/2026/07/cv.pdf';
        Storage::disk('local')->put($path, 'private');
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);
        $submission = $this->submission('job-application', ['fullName' => 'Applicant'], [
            'cvFile' => [
                'disk' => 'local',
                'path' => $path,
                'original_name' => 'cv.pdf',
            ],
        ]);

        $this->actingAs($facultyEditor, 'web');

        $this->get(DynamicFormSubmissionResource::getUrl('index'))->assertForbidden();
        $this->get(DynamicFormSubmissionResource::getUrl('view', ['record' => $submission]))->assertForbidden();
        $this->get(route('admin.form-submissions.attachments.download', [
            'submission' => $submission->id,
            'field' => 'cvFile',
        ]))->assertForbidden();
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
        ?string $applicantName = null,
    ): DynamicFormSubmission {
        return DynamicFormSubmission::query()->create([
            'form_id' => $formId,
            'locale' => 'en',
            'applicant_name' => $applicantName ?? (is_string($payload['fullName'] ?? null) ? $payload['fullName'] : 'Applicant'),
            'applicant_email' => 'applicant@example.com',
            'status' => $status,
            'payload_json' => $payload,
            'files_json' => $files,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Admin inbox test agent',
        ]);
    }
}
