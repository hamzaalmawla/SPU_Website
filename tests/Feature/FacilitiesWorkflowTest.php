<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Filament\Pages\ManageArtificialIntelligenceFaculty;
use App\Filament\Pages\ManageFacilities;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('Graduate Name')
            ->assertSee('Graduation year');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.valedictorians')
            ->call('loadTarget', 'facilities.medicine.valedictorians')
            ->assertSee('Honor List Records')
            ->assertSee('Gpa');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.study_plan')
            ->call('loadTarget', 'facilities.medicine.study_plan')
            ->assertSee('Study Plan Labels')
            ->assertSee('Course Page Labels')
            ->assertSee('Elective Pools')
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
            ->assertSee('AI Student Team');
    }

    public function test_artificial_intelligence_study_plan_public_payload_is_trimmed_to_active_department(): void
    {
        $response = $this->get('/en/facilities/artificial-intelligence/study-plan');

        $response->assertOk()
            ->assertSee('data-study-plan-payload', false);

        $this->assertLessThan(500_000, strlen((string) $response->getContent()));
    }

    public function test_artificial_intelligence_study_plan_admin_hydrates_only_selected_department_and_term_graph(): void
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
            ->assertSee('Study Plan Term')
            ->assertSee('Study Plan Courses');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $selectedDepartmentId = (string) ($data['study_plan_department_id'] ?? '');
        $selectedTermId = (string) ($data['study_plan_term_id'] ?? '');
        $options = is_array($data['study_plan_department_options'] ?? null) ? $data['study_plan_department_options'] : [];
        $termOptions = is_array($data['study_plan_term_options'] ?? null) ? $data['study_plan_term_options'] : [];
        $terms = is_array($data['en_content']['payload']['plan']['terms'] ?? null) ? $data['en_content']['payload']['plan']['terms'] : [];
        $courses = is_array($data['en_content']['payload']['plan']['courses'] ?? null) ? $data['en_content']['payload']['plan']['courses'] : [];

        $this->assertGreaterThan(1, count($options));
        $this->assertGreaterThan(1, count($termOptions));
        $this->assertNotSame('', $selectedDepartmentId);
        $this->assertNotSame('', $selectedTermId);
        $this->assertLessThan($totalTerms, count($terms));
        $this->assertLessThan($totalCourses, count($courses));
        $this->assertSame([$selectedDepartmentId], collect($terms)->pluck('departmentId')->unique()->values()->all());
        $this->assertSame([$selectedDepartmentId], collect($courses)->pluck('departmentId')->unique()->values()->all());
        $this->assertSame([$selectedTermId], collect($courses)->pluck('termId')->unique()->values()->map(fn (mixed $id): string => (string) $id)->all());
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
        $selectedTermId = (string) ($data['study_plan_term_id'] ?? '');
        $untouchedDepartment = $baseDepartments->first(fn (array $department): bool => (string) ($department['id'] ?? '') !== $selectedDepartmentId);
        $selectedBaseDepartment = $baseDepartments->firstWhere('id', $selectedDepartmentId);
        $untouchedSelectedDepartmentTerm = collect(is_array($selectedBaseDepartment) ? ($selectedBaseDepartment['terms'] ?? []) : [])
            ->first(fn (array $term): bool => (string) ($term['id'] ?? '') !== $selectedTermId);
        $this->assertIsArray($untouchedDepartment);
        $this->assertIsArray($untouchedSelectedDepartmentTerm);
        $untouchedDepartmentId = (string) ($untouchedDepartment['id'] ?? '');
        $untouchedSelectedDepartmentTermId = (string) ($untouchedSelectedDepartmentTerm['id'] ?? '');
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
        $savedUntouchedSelectedDepartmentTerm = collect($savedSelectedDepartment['terms'] ?? [])->firstWhere('id', $untouchedSelectedDepartmentTermId);

        $this->assertSame('AI Edited Selected Department', $savedSelectedDepartment['name'] ?? null);
        $this->assertSame($untouchedDepartment['terms'] ?? [], $savedUntouchedDepartment['terms'] ?? []);
        $this->assertSame($untouchedSelectedDepartmentTerm['courses'] ?? [], $savedUntouchedSelectedDepartmentTerm['courses'] ?? []);
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
}
