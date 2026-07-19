<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Filament\Pages\ManageCampusLife;
use App\Filament\Resources\DynamicFormSubmissionResource\Pages\ListDynamicFormSubmissions;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class CampusLifeJobsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{en: string, ar: string}> */
    private const JOBS = [
        'lecturer-computer-science' => ['en' => 'Lecturer in Computer Science', 'ar' => 'محاضر في علوم الحاسوب'],
        'research-assistant' => ['en' => 'Research Assistant', 'ar' => 'مساعد باحث'],
        'administrative-coordinator' => ['en' => 'Administrative Coordinator', 'ar' => 'منسق إداري'],
        'admissions-officer' => ['en' => 'Admissions Officer', 'ar' => 'موظف قبول وتسجيل'],
        'campus-bus-driver' => ['en' => 'Campus Bus Driver', 'ar' => 'سائق حافلة الجامعة'],
        'it-support-specialist' => ['en' => 'IT Support Specialist', 'ar' => 'أخصائي دعم تقنية المعلومات'],
        'laboratory-technician' => ['en' => 'Laboratory Technician', 'ar' => 'فني مختبر'],
        'dental-clinic-supervisor' => ['en' => 'Dental Clinic Supervisor', 'ar' => 'مشرف العيادات السنية'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_job_target_is_registered_under_career_development_and_has_a_curated_editor(): void
    {
        $target = app(CmsTargetRegistryInterface::class)->find('campus_life.jobs');

        $this->assertNotNull($target);
        $this->assertSame('campus_life.career-development', $target->parentKey);
        $this->assertSame('/campus-life/career-development/jobs', $target->publicPath);

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.jobs')
            ->call('loadTarget', 'campus_life.jobs')
            ->assertSee('Job Board Hero')
            ->assertSee('Job Catalog')
            ->assertDontSee('Target Schema Pending');
    }

    public function test_board_and_all_eight_job_details_render_in_both_locales_with_structured_data(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/campus-life/career-development/jobs')
                ->assertOk()
                ->assertSee($locale === 'ar' ? 'لوحة الوظائف' : 'Job Board');

            foreach (self::JOBS as $slug => $titles) {
                $this->get('/'.$locale.'/campus-life/career-development/jobs/'.$slug)
                    ->assertOk()
                    ->assertSee($titles[$locale])
                    ->assertSee('JobPosting')
                    ->assertSee('validThrough')
                    ->assertSee('/'.$locale.'/campus-life/career-development/jobs/'.$slug, false);
            }
        }
    }

    public function test_board_filters_and_bounded_pagination_preserve_query_and_locale(): void
    {
        $this->get('/en/campus-life/career-development/jobs?q=Research&category=academic&type=contract')
            ->assertOk()
            ->assertSee('Research Assistant')
            ->assertDontSee('Lecturer in Computer Science')
            ->assertSee('q=Research', false)
            ->assertSee('category=academic', false)
            ->assertSee('type=contract', false)
            ->assertSee('/ar/campus-life/career-development/jobs', false);

        $this->get('/en/campus-life/career-development/jobs?page=2')
            ->assertOk()
            ->assertSee('Laboratory Technician')
            ->assertSee('Dental Clinic Supervisor')
            ->assertDontSee('Lecturer in Computer Science');

        $this->get('/en/campus-life/career-development/jobs?page=999')
            ->assertOk()
            ->assertSee('Dental Clinic Supervisor')
            ->assertDontSee('Lecturer in Computer Science');

        $this->get('/ar/campus-life/career-development/jobs?q=missing-position')
            ->assertOk()
            ->assertSee('لا توجد وظائف تطابق بحثك')
            ->assertSee('إعادة ضبط المرشحات');
    }

    public function test_jobs_catalog_readiness_requires_complete_matching_bilingual_invariants(): void
    {
        $service = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $service->getEditablePayload('campus_life.jobs');

        $this->assertTrue($workflow->readiness('campus_life.jobs', $payload)->isReady);

        $payload['translations']['en']['jobs'][0]['slug'] = 'mismatched-job';
        $readiness = $workflow->readiness('campus_life.jobs', $payload);

        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('jobs', $readiness->errors);

        $payload = $service->getEditablePayload('campus_life.jobs');
        $payload['translations']['ar']['jobs'][0]['requirements'] = [];

        $this->assertFalse($workflow->readiness('campus_life.jobs', $payload)->isReady);
    }

    public function test_jobs_catalog_lifecycle_preview_publish_schedule_unpublish_and_audit(): void
    {
        $service = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $service->getEditablePayload('campus_life.jobs');
        $payload['translations']['en']['hero']['title'] = 'Draft Careers Catalog';

        $workflow->saveDraft('campus_life.jobs', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.jobs', 'en', (int) $author->id);

        $this->get('/en/campus-life/career-development/jobs')->assertDontSee('Draft Careers Catalog');
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Draft Careers Catalog')
            ->assertSee('Preview mode');
        $this->get($preview->previewUrl.'&job=lecturer-computer-science')
            ->assertOk()
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('noindex,nofollow,noarchive', false);

        $this->assertTrue($workflow->publish('campus_life.jobs', (int) $author->id));
        $this->get('/en/campus-life/career-development/jobs')->assertSee('Draft Careers Catalog');
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.published']);

        $scheduled = $service->getEditablePayload('campus_life.jobs');
        $scheduled['translations']['en']['hero']['title'] = 'Scheduled Careers Catalog';
        $workflow->saveDraft('campus_life.jobs', $scheduled, (int) $author->id);
        $this->assertTrue($workflow->schedule('campus_life.jobs', now()->addHour(), (int) $author->id));
        $this->assertTrue($workflow->unpublish('campus_life.jobs', (int) $author->id));
        $this->assertNull($workflow->getPublishedPayload('campus_life.jobs'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.unpublished']);
    }

    public function test_only_open_unexpired_jobs_are_public_and_application_context_is_required(): void
    {
        Storage::fake('local');
        $service = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $service->getEditablePayload('campus_life.jobs');

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['jobs'][1]['status'] = 'closed';
            $payload['translations'][$locale]['jobs'][2]['postedDate'] = now()->subDays(2)->toDateString();
            $payload['translations'][$locale]['jobs'][2]['closeDate'] = now()->subDay()->toDateString();
        }

        $workflow->saveDraft('campus_life.jobs', $payload, (int) $author->id);
        $workflow->publish('campus_life.jobs', (int) $author->id);

        $this->get('/en/campus-life/career-development/jobs')->assertDontSee('Research Assistant')->assertDontSee('Administrative Coordinator');
        $this->get('/en/campus-life/career-development/jobs/research-assistant')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/administrative-coordinator')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/apply')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/apply?job=unknown-job')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/apply?job=research-assistant')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/apply?job=administrative-coordinator')->assertNotFound();

        $this->post('/en/forms/job-application/submissions', $this->applicationPayload(null, null))
            ->assertSessionHasErrors('job_slug');
        $this->post('/en/forms/job-application/submissions', $this->applicationPayload('job-002', 'research-assistant'))
            ->assertSessionHasErrors('job_slug');
        $this->post('/en/forms/job-application/submissions', $this->applicationPayload('job-003', 'administrative-coordinator'))
            ->assertSessionHasErrors('job_slug');
        $this->assertDatabaseCount('dynamic_form_submissions', 0);
    }

    public function test_application_persists_server_validated_context_and_admin_can_review_it(): void
    {
        Storage::fake('local');

        $this->get('/ar/campus-life/career-development/jobs/apply?job=lecturer-computer-science')
            ->assertOk()
            ->assertSee('محاضر في علوم الحاسوب')
            ->assertSee('/en/campus-life/career-development/jobs/apply?job=lecturer-computer-science', false);

        $this->post('/en/forms/job-application/submissions', $this->applicationPayload('job-001', 'lecturer-computer-science'))
            ->assertCreated();

        $submission = DynamicFormSubmission::query()->firstOrFail();
        $this->assertSame([
            'source' => 'campus-life-jobs',
            'job_id' => 'job-001',
            'job_slug' => 'lecturer-computer-science',
            'job_title' => 'Lecturer in Computer Science',
        ], $submission->payload_json['_context'] ?? null);
        Storage::disk('local')->assertExists($submission->files_json['cvFile']['path']);

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
        Livewire::test(ListDynamicFormSubmissions::class)
            ->assertSee('Lecturer in Computer Science')
            ->assertSee('Selected Job');
    }

    public function test_faculty_editor_cannot_manage_global_jobs_catalog(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $this->expectException(AuthorizationException::class);

        app(CmsWorkflowServiceInterface::class)->saveDraft(
            'campus_life.jobs',
            app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.jobs'),
            (int) $user->id,
        );
    }

    /** @return array<string, mixed> */
    private function applicationPayload(?string $jobId, ?string $jobSlug): array
    {
        return array_filter([
            'firstNameAr' => 'أحمد',
            'lastNameAr' => 'الخطيب',
            'email' => 'applicant'.uniqid().'@example.com',
            'phone' => '+963 999 999 999',
            'gender' => 'male',
            'profession' => 'Lecturer',
            'birthDate' => '1990-01-01',
            'educationLevel' => 'phd',
            'highestUniversity' => 'SPU',
            'englishLevel' => 'advanced',
            'targetFaculty' => 'ai',
            'generalSpecialization' => 'Computer Science',
            'preciseSpecialization' => 'Machine Learning',
            'academicRank' => 'assistant-professor',
            'contractType' => 'full-time',
            'cvFile' => UploadedFile::fake()->create('cv.pdf', 128, 'application/pdf'),
            'hasPriorCriminalRecord' => 'no',
            'canProvideReferences' => 'yes',
            'agreeToTerms' => '1',
            'job_id' => $jobId,
            'job_slug' => $jobSlug,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
