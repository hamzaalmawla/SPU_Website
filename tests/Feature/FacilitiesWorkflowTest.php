<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Filament\Pages\ManageBuildingConstructionEngineeringFaculty;
use App\Filament\Pages\ManageBusinessAdministrationFaculty;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Filament\Pages\ManageFacilities;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Filament\Pages\ManagePetroleumFaculty;
use App\Filament\Pages\ManagePharmacyFaculty;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FacilitiesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_facilities_hub_workflow_draft_does_not_leak_until_published(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.landing');
        $payload['translations']['en']['hero']['title'] = 'Published Facilities Workflow';
        $payload['translations']['en']['hero']['summary'] = 'Published facilities workflow summary.';
        $payload['translations']['ar']['hero']['title'] = 'المرافق المنشورة';
        $payload['translations']['ar']['hero']['summary'] = 'ملخص المرافق المنشورة.';

        $workflow->saveDraft('facilities.landing', $payload, (int) $author->id);

        $this->get('/en/facilities')
            ->assertOk()
            ->assertDontSee('Published Facilities Workflow');

        $this->assertTrue($workflow->publish('facilities.landing', (int) $author->id));

        $this->get('/en/facilities')
            ->assertOk()
            ->assertSee('Published Facilities Workflow');
    }

    public function test_facilities_hub_preview_renders_draft_snapshot(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.landing');
        $payload['translations']['en']['hero']['title'] = 'Facilities Preview Workflow';
        $payload['translations']['en']['hero']['summary'] = 'Facilities preview workflow summary.';
        $payload['translations']['ar']['hero']['title'] = 'معاينة المرافق';
        $payload['translations']['ar']['hero']['summary'] = 'ملخص معاينة المرافق.';

        $workflow->saveDraft('facilities.landing', $payload, (int) $author->id);
        $preview = $workflow->preview('facilities.landing', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Facilities Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/facilities')
            ->assertOk()
            ->assertDontSee('Facilities Preview Workflow');
    }

    public function test_manage_facilities_uses_curated_hub_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageFacilities::class)
            ->assertSee('Hero')
            ->assertSee('Facts')
            ->assertSee('Faculty Buttons')
            ->assertSee('Academic Model');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $facts = is_array($data['en_content']['facts'] ?? null) ? $data['en_content']['facts'] : [];
        $facts[] = [
            'value' => '99',
            'label' => 'Curated Facilities Fact',
        ];
        $facultyLinks = is_array($data['en_content']['facultyLinks'] ?? null) ? $data['en_content']['facultyLinks'] : [];
        $facultyLinks[] = [
            'title' => 'Curated Faculty Button',
            'summary' => 'Curated faculty button summary.',
            'url' => '/en/facilities/medicine',
            'accentColor' => '#202759',
        ];

        $component
            ->set('data.en_content.hero.title', 'Curated Facilities Hub')
            ->set('data.en_content.hero.summary', 'Curated facilities hub summary.')
            ->set('data.en_content.facts', $facts)
            ->set('data.en_content.facultyLinks', $facultyLinks)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.landing')->latest('id')->firstOrFail();
        $factLabels = collect($draft->payload_json['translations']['en']['facts'] ?? [])->pluck('label')->all();
        $facultyButtonTitles = collect($draft->payload_json['translations']['en']['facultyLinks'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Facilities Hub', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Facilities Fact', $factLabels);
        $this->assertContains('Curated Faculty Button', $facultyButtonTitles);
    }

    public function test_medicine_faculty_workflow_draft_does_not_leak_until_published(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine');
        $payload['translations']['en']['title'] = 'Published Medicine CMS Homepage';
        $payload['translations']['en']['summary'] = 'Published medicine CMS summary.';
        $payload['translations']['en']['faculty']['name'] = 'Published Medicine Faculty Name';
        $payload['translations']['ar']['title'] = 'صفحة الطب المنشورة';
        $payload['translations']['ar']['summary'] = 'ملخص صفحة الطب المنشورة.';
        $payload['translations']['ar']['faculty']['name'] = 'كلية الطب المنشورة';

        $workflow->saveDraft('facilities.medicine', $payload, (int) $author->id);

        $this->get('/en/facilities/medicine')
            ->assertOk()
            ->assertDontSee('Published Medicine CMS Homepage');

        $this->assertTrue($workflow->publish('facilities.medicine', (int) $author->id));

        $this->get('/en/facilities/medicine')
            ->assertOk()
            ->assertSee('Published Medicine CMS Homepage')
            ->assertSee('Published Medicine Faculty Name');
    }

    public function test_medicine_faculty_preview_renders_draft_snapshot(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine');
        $payload['translations']['en']['title'] = 'Medicine Preview CMS Homepage';
        $payload['translations']['en']['summary'] = 'Medicine preview CMS summary.';
        $payload['translations']['en']['tabs'][] = [
            'id' => 'preview-tab',
            'label' => 'Preview Tab',
            'body' => 'Medicine preview tab body.',
        ];

        $workflow->saveDraft('facilities.medicine', $payload, (int) $author->id);
        $preview = $workflow->preview('facilities.medicine', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Medicine Preview CMS Homepage')
            ->assertSee('Preview Tab')
            ->assertSee('Preview mode');

        $this->get('/en/facilities/medicine')
            ->assertOk()
            ->assertDontSee('Medicine Preview CMS Homepage');
    }

    public function test_manage_medicine_faculty_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageMedicineFaculty::class)
            ->assertSee('Page Content')
            ->assertSee('Faculty Identity')
            ->assertSee('Overview Tabs')
            ->assertDontSee('Body Sections')
            ->assertSee('Dean Message')
            ->assertSee('Latest Research Cards');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $tabs = is_array($data['en_content']['tabs'] ?? null) ? $data['en_content']['tabs'] : [];
        $tabs[] = [
            'id' => 'curated-tab',
            'label' => 'Curated Medicine Tab',
            'body' => 'Curated medicine tab body.',
        ];

        $component
            ->set('data.en_content.title', 'Curated Medicine Homepage')
            ->set('data.en_content.summary', 'Curated medicine homepage summary.')
            ->set('data.en_content.faculty.name', 'Curated Medicine Faculty')
            ->set('data.en_content.dean.name', 'Curated Dean')
            ->set('data.en_content.tabs', $tabs)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.medicine')->latest('id')->firstOrFail();
        $tabLabels = collect($draft->payload_json['translations']['en']['tabs'] ?? [])->pluck('label')->all();

        $this->assertSame('Curated Medicine Homepage', $draft->payload_json['translations']['en']['title'] ?? null);
        $this->assertSame('Curated Medicine Faculty', $draft->payload_json['translations']['en']['faculty']['name'] ?? null);
        $this->assertSame('Curated Dean', $draft->payload_json['translations']['en']['dean']['name'] ?? null);
        $this->assertContains('Curated Medicine Tab', $tabLabels);
    }

    public function test_medicine_overview_subpage_workflow_draft_does_not_leak_until_published(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.overview');
        $payload['translations']['en']['title'] = 'Published Medicine Overview CMS';
        $payload['translations']['en']['summary'] = 'Published medicine overview CMS summary.';
        $payload['translations']['en']['body'] = 'Published medicine overview CMS body.';
        $payload['translations']['ar']['title'] = 'لمحة الطب المنشورة';
        $payload['translations']['ar']['summary'] = 'ملخص لمحة الطب المنشورة.';

        $workflow->saveDraft('facilities.medicine.overview', $payload, (int) $author->id);

        $this->get('/en/facilities/medicine/overview')
            ->assertOk()
            ->assertDontSee('Published Medicine Overview CMS');

        $this->assertTrue($workflow->publish('facilities.medicine.overview', (int) $author->id));

        $this->get('/en/facilities/medicine/overview')
            ->assertOk()
            ->assertSee('Published Medicine Overview CMS')
            ->assertSee('Published medicine overview CMS body.');
    }

    public function test_medicine_overview_subpage_preview_renders_draft_snapshot(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.overview');
        $payload['translations']['en']['title'] = 'Medicine Overview Preview CMS';
        $payload['translations']['en']['body'] = 'Medicine overview preview CMS body.';
        $payload['translations']['en']['sections'][] = [
            'id' => 'preview-overview-section',
            'title' => 'Preview Overview Section',
            'body' => 'Preview overview section body.',
        ];

        $workflow->saveDraft('facilities.medicine.overview', $payload, (int) $author->id);
        $preview = $workflow->preview('facilities.medicine.overview', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Medicine Overview Preview CMS')
            ->assertSee('Medicine overview preview CMS body.')
            ->assertSee('Preview mode');

        $this->get('/en/facilities/medicine/overview')
            ->assertOk()
            ->assertDontSee('Medicine Overview Preview CMS');
    }

    public function test_manage_medicine_faculty_saves_overview_subpage_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.overview')
            ->call('loadTarget', 'facilities.medicine.overview')
            ->assertSee('Subpage Content')
            ->assertSee('Overview Sections')
            ->assertSee('Overview Stats');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $sections = is_array($data['en_content']['sections'] ?? null) ? $data['en_content']['sections'] : [];
        $sections[] = [
            'id' => 'curated-overview-section',
            'title' => 'Curated Overview Section',
            'body' => 'Curated overview section body.',
        ];

        $component
            ->set('data.en_content.title', 'Curated Medicine Overview')
            ->set('data.en_content.body', 'Curated medicine overview body.')
            ->set('data.en_content.sections', $sections)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.medicine.overview')->latest('id')->firstOrFail();
        $sectionTitles = collect($draft->payload_json['translations']['en']['sections'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Medicine Overview', $draft->payload_json['translations']['en']['title'] ?? null);
        $this->assertSame('Curated medicine overview body.', $draft->payload_json['translations']['en']['body'] ?? null);
        $this->assertContains('Curated Overview Section', $sectionTitles);
    }

    public function test_medicine_departments_subpage_can_publish_cms_items(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.departments');
        $payload['translations']['en']['title'] = 'Published Medicine Departments CMS';
        $payload['translations']['en']['items'] = [[
            'slug' => 'curated-department',
            'code' => '99',
            'title' => 'Curated Medicine Department',
            'summary' => 'Curated medicine department summary.',
            'degrees' => 'Curated Degree',
            'tags' => ['Curated Tag'],
        ]];

        $workflow->saveDraft('facilities.medicine.departments', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.medicine.departments', (int) $author->id));

        $this->get('/en/facilities/medicine/departments')
            ->assertOk()
            ->assertSee('Published Medicine Departments CMS')
            ->assertSee('Curated Medicine Department')
            ->assertSee('Curated medicine department summary.');
    }

    public function test_manage_medicine_faculty_uses_page_specific_subpage_templates(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.departments')
            ->call('loadTarget', 'facilities.medicine.departments')
            ->assertSee('Department Directory')
            ->assertSee('Degree / Track')
            ->assertDontSee('Subpage Items');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.labs')
            ->call('loadTarget', 'facilities.medicine.labs')
            ->assertSee('Laboratories')
            ->assertSee('Instructor')
            ->assertDontSee('Degree / Track');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.projects')
            ->call('loadTarget', 'facilities.medicine.projects')
            ->assertSee('Student Projects')
            ->assertSee('Supervisor')
            ->assertSee('Team');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.alumni')
            ->call('loadTarget', 'facilities.medicine.alumni')
            ->assertSee('Alumni Records')
            ->assertSee('Search Records')
            ->assertSee('Year Filter');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.valedictorians')
            ->call('loadTarget', 'facilities.medicine.valedictorians')
            ->assertSee('Honor List Records')
            ->assertSee('Search Records')
            ->assertSee('Year Filter');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.study_plan')
            ->call('loadTarget', 'facilities.medicine.study_plan')
            ->assertSee('Study Plan Labels')
            ->assertSee('Course Page Labels')
            ->assertSee('Elective Pools')
            ->assertSee('Study Plan Tree')
            ->assertSee('Courses In This Term')
            ->assertSee('Opens Course IDs')
            ->assertSee('Lessons');
    }

    public function test_manage_medicine_faculty_filters_alumni_and_valedictorians_inside_curated_workflow(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $alumniPayload = $facilities->getEditablePayload('facilities.medicine.alumni');
        $alumniPayload['translations']['en']['items'] = [
            ['title' => 'First Alumni', 'graduationYear' => '2020', 'department' => 'General Medicine', 'faculty' => 'Medicine', 'degree' => 'MD'],
            ['title' => 'Target Alumni', 'graduationYear' => '2024', 'department' => 'Surgery', 'faculty' => 'Medicine', 'degree' => 'MD'],
        ];
        $alumniPayload['translations']['ar']['items'] = [
            ['title' => 'الخريج الأول', 'graduationYear' => '2020', 'department' => 'الطب العام', 'faculty' => 'الطب', 'degree' => 'طب'],
            ['title' => 'الخريج الهدف', 'graduationYear' => '2024', 'department' => 'الجراحة', 'faculty' => 'الطب', 'degree' => 'طب'],
        ];
        $workflow->saveDraft('facilities.medicine.alumni', $alumniPayload, (int) $author->id);

        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.alumni')
            ->set('data.record_search', 'Target Alumni')
            ->call('loadTarget', 'facilities.medicine.alumni')
            ->assertSee('Search Records')
            ->assertSee('Department / Faculty Filter')
            ->assertSee('Year Filter');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $this->assertCount(1, $data['en_content']['items'] ?? []);
        $this->assertSame('Target Alumni', $data['en_content']['items'][array_key_first($data['en_content']['items'])]['title'] ?? null);

        $visibleKey = array_key_first($data['en_content']['items']);
        $component
            ->set('data.en_content.items.'.$visibleKey.'.title', 'Edited Target Alumni')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.medicine.alumni')->latest('id')->firstOrFail();
        $savedTitles = collect($draft->payload_json['translations']['en']['items'] ?? [])->pluck('title')->all();

        $this->assertCount(2, $savedTitles);
        $this->assertContains('First Alumni', $savedTitles);
        $this->assertContains('Edited Target Alumni', $savedTitles);

        $valedictoriansPayload = $facilities->getEditablePayload('facilities.medicine.valedictorians');
        $valedictoriansPayload['translations']['en']['items'] = [
            ['title' => 'First Honor Student', 'academicYear' => '2022-2023', 'department' => 'General Medicine', 'faculty' => 'Medicine', 'gpa' => '3.80'],
            ['title' => 'Filtered Honor Student', 'academicYear' => '2024-2025', 'department' => 'Surgery', 'faculty' => 'Medicine', 'gpa' => '3.95'],
        ];
        $workflow->saveDraft('facilities.medicine.valedictorians', $valedictoriansPayload, (int) $author->id);

        $honorComponent = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.valedictorians')
            ->set('data.record_department_filter', 'Surgery')
            ->set('data.record_year_filter', '2024')
            ->call('loadTarget', 'facilities.medicine.valedictorians')
            ->assertSee('Honor List Records')
            ->assertSee('Search Records');

        /** @var array<string, mixed> $honorData */
        $honorData = $honorComponent->get('data');
        $this->assertCount(1, $honorData['en_content']['items'] ?? []);
        $this->assertSame('Filtered Honor Student', $honorData['en_content']['items'][array_key_first($honorData['en_content']['items'])]['title'] ?? null);
    }

    public function test_manage_medicine_faculty_does_not_load_filterable_students_until_filtered_and_preserves_hidden_rows_on_add(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.alumni');
        $payload['translations']['en']['items'] = [
            ['title' => 'Existing Alumni One', 'graduationYear' => '2020', 'faculty' => 'Medicine'],
            ['title' => 'Existing Alumni Two', 'graduationYear' => '2021', 'faculty' => 'Medicine'],
        ];
        $payload['translations']['ar']['items'] = [
            ['title' => 'الخريج الأول', 'graduationYear' => '2020', 'faculty' => 'الطب'],
            ['title' => 'الخريج الثاني', 'graduationYear' => '2021', 'faculty' => 'الطب'],
        ];
        $workflow->saveDraft('facilities.medicine.alumni', $payload, (int) $author->id);

        $component = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.alumni')
            ->call('loadTarget', 'facilities.medicine.alumni')
            ->assertSee('Alumni Records')
            ->assertSee('Search Records');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $this->assertSame([], $data['en_content']['items'] ?? null);
        $this->assertSame([], $data['ar_content']['items'] ?? null);

        $component
            ->set('data.en_content.items', [[
                'title' => 'New Alumni From Empty View',
                'graduationYear' => '2026',
                'faculty' => 'Medicine',
            ]])
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.medicine.alumni')->latest('id')->firstOrFail();
        $savedTitles = collect($draft->payload_json['translations']['en']['items'] ?? [])->pluck('title')->all();

        $this->assertCount(3, $savedTitles);
        $this->assertContains('Existing Alumni One', $savedTitles);
        $this->assertContains('Existing Alumni Two', $savedTitles);
        $this->assertContains('New Alumni From Empty View', $savedTitles);
    }

    public function test_public_faculty_student_lists_are_filterable_searchable_and_paginated(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();

        for ($index = 1; $index <= 30; $index++) {
            $alumni = Alumni::query()->create([
                'faculty_id' => (int) $faculty->getKey(),
                'graduation_year' => 2026,
                'is_enabled' => true,
            ]);
            AlumniTranslation::query()->create([
                'alumni_id' => (int) $alumni->getKey(),
                'locale' => 'en',
                'full_name' => 'Imported Alumni '.$index,
            ]);
            AlumniTranslation::query()->create([
                'alumni_id' => (int) $alumni->getKey(),
                'locale' => 'ar',
                'full_name' => 'الخريج المستورد '.$index,
            ]);
            MigrationLog::query()->create([
                'module' => 'alumni',
                'batch_name' => 'test-student-list',
                'source_table' => 'jx_graduated_students',
                'source_id' => $index,
                'target_table' => 'alumni',
                'target_id' => (int) $alumni->getKey(),
                'status' => 'success',
                'message' => 'test',
                'metadata' => ['legacy_section_id' => $index % 2 === 0 ? 2 : 1],
            ]);

            $honorStudent = HonorStudent::query()->create([
                'faculty_id' => (int) $faculty->getKey(),
                'academic_year' => '2026 / '.$index,
                'gpa' => 90.00,
                'sort_order' => $index,
                'is_enabled' => true,
            ]);
            HonorStudentTranslation::query()->create([
                'honor_student_id' => (int) $honorStudent->getKey(),
                'locale' => 'en',
                'full_name' => 'Imported Honor Student '.$index,
            ]);
            HonorStudentTranslation::query()->create([
                'honor_student_id' => (int) $honorStudent->getKey(),
                'locale' => 'ar',
                'full_name' => 'طالب الشرف المستورد '.$index,
            ]);
            MigrationLog::query()->create([
                'module' => 'honor_students',
                'batch_name' => 'test-student-list',
                'source_table' => 'jx_good_students',
                'source_id' => $index,
                'target_table' => 'honor_students',
                'target_id' => (int) $honorStudent->getKey(),
                'status' => 'success',
                'message' => 'test',
                'metadata' => ['legacy_section_id' => $index % 2 === 0 ? 2 : 1],
            ]);
        }

        Cache::flush();

        $facilities = app(FacultyPageServiceInterface::class);
        $alumniPage = $facilities->getSubpage('medicine', 'alumni', 'en');
        $honorPage = $facilities->getSubpage('medicine', 'valedictorians', 'en');
        $secondAlumniPage = $facilities->getSubpage('medicine', 'alumni', 'en', ['page' => 2]);
        $searchedAlumniPage = $facilities->getSubpage('medicine', 'alumni', 'en', ['q' => 'Imported Alumni 30']);
        $firstSemesterHonorPage = $facilities->getSubpage('medicine', 'valedictorians', 'en', ['semester' => 'first']);
        $searchedHonorPage = $facilities->getSubpage('medicine', 'valedictorians', 'en', ['q' => 'Imported Honor Student 30']);

        $this->assertNotNull($alumniPage);
        $this->assertNotNull($honorPage);
        $this->assertSame(24, count($alumniPage->items));
        $this->assertSame(24, count($honorPage->items));
        $this->assertGreaterThan(24, $alumniPage->pagination['total_items']);
        $this->assertGreaterThan(24, $honorPage->pagination['total_items']);
        $this->assertSame(2, $secondAlumniPage?->pagination['current_page']);
        $this->assertSame(['Imported Alumni 30'], collect($searchedAlumniPage?->items ?? [])->pluck('title')->all());
        $this->assertSame(['Imported Honor Student 30'], collect($searchedHonorPage?->items ?? [])->pluck('title')->all());
        $this->assertSame(15, $firstSemesterHonorPage?->pagination['total_items']);
        $this->assertContains('First Semester', collect($firstSemesterHonorPage?->items ?? [])->pluck('semester')->all());
    }

    public function test_medicine_study_plan_preview_renders_cms_labels(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.study_plan');
        $payload['translations']['en']['payload']['labels']['title'] = 'Medicine Study Plan CMS Preview';
        $payload['translations']['en']['payload']['plan']['faculty'] = 'Medicine CMS Plan Faculty';

        $workflow->saveDraft('facilities.medicine.study_plan', $payload, (int) $author->id);
        $preview = $workflow->preview('facilities.medicine.study_plan', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Medicine Study Plan CMS Preview')
            ->assertSee('Medicine CMS Plan Faculty')
            ->assertSee('Preview mode');
    }

    #[DataProvider('remainingFacultyPageProvider')]
    public function test_remaining_faculty_pages_register_required_targets(string $pageClass, string $facultySlug): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test($pageClass)
            ->assertSee('Faculty Identity')
            ->assertSee('Overview Tabs');

        $expectedTargets = [
            'facilities.'.$facultySlug,
            'facilities.'.$facultySlug.'.overview',
            'facilities.'.$facultySlug.'.departments',
            'facilities.'.$facultySlug.'.study_plan',
            'facilities.'.$facultySlug.'.labs',
            'facilities.'.$facultySlug.'.projects',
            'facilities.'.$facultySlug.'.alumni',
            'facilities.'.$facultySlug.'.valedictorians',
        ];

        $reflection = new \ReflectionClass($pageClass);
        $method = $reflection->getMethod('targetOptions');
        $method->setAccessible(true);
        $targetOptions = $method->invoke($reflection->newInstanceWithoutConstructor());

        $this->assertSame($expectedTargets, array_keys($targetOptions));

        $facilities = app(FacultyPageServiceInterface::class);

        foreach ($expectedTargets as $targetKey) {
            $payload = $facilities->getEditablePayload($targetKey);

            $this->assertArrayHasKey('translations', $payload);
        }
    }

    /** @return iterable<string, array{pageClass: class-string, facultySlug: string}> */
    public static function remainingFacultyPageProvider(): iterable
    {
        yield 'dentistry' => [
            'pageClass' => ManageDentistryFaculty::class,
            'facultySlug' => 'dentistry',
        ];

        yield 'pharmacy' => [
            'pageClass' => ManagePharmacyFaculty::class,
            'facultySlug' => 'pharmacy',
        ];

        yield 'building construction engineering' => [
            'pageClass' => ManageBuildingConstructionEngineeringFaculty::class,
            'facultySlug' => 'building-construction-engineering',
        ];

        yield 'petroleum' => [
            'pageClass' => ManagePetroleumFaculty::class,
            'facultySlug' => 'petroleum',
        ];

        yield 'business administration' => [
            'pageClass' => ManageBusinessAdministrationFaculty::class,
            'facultySlug' => 'business-administration',
        ];
    }

    public function test_artificial_intelligence_faculty_workflow_draft_does_not_leak_until_published(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence');
        $payload['translations']['en']['title'] = 'Published AI CMS Homepage';
        $payload['translations']['en']['summary'] = 'Published AI CMS summary.';
        $payload['translations']['en']['faculty']['name'] = 'Published AI Faculty Name';
        $payload['translations']['ar']['title'] = 'صفحة الذكاء الاصطناعي المنشورة';
        $payload['translations']['ar']['summary'] = 'ملخص صفحة الذكاء الاصطناعي المنشورة.';
        $payload['translations']['ar']['faculty']['name'] = 'كلية الذكاء الاصطناعي المنشورة';

        $workflow->saveDraft('facilities.artificial-intelligence', $payload, (int) $author->id);

        $this->get('/en/facilities/artificial-intelligence')
            ->assertOk()
            ->assertDontSee('Published AI CMS Homepage');

        $this->assertTrue($workflow->publish('facilities.artificial-intelligence', (int) $author->id));

        $this->get('/en/facilities/artificial-intelligence')
            ->assertOk()
            ->assertSee('Published AI CMS Homepage')
            ->assertSee('Published AI Faculty Name');
    }

    public function test_artificial_intelligence_projects_subpage_can_publish_cms_items(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.projects');
        $payload['translations']['en']['title'] = 'Published AI Projects CMS';
        $payload['translations']['en']['items'] = [[
            'slug' => 'curated-ai-project',
            'title' => 'Curated AI Project',
            'summary' => 'Curated AI project summary.',
            'tag' => 'Machine Learning',
            'team' => 'AI Student Team',
            'supervisor' => 'AI Supervisor',
            'image' => '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp',
            'detailRoute' => '#curated-ai-project',
        ]];

        $workflow->saveDraft('facilities.artificial-intelligence.projects', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.artificial-intelligence.projects', (int) $author->id));

        $this->get('/en/facilities/artificial-intelligence/projects')
            ->assertOk()
            ->assertSee('Published AI Projects CMS')
            ->assertSee('Curated AI Project')
            ->assertSee('AI Student Team')
            ->assertSee('/en/facilities/artificial-intelligence/projects/curated-ai-project', false);

        $this->get('/en/facilities/artificial-intelligence/projects/curated-ai-project')
            ->assertOk()
            ->assertSee('Curated AI Project')
            ->assertSee('Project Gallery');
    }

    public function test_faculty_project_cards_link_to_project_detail_page(): void
    {
        $this->get('/en/facilities/artificial-intelligence/projects')
            ->assertOk()
            ->assertSee('/en/facilities/artificial-intelligence/projects/artificial-intelligence-project-1', false);
    }

    public function test_faculty_project_detail_page_renders_imported_frontend_layout(): void
    {
        $this->get('/en/facilities/artificial-intelligence/projects/artificial-intelligence-project-1')
            ->assertOk()
            ->assertSee('AI Diagnosis Support for Rural Health Centers')
            ->assertSee('Ahmad Al-Masri')
            ->assertSee('Samar Haddad')
            ->assertSee('TensorFlow')
            ->assertSee('Project Gallery')
            ->assertSee('Related Projects')
            ->assertSee('Smart Traffic Management System')
            ->assertSee('View All Projects');
    }

    #[DataProvider('frontendProjectDetailProvider')]
    public function test_frontend_project_detail_data_is_imported_for_facility(string $facultySlug, string $projectSlug, string $expectedTitle, string $expectedTechnology): void
    {
        $this->get('/en/facilities/'.$facultySlug.'/projects/'.$projectSlug)
            ->assertOk()
            ->assertSee($expectedTitle)
            ->assertSee($expectedTechnology)
            ->assertSee('Project Gallery')
            ->assertSee('Related Projects');
    }

    /** @return iterable<string, array{facultySlug: string, projectSlug: string, expectedTitle: string, expectedTechnology: string}> */
    public static function frontendProjectDetailProvider(): iterable
    {
        yield 'business' => ['business-administration', 'business-administration-project-1', 'Predictive Analytics for Local Economic Trends', 'Tableau'];
        yield 'construction' => ['building-construction-engineering', 'building-construction-engineering-project-1', 'Structural Health Monitoring Dashboard', 'Arduino'];
        yield 'dentistry' => ['dentistry', 'dentistry-project-1', 'Digital Impression Analysis System', 'Open3D'];
        yield 'medicine' => ['medicine', 'medicine-project-1', 'Clinical Appointment Flow Optimizer', 'OptaPlanner'];
        yield 'pharmacy' => ['pharmacy', 'pharmacy-project-1', 'Evidence-Based Learning Repository', 'Elasticsearch'];
    }

    public function test_artificial_intelligence_study_plan_public_payload_is_trimmed_to_active_department(): void
    {
        $response = $this->get('/en/facilities/artificial-intelligence/study-plan');

        $response->assertOk()
            ->assertSee('data-study-plan-payload', false);

        $this->assertLessThan(500_000, strlen((string) $response->getContent()));
    }

    public function test_artificial_intelligence_study_plan_admin_hydrates_selected_department_as_term_tree(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.study_plan');
        $departments = $payload['translations']['en']['payload']['plan']['departments'] ?? [];
        $totalTerms = collect($departments)->sum(fn (array $department): int => count($department['terms'] ?? []));
        $totalCourses = collect($departments)->flatMap(fn (array $department): array => $department['terms'] ?? [])
            ->sum(fn (array $term): int => count($term['courses'] ?? []));

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.study_plan')
            ->call('loadTarget', 'facilities.artificial-intelligence.study_plan')
            ->assertSee('Study Plan Department')
            ->assertSee('Study Plan Tree')
            ->assertSee('Courses In This Term')
            ->assertSee('Prerequisite Course IDs')
            ->assertSee('Opens Course IDs');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $selectedDepartmentId = (string) ($data['study_plan_department_id'] ?? '');
        $options = is_array($data['study_plan_department_options'] ?? null) ? $data['study_plan_department_options'] : [];
        $terms = is_array($data['en_content']['payload']['plan']['terms'] ?? null) ? $data['en_content']['payload']['plan']['terms'] : [];
        $courses = collect($terms)->flatMap(fn (array $term): array => is_array($term['courses'] ?? null) ? $term['courses'] : [])->all();

        $this->assertGreaterThan(1, count($options));
        $this->assertNotSame('', $selectedDepartmentId);
        $this->assertLessThan($totalTerms, count($terms));
        $this->assertLessThan($totalCourses, count($courses));
        $this->assertArrayHasKey('courses', $terms[array_key_first($terms)] ?? []);
        $this->assertArrayHasKey('opensCourseIds', $courses[array_key_first($courses)] ?? []);
    }

    public function test_artificial_intelligence_study_plan_admin_save_preserves_unselected_departments(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.study_plan');
        $baseDepartments = collect($payload['translations']['en']['payload']['plan']['departments'] ?? []);
        $this->assertGreaterThan(1, $baseDepartments->count());

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.study_plan')
            ->call('loadTarget', 'facilities.artificial-intelligence.study_plan');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $selectedDepartmentId = (string) ($data['study_plan_department_id'] ?? '');
        $untouchedDepartment = $baseDepartments->first(fn (array $department): bool => (string) ($department['id'] ?? '') !== $selectedDepartmentId);
        $this->assertIsArray($untouchedDepartment);
        $untouchedDepartmentId = (string) ($untouchedDepartment['id'] ?? '');
        $selectedDepartmentIndex = collect($data['en_content']['payload']['plan']['departments'] ?? [])
            ->search(fn (array $department): bool => (string) ($department['id'] ?? '') === $selectedDepartmentId);

        $this->assertNotFalse($selectedDepartmentIndex);

        $component
            ->set('data.en_content.payload.plan.departments.'.$selectedDepartmentIndex.'.name', 'AI Edited Selected Department')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.artificial-intelligence.study_plan')->latest('id')->firstOrFail();
        $savedDepartments = collect($draft->payload_json['translations']['en']['payload']['plan']['departments'] ?? []);
        $savedSelectedDepartment = $savedDepartments->firstWhere('id', $selectedDepartmentId);
        $savedUntouchedDepartment = $savedDepartments->firstWhere('id', $untouchedDepartmentId);

        $this->assertSame('AI Edited Selected Department', $savedSelectedDepartment['name'] ?? null);
        $this->assertSame($untouchedDepartment['terms'] ?? [], $savedUntouchedDepartment['terms'] ?? []);
    }

    public function test_artificial_intelligence_study_plan_admin_saves_opens_courses_as_prerequisites(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.study_plan')
            ->call('loadTarget', 'facilities.artificial-intelligence.study_plan');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $termKey = array_key_first($data['en_content']['payload']['plan']['terms'] ?? []);
        $this->assertNotNull($termKey);
        $courseKeys = array_keys($data['en_content']['payload']['plan']['terms'][$termKey]['courses'] ?? []);
        $this->assertGreaterThanOrEqual(2, count($courseKeys));
        $sourceKey = $courseKeys[0];
        $targetKey = $courseKeys[1];
        $sourceId = (string) ($data['en_content']['payload']['plan']['terms'][$termKey]['courses'][$sourceKey]['id'] ?? '');
        $targetId = (string) ($data['en_content']['payload']['plan']['terms'][$termKey]['courses'][$targetKey]['id'] ?? '');

        $component
            ->set('data.en_content.payload.plan.terms.'.$termKey.'.courses.'.$sourceKey.'.opensCourseIds', [$targetId])
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.artificial-intelligence.study_plan')->latest('id')->firstOrFail();
        $savedCourses = collect($draft->payload_json['translations']['en']['payload']['plan']['departments'] ?? [])
            ->firstWhere('id', $data['study_plan_department_id'])['terms'] ?? [];
        $targetCourse = collect($savedCourses)
            ->flatMap(fn (array $term): array => $term['courses'] ?? [])
            ->firstWhere('id', $targetId);
        $sourceCourse = collect($savedCourses)
            ->flatMap(fn (array $term): array => $term['courses'] ?? [])
            ->firstWhere('id', $sourceId);

        $this->assertContains($sourceId, $targetCourse['prerequisites'] ?? []);
        $this->assertArrayNotHasKey('opensCourseIds', $sourceCourse);
    }

    public function test_manage_artificial_intelligence_faculty_uses_page_specific_templates(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->assertSee('Faculty Identity')
            ->assertSee('Overview Tabs');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.departments')
            ->call('loadTarget', 'facilities.artificial-intelligence.departments')
            ->assertSee('Department Directory')
            ->assertSee('Degree / Track')
            ->assertDontSee('Subpage Items');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.projects')
            ->call('loadTarget', 'facilities.artificial-intelligence.projects')
            ->assertSee('Student Projects')
            ->assertSee('Supervisor')
            ->assertSee('Team');
    }

    public function test_registered_faculty_subpage_target_hydrates_when_page_shell_is_missing(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $faculty = Faculty::query()->where('public_slug', 'petroleum')->firstOrFail();

        FacultyPage::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('slug', 'projects')
            ->update(['is_enabled' => false]);

        Livewire::test(ManagePetroleumFaculty::class)
            ->set('data.target_key', 'facilities.petroleum.projects')
            ->call('loadTarget', 'facilities.petroleum.projects')
            ->assertSet('data.en_content.title', 'Projects')
            ->assertSee('Student Projects')
            ->assertSee('Supervisor');
    }
}
