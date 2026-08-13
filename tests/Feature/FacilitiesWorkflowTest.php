<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Research\ResearchPageDTO;
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
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyLabTranslation;
use App\Models\Faculty\FacultyPage;
use App\Models\Media\MediaAsset;
use App\Models\Person\Person;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function test_english_student_lists_fall_back_to_original_arabic_names(): void
    {
        $faculty = Faculty::query()->where('slug', 'medicine')->firstOrFail();
        $alumni = Alumni::query()->create([
            'faculty_id' => $faculty->getKey(), 'graduation_year' => 2024,
            'is_featured' => false, 'is_enabled' => true,
        ]);
        AlumniTranslation::query()->create([
            'alumni_id' => $alumni->getKey(), 'locale' => 'ar', 'full_name' => 'خريج عربي فقط',
        ]);
        $honor = HonorStudent::query()->create([
            'faculty_id' => $faculty->getKey(), 'academic_year' => '2024 / 1',
            'sort_order' => 1, 'is_enabled' => true,
        ]);
        HonorStudentTranslation::query()->create([
            'honor_student_id' => $honor->getKey(), 'locale' => 'ar', 'full_name' => 'متفوق عربي فقط',
        ]);
        $service = app(FacultyPageServiceInterface::class);

        $arabicAlumni = $service->getSubpage('medicine', 'alumni', 'ar');
        $englishAlumni = $service->getSubpage('medicine', 'alumni', 'en');
        $arabicHonor = $service->getSubpage('medicine', 'valedictorians', 'ar');
        $englishHonor = $service->getSubpage('medicine', 'valedictorians', 'en');

        $this->assertContains('خريج عربي فقط', array_column($arabicAlumni?->items ?? [], 'title'));
        $this->assertContains('خريج عربي فقط', array_column($englishAlumni?->items ?? [], 'title'));
        $this->assertContains('متفوق عربي فقط', array_column($arabicHonor?->items ?? [], 'title'));
        $this->assertContains('متفوق عربي فقط', array_column($englishHonor?->items ?? [], 'title'));
    }

    public function test_honor_student_preview_defaults_missing_filters(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $targetKey = 'facilities.artificial-intelligence.valedictorians';
        $payload = $facilities->getEditablePayload($targetKey);
        $payload['translations']['ar']['title'] = 'معاينة قائمة شرف الذكاء الاصطناعي';

        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());
        $preview = $workflow->preview($targetKey, 'ar', (int) $author->getKey());

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('معاينة قائمة شرف الذكاء الاصطناعي');
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
            ->assertSee('مقدمة مركز الكليات')
            ->assertSee('الأرقام والمعلومات السريعة')
            ->assertSee('بطاقات الكليات')
            ->assertSee('النموذج الأكاديمي');

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

    public function test_published_facilities_hub_renders_cms_buttons_and_featured_model_card(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.landing');
        $links = $payload['translations']['en']['facultyLinks'];
        $firstLink = $links[0];
        $secondLink = $links[1];

        $links[0] = array_merge($secondLink, [
            'title' => 'CMS Dentistry First',
            'summary' => 'CMS dentistry summary.',
            'accentColor' => '#123456',
        ]);
        $links[1] = array_merge($firstLink, [
            'title' => 'CMS Medicine Second',
            'summary' => 'CMS medicine summary.',
            'accentColor' => '#654321',
        ]);
        $payload['translations']['en']['facultyLinks'] = $links;

        foreach ($payload['translations']['en']['model']['cards'] as $index => $card) {
            $payload['translations']['en']['model']['cards'][$index]['featured'] = $index === 1;
        }

        $workflow->saveDraft('facilities.landing', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.landing', (int) $author->id));

        $this->get('/en/facilities')
            ->assertOk()
            ->assertSeeInOrder(['CMS Dentistry First', 'CMS Medicine Second'])
            ->assertSee('CMS dentistry summary.')
            ->assertSee('--accent: #123456;', false)
            ->assertSee('href="#faculties-overview"', false)
            ->assertSee('id="faculties-overview"', false)
            ->assertDontSee('#faculties-explorer', false)
            ->assertSeeInOrder([
                'data-featured="false"',
                'Clinical Learning',
                'data-featured="true"',
                'Applied Education',
            ], false)
            ->assertSee('bg-[#1e2652] text-white', false);
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
            ->assertSee('محتوى الصفحة')
            ->assertSee('بيانات الكلية وصورها')
            ->assertSee('أقسام التعريف بالكلية')
            ->assertDontSee('Body Sections')
            ->assertSee('كلمة العميد')
            ->assertSee('أحدث الأبحاث');

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
            ->assertSee('مقدمة الصفحة')
            ->assertSee('محتوى النظرة العامة')
            ->assertSee('الإحصاءات');

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

    public function test_department_study_plan_mapping_survives_preview_and_publication(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.departments');

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['items'] = [[
                'slug' => 'mapped-medicine',
                'code' => '01',
                'title' => $locale === 'ar' ? 'برنامج الطب البشري' : 'Medicine Program',
                'summary' => $locale === 'ar' ? 'قسم مرتبط بالخطة.' : 'A department linked to its plan.',
                'degrees' => $locale === 'ar' ? 'بكالوريوس' : 'Bachelor',
                'tags' => [],
                'studyPlanDepartmentId' => 'medicine-plan',
            ]];
        }

        $workflow->saveDraft('facilities.medicine.departments', $payload, (int) $author->id);
        $preview = $workflow->preview('facilities.medicine.departments', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('href="/en/facilities/medicine/study-plan?department=medicine-plan"', false);

        $this->assertTrue($workflow->publish('facilities.medicine.departments', (int) $author->id));

        $this->get('/en/facilities/medicine/departments')
            ->assertOk()
            ->assertSee('href="/en/facilities/medicine/study-plan?department=medicine-plan"', false);
    }

    public function test_departments_reject_draft_only_study_plan_ids(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $studyPlanPayload = $facilities->getEditablePayload('facilities.medicine.study_plan');

        foreach (['ar', 'en'] as $locale) {
            $studyPlanPayload['translations'][$locale]['payload']['plan']['departments'][0]['id'] = 'future-only-plan';
        }

        $workflow->saveDraft('facilities.medicine.study_plan', $studyPlanPayload, (int) $author->id);
        $departmentsPayload = $facilities->getEditablePayload('facilities.medicine.departments');

        foreach (['ar', 'en'] as $locale) {
            $departmentsPayload['translations'][$locale]['items'][0]['studyPlanDepartmentId'] = 'future-only-plan';
        }

        $readiness = $workflow->readiness('facilities.medicine.departments', $departmentsPayload);

        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('ar', $readiness->errors);
        $this->assertArrayHasKey('en', $readiness->errors);
    }

    public function test_study_plan_publish_cannot_break_published_department_mappings(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $departmentsPayload = $facilities->getEditablePayload('facilities.medicine.departments');

        foreach (['ar', 'en'] as $locale) {
            $departmentsPayload['translations'][$locale]['items'] = [[
                'slug' => 'medicine-program',
                'title' => $locale === 'ar' ? 'برنامج الطب البشري' : 'Medicine Program',
                'summary' => $locale === 'ar' ? 'برنامج منشور.' : 'Published program.',
                'studyPlanDepartmentId' => 'medicine-plan',
            ]];
        }

        $workflow->saveDraft('facilities.medicine.departments', $departmentsPayload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.medicine.departments', (int) $author->id));

        $studyPlanPayload = $facilities->getEditablePayload('facilities.medicine.study_plan');
        foreach (['ar', 'en'] as $locale) {
            $studyPlanPayload['translations'][$locale]['payload']['plan']['departments'][0]['id'] = 'replacement-plan';
        }

        $readiness = $workflow->readiness('facilities.medicine.study_plan', $studyPlanPayload);
        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('ar', $readiness->errors);
        $this->assertArrayHasKey('en', $readiness->errors);
    }

    public function test_study_plan_ids_must_exist_in_both_locales(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.medicine.study_plan');
        $payload['translations']['en']['payload']['plan']['departments'][0]['id'] = 'english-only-plan';

        $readiness = $workflow->readiness('facilities.medicine.study_plan', $payload);

        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('translations', $readiness->errors);
    }

    public function test_ambiguous_department_names_do_not_generate_guessed_study_plan_links(): void
    {
        $this->get('/en/facilities/artificial-intelligence/departments')
            ->assertOk()
            ->assertSee('href="/en/facilities/artificial-intelligence/study-plan"', false)
            ->assertDontSee('/study-plan?department=', false);
    }

    public function test_study_plan_language_switch_preserves_only_valid_department_selection(): void
    {
        $this->get('/en/facilities/artificial-intelligence/study-plan?department=ai')
            ->assertOk()
            ->assertSee('/ar/facilities/artificial-intelligence/study-plan?department=ai', false)
            ->assertSee('aria-pressed="true"', false);

        $this->get('/en/facilities/artificial-intelligence/study-plan?department=unknown')
            ->assertNotFound();
    }

    public function test_faculty_editor_is_restricted_to_assigned_faculty_targets(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $this->actingAs($facultyEditor, 'web');
        $this->assertTrue(ManageMedicineFaculty::canAccess());
        $this->assertFalse(ManagePharmacyFaculty::canAccess());

        $this->expectException(AuthorizationException::class);
        $workflow->latestEditableDraftPayload('facilities.pharmacy.departments', (int) $facultyEditor->id);
    }

    public function test_manage_medicine_faculty_uses_page_specific_subpage_templates(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.departments')
            ->call('loadTarget', 'facilities.medicine.departments')
            ->assertSee('الأقسام الأكاديمية')
            ->assertSee('الدرجة أو المسار')
            ->assertDontSee('Subpage Items');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.labs')
            ->call('loadTarget', 'facilities.medicine.labs')
            ->assertSee('المخابر')
            ->assertSee('المشرف أو المدرس')
            ->assertDontSee('الدرجة أو المسار');

        $projectEditor = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.projects')
            ->call('loadTarget', 'facilities.medicine.projects')
            ->assertSee('مشاريع الطلاب')
            ->assertSee('المشرف')
            ->assertSee('فريق العمل')
            ->assertSee('وصف المشروع')
            ->assertSee('التقنيات')
            ->assertSee('أعضاء الفريق');

        /** @var array<string, mixed> $projectEditorData */
        $projectEditorData = $projectEditor->get('data');
        $firstProjectKey = array_key_first($projectEditorData['en_content']['items'] ?? []);
        $this->assertNotNull($firstProjectKey);
        $projectEditor
            ->set('data.en_content.items.'.$firstProjectKey.'.longDescription', [['paragraph' => 'Description persists after reload.']])
            ->call('save');

        $projectDraft = CmsDraft::query()->where('target_key', 'facilities.medicine.projects')->latest('id')->firstOrFail();
        $savedLongDescription = $projectDraft->payload_json['translations']['en']['items'][0]['longDescription'] ?? null;
        $this->assertSame(['Description persists after reload.'], $savedLongDescription);

        /** @var array<string, mixed> $reloadedProjectEditorData */
        $reloadedProjectEditorData = Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.projects')
            ->call('loadTarget', 'facilities.medicine.projects')
            ->get('data');
        $reloadedFirstProjectKey = array_key_first($reloadedProjectEditorData['en_content']['items'] ?? []);
        $reloadedDescriptionKey = array_key_first($reloadedProjectEditorData['en_content']['items'][$reloadedFirstProjectKey]['longDescription'] ?? []);
        $this->assertSame(
            'Description persists after reload.',
            $reloadedProjectEditorData['en_content']['items'][$reloadedFirstProjectKey]['longDescription'][$reloadedDescriptionKey]['paragraph'] ?? null,
        );

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.alumni')
            ->call('loadTarget', 'facilities.medicine.alumni')
            ->assertSee('الخريجون')
            ->assertSee('البحث في السجلات')
            ->assertSee('السنة');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.valedictorians')
            ->call('loadTarget', 'facilities.medicine.valedictorians')
            ->assertSee('الطلبة الأوائل')
            ->assertSee('البحث في السجلات')
            ->assertSee('السنة');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.study_plan')
            ->call('loadTarget', 'facilities.medicine.study_plan')
            ->assertSee('نصوص واجهة الخطة الدراسية')
            ->assertSee('نصوص صفحة المقرر')
            ->assertSee('مجموعات المقررات الاختيارية')
            ->assertSee('مساحة عمل الفصل باللغتين')
            ->assertSee('مقررات هذا الفصل')
            ->assertDontSee('Opens Course IDs')
            ->assertSee('المحاضرات والملفات');
    }

    public function test_published_faculty_project_cms_content_overrides_reference_detail_fallback_in_both_locales(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.projects');
        $projectSlug = (string) ($payload['translations']['en']['items'][0]['slug'] ?? '');

        $payload['translations']['en']['items'][0] = [
            ...$payload['translations']['en']['items'][0],
            'title' => 'CMS Medicine Project',
            'summary' => 'CMS medicine project summary.',
            'academicYear' => '2026 / 2027',
            'status' => 'In progress',
            'createdBy' => 'CMS Student',
            'longDescription' => ['CMS project paragraph.'],
            'gallery' => ['/images/campus-feature-01.webp'],
            'technologies' => ['CMS Technology'],
            'teamMembers' => [['name' => 'CMS Team Member', 'role' => 'Developer']],
        ];
        $payload['translations']['ar']['items'][0] = [
            ...$payload['translations']['ar']['items'][0],
            'title' => 'مشروع طب CMS',
            'summary' => 'ملخص مشروع طب CMS.',
            'academicYear' => '2026 / 2027',
            'status' => 'قيد التنفيذ',
            'createdBy' => 'طالب CMS',
            'longDescription' => ['فقرة مشروع CMS.'],
            'gallery' => ['/images/campus-feature-01.webp'],
            'technologies' => ['تقنية CMS'],
            'teamMembers' => [['name' => 'عضو فريق CMS', 'role' => 'مطور']],
        ];

        $workflow->saveDraft('facilities.medicine.projects', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.medicine.projects', (int) $author->id));
        Cache::flush();

        $this->get('/en/facilities/medicine/projects/'.$projectSlug)
            ->assertOk()
            ->assertSee('CMS Medicine Project')
            ->assertSee('CMS project paragraph.')
            ->assertSee('CMS Technology')
            ->assertSee('In progress')
            ->assertSee('2026 / 2027');

        $this->get('/ar/facilities/medicine/projects/'.$projectSlug)
            ->assertOk()
            ->assertSee('مشروع طب CMS')
            ->assertSee('فقرة مشروع CMS.')
            ->assertSee('تقنية CMS')
            ->assertSee('قيد التنفيذ');
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
            ->assertSee('البحث في السجلات')
            ->assertSee('الكلية أو القسم')
            ->assertSee('السنة');

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
            ->assertSee('الطلبة الأوائل')
            ->assertSee('البحث في السجلات');

        /** @var array<string, mixed> $honorData */
        $honorData = $honorComponent->get('data');
        $this->assertCount(1, $honorData['en_content']['items'] ?? []);
        $this->assertSame('Filtered Honor Student', $honorData['en_content']['items'][array_key_first($honorData['en_content']['items'])]['title'] ?? null);
    }

    public function test_student_editor_pairs_locales_and_deleting_a_filtered_row_publishes(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $targetKey = 'facilities.artificial-intelligence.valedictorians';
        $payload = $facilities->getEditablePayload($targetKey);
        $payload['translations']['en']['items'] = [
            ['title' => 'Keep Student', 'academicYear' => '2026', 'department' => 'Computer Science', 'faculty' => 'AI Engineering', 'gpa' => '3.80', 'image' => '/images/slider-1.webp'],
            ['title' => 'Remove Student', 'academicYear' => '2025', 'department' => 'Robotics', 'faculty' => 'AI Engineering', 'gpa' => '3.95', 'image' => '/images/slider-2.webp'],
        ];
        $payload['translations']['ar']['items'] = [
            ['title' => 'الطالب الباقي', 'academicYear' => '2026', 'department' => 'علوم الحاسوب', 'faculty' => 'هندسة الذكاء الاصطناعي', 'gpa' => '3.80', 'image' => '/images/slider-1.webp'],
            ['title' => 'الطالب المحذوف', 'academicYear' => '2025', 'department' => 'الروبوتات', 'faculty' => 'هندسة الذكاء الاصطناعي', 'gpa' => '3.95', 'image' => '/images/slider-2.webp'],
        ];

        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', $targetKey)
            ->set('data.record_search', 'Remove Student')
            ->call('loadTarget', $targetKey)
            ->assertSee('اسم الطالب (بالعربية)');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $this->assertCount(1, $data['student_records'] ?? []);
        $recordKey = array_key_first($data['student_records']);
        $this->assertSame('Remove Student', $data['student_records'][$recordKey]['titleEn'] ?? null);
        $this->assertSame('الطالب المحذوف', $data['student_records'][$recordKey]['titleAr'] ?? null);

        $component
            ->set('data.student_records.'.$recordKey.'.image', '/images/slider-3.webp')
            ->set('data.student_records.'.$recordKey.'.titleEn', 'Updated Remove Student')
            ->set('data.student_records.'.$recordKey.'.titleAr', 'طالب محذوف محدث')
            ->call('save');

        $editedDraft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
        $editedEnglish = collect($editedDraft->payload_json['translations']['en']['items'] ?? [])->firstWhere('title', 'Updated Remove Student');
        $editedArabic = collect($editedDraft->payload_json['translations']['ar']['items'] ?? [])->firstWhere('title', 'طالب محذوف محدث');
        $this->assertSame('/images/slider-3.webp', $editedEnglish['image'] ?? null);
        $this->assertSame('/images/slider-3.webp', $editedArabic['image'] ?? null);

        $component
            ->set('data.student_records', [])
            ->call('save');

        $deletedDraft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
        $this->assertSame(['Keep Student'], collect($deletedDraft->payload_json['translations']['en']['items'] ?? [])->pluck('title')->all());
        $this->assertSame(['الطالب الباقي'], collect($deletedDraft->payload_json['translations']['ar']['items'] ?? [])->pluck('title')->all());

        $component->call('publish');

        $this->get('/en/facilities/artificial-intelligence/valedictorians')
            ->assertOk()
            ->assertDontSee('Remove Student')
            ->assertSee('Keep Student');

        $this->get('/en/facilities/artificial-intelligence/valedictorians?q=Remove%20Student')
            ->assertOk()
            ->assertSee('Showing 0-0 of 0');
    }

    public function test_student_media_uses_host_relative_webp_url_and_new_row_is_not_duplicated(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $targetKey = 'facilities.artificial-intelligence.valedictorians';
        $payload = $facilities->getEditablePayload($targetKey);
        $payload['translations']['en']['items'] = [];
        $payload['translations']['ar']['items'] = [];
        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', $targetKey)
            ->call('loadTarget', $targetKey);

        $component
            ->set('data.student_records', [[
                '_cmsKey' => 'record-new-student',
                'titleEn' => 'New Student',
                'titleAr' => 'طالب جديد',
                'academicYear' => '2026',
                'gpa' => '3.90',
                'image' => '/storage/media/image/2026/08/student.webp',
            ]])
            ->set('data.student_record_keys', ['record-new-student'])
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
        $english = $draft->payload_json['translations']['en']['items'] ?? [];
        $arabic = $draft->payload_json['translations']['ar']['items'] ?? [];

        $this->assertCount(1, $english);
        $this->assertCount(1, $arabic);
        $this->assertSame('/storage/media/image/2026/08/student.webp', $english[0]['image'] ?? null);
        $this->assertSame('/storage/media/image/2026/08/student.webp', $arabic[0]['image'] ?? null);
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
            ->assertSee('الخريجون')
            ->assertSee('البحث في السجلات');

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
        $secondHonorPage = $facilities->getSubpage('medicine', 'valedictorians', 'en', ['page' => 2]);
        $searchedAlumniPage = $facilities->getSubpage('medicine', 'alumni', 'en', ['q' => 'Imported Alumni 30']);
        $searchedHonorPage = $facilities->getSubpage('medicine', 'valedictorians', 'en', ['q' => 'Imported Honor Student 30']);

        $this->assertNotNull($alumniPage);
        $this->assertNotNull($honorPage);
        $this->assertSame(12, count($alumniPage->items));
        $this->assertSame(6, count($honorPage->items));
        $this->assertGreaterThan(12, $alumniPage->pagination['total_items']);
        $this->assertGreaterThan(6, $honorPage->pagination['total_items']);
        $this->assertSame(2, $secondAlumniPage?->pagination['current_page']);
        $this->assertSame(2, $secondHonorPage?->pagination['current_page']);
        $this->assertSame(['Imported Alumni 30'], collect($searchedAlumniPage?->items ?? [])->pluck('title')->all());
        $this->assertSame(['Imported Honor Student 30'], collect($searchedHonorPage?->items ?? [])->pluck('title')->all());
        $this->assertSame([null], collect($alumniPage->items)->pluck('semester')->unique()->values()->all());
        $this->assertSame([null], collect($honorPage->items)->pluck('semester')->unique()->values()->all());
    }

    public function test_all_department_routes_are_localized_canonical_and_keep_alias_deep_links(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $facultyAliases = [
            'artificial-intelligence' => 'ai-engineering',
            'business-administration' => 'business',
            'building-construction-engineering' => 'Construction',
            'dentistry' => 'dentistry',
            'medicine' => 'medicine',
            'petroleum' => 'petroleum',
            'pharmacy' => 'pharmacy',
        ];

        foreach ($facultyAliases as $facultySlug => $legacySlug) {
            foreach (['ar', 'en'] as $locale) {
                $path = '/'.$locale.'/facilities/'.$facultySlug.'/departments';
                $page = $facilities->getSubpage($facultySlug, 'departments', $locale);
                $this->assertNotNull($page);
                $firstDepartment = $page->items[0] ?? null;
                $this->assertIsArray($firstDepartment);

                $this->get($path)
                    ->assertOk()
                    ->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false)
                    ->assertSee('id="'.$firstDepartment['slug'].'"', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'">', false)
                    ->assertSee('hreflang="'.($locale === 'ar' ? 'en' : 'ar').'"', false)
                    ->assertDontSee('Mock department data');

                $this->get('/'.$locale.'/facilities/'.$facultySlug.'/departments/index.html?source=reference')
                    ->assertStatus(301)
                    ->assertRedirect($path.'?source=reference');

                $this->get('/'.$locale.'/faculties/'.$legacySlug.'/departments?source=legacy')
                    ->assertStatus(301)
                    ->assertRedirect($path.'?source=legacy');
            }
        }
    }

    public function test_all_student_directories_use_reference_page_sizes_and_preserve_validated_query_state(): void
    {
        $sourceId = 50_000;

        foreach (Faculty::query()->whereIn('public_slug', $this->facultySlugs())->get() as $faculty) {
            for ($index = 1; $index <= 13; $index++) {
                $alumni = Alumni::query()->create([
                    'faculty_id' => (int) $faculty->getKey(),
                    'graduation_year' => 2026,
                    'is_enabled' => true,
                ]);

                foreach (['ar', 'en'] as $locale) {
                    AlumniTranslation::query()->create([
                        'alumni_id' => (int) $alumni->getKey(),
                        'locale' => $locale,
                        'full_name' => 'Parity '.$faculty->public_slug.' Alumni '.$index,
                    ]);
                }

                MigrationLog::query()->create([
                    'module' => 'alumni',
                    'batch_name' => 'faculty-directory-parity',
                    'source_table' => 'jx_graduated_students',
                    'source_id' => $sourceId++,
                    'target_table' => 'alumni',
                    'target_id' => (int) $alumni->getKey(),
                    'status' => 'success',
                    'message' => 'test',
                    'metadata' => ['legacy_section_id' => 1],
                ]);
            }

            for ($index = 1; $index <= 7; $index++) {
                $student = HonorStudent::query()->create([
                    'faculty_id' => (int) $faculty->getKey(),
                    'academic_year' => '2026 / 2027',
                    'gpa' => 95,
                    'sort_order' => 100 + $index,
                    'is_enabled' => true,
                ]);

                foreach (['ar', 'en'] as $locale) {
                    HonorStudentTranslation::query()->create([
                        'honor_student_id' => (int) $student->getKey(),
                        'locale' => $locale,
                        'full_name' => 'Parity '.$faculty->public_slug.' Honor '.$index,
                    ]);
                }

                MigrationLog::query()->create([
                    'module' => 'honor_students',
                    'batch_name' => 'faculty-directory-parity',
                    'source_table' => 'jx_good_students',
                    'source_id' => $sourceId++,
                    'target_table' => 'honor_students',
                    'target_id' => (int) $student->getKey(),
                    'status' => 'success',
                    'message' => 'test',
                    'metadata' => ['legacy_section_id' => 1],
                ]);
            }
        }

        Cache::flush();
        $facilities = app(FacultyPageServiceInterface::class);

        foreach ($this->facultySlugs() as $facultySlug) {
            foreach (['ar', 'en'] as $locale) {
                foreach (['alumni' => 12, 'valedictorians' => 6] as $subpage => $perPage) {
                    $search = 'Parity '.$facultySlug;
                    $filters = ['q' => $search, 'year' => '2026', 'semester' => 'first'];
                    $firstPage = $facilities->getSubpage($facultySlug, $subpage, $locale, [...$filters, 'page' => 1]);
                    $secondPage = $facilities->getSubpage($facultySlug, $subpage, $locale, [...$filters, 'page' => 2]);
                    $boundedPage = $facilities->getSubpage($facultySlug, $subpage, $locale, [...$filters, 'page' => 999]);

                    $this->assertSame($perPage, count($firstPage?->items ?? []));
                    $this->assertSame(1, count($secondPage?->items ?? []));
                    $this->assertSame(2, $boundedPage?->pagination['current_page']);

                    foreach ([1, 2, 999] as $requestedPage) {
                        $canonicalPage = $requestedPage === 999 ? 2 : $requestedPage;
                        $query = http_build_query([...$filters, 'page' => $requestedPage], '', '&', PHP_QUERY_RFC3986);
                        $canonicalQuery = http_build_query([...$filters, 'page' => $canonicalPage], '', '&', PHP_QUERY_RFC3986);
                        $path = '/'.$locale.'/facilities/'.$facultySlug.'/'.$subpage;
                        $otherLocale = $locale === 'ar' ? 'en' : 'ar';

                        $this->get($path.'?'.$query)
                            ->assertOk()
                            ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?'.e($canonicalQuery).'">', false)
                            ->assertSee('/'.$otherLocale.'/facilities/'.$facultySlug.'/'.$subpage.'?'.e($canonicalQuery), false);
                    }
                }
            }
        }
    }

    public function test_alumni_and_honor_students_are_sorted_by_newest_academic_year_before_pagination(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();

        foreach ([2030 => 'Older Alumni', 2031 => 'Newest Alumni'] as $year => $name) {
            $alumni = Alumni::query()->create([
                'faculty_id' => $faculty->getKey(),
                'graduation_year' => $year,
                'is_enabled' => true,
            ]);
            $alumni->translations()->createMany([
                ['locale' => 'ar', 'full_name' => $name],
                ['locale' => 'en', 'full_name' => $name],
            ]);
        }

        foreach ([2030 => ['Older Honor', 1], 2031 => ['Newest Honor', 999]] as $year => [$name, $sortOrder]) {
            $student = HonorStudent::query()->create([
                'faculty_id' => $faculty->getKey(),
                'academic_year' => $year.' / '.($year + 1),
                'gpa' => 95,
                'sort_order' => $sortOrder,
                'is_enabled' => true,
            ]);
            $student->translations()->createMany([
                ['locale' => 'ar', 'full_name' => $name],
                ['locale' => 'en', 'full_name' => $name],
            ]);
        }

        Cache::flush();
        $facilities = app(FacultyPageServiceInterface::class);

        $this->assertSame('Newest Alumni', $facilities->getSubpage('medicine', 'alumni', 'en')?->items[0]['title']);
        $this->assertSame('Newest Honor', $facilities->getSubpage('medicine', 'valedictorians', 'en')?->items[0]['title']);
    }

    public function test_student_directory_media_and_quote_render_only_from_safe_managed_content(): void
    {
        $media = MediaAsset::query()->create([
            'disk' => 'public',
            'filename' => 'student-photo.jpg',
            'original_name' => 'student-photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'path' => 'faculty/student-photo.jpg',
        ]);
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $alumni = Alumni::query()->where('faculty_id', $faculty->getKey())->firstOrFail();
        $student = HonorStudent::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'academic_year' => '2026-2027',
            'gpa' => 95,
            'photo_media_id' => $media->getKey(),
            'is_enabled' => true,
        ]);
        HonorStudentTranslation::query()->create([
            'honor_student_id' => (int) $student->getKey(),
            'locale' => 'en',
            'full_name' => 'Managed Media Honor Student',
        ]);
        HonorStudentTranslation::query()->create([
            'honor_student_id' => (int) $student->getKey(),
            'locale' => 'ar',
            'full_name' => 'طالب شرف بصورة مدارة',
        ]);
        $alumni->update(['photo_media_id' => $media->getKey()]);
        Cache::flush();

        $this->get('/en/facilities/medicine/alumni')
            ->assertOk()
            ->assertSee('/storage/faculty/student-photo.jpg', false)
            ->assertDontSee('/images/unkown.jpeg', false);
        $this->get('/en/facilities/medicine/valedictorians')
            ->assertOk()
            ->assertSee('/storage/faculty/student-photo.jpg', false)
            ->assertDontSee('/images/unkown.jpeg', false);

        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.valedictorians');

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['quote'] = 'Managed <script>alert("quote")</script>';
            $payload['translations'][$locale]['items'] = [[
                'title' => 'Managed Honor Student',
                'academicYear' => '2026',
                'gpa' => '3.95',
                'image' => 'javascript:alert(1)',
            ]];
        }

        $workflow->saveDraft('facilities.medicine.valedictorians', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.medicine.valedictorians', (int) $author->id));

        $this->get('/en/facilities/medicine/valedictorians')
            ->assertOk()
            ->assertSee('Managed &lt;script&gt;alert(&quot;quote&quot;)&lt;/script&gt;', false)
            ->assertDontSee('src="javascript:', false)
            ->assertDontSee('/images/unkown.jpeg', false);
    }

    public function test_six_project_routes_have_bounded_server_pagination_and_localized_links(): void
    {
        foreach ($this->projectFacultySlugs() as $facultySlug) {
            foreach (['ar', 'en'] as $locale) {
                $path = '/'.$locale.'/facilities/'.$facultySlug.'/projects';

                $this->get($path.'?page=1')
                    ->assertOk()
                    ->assertSee('id="'.$facultySlug.'-project-1"', false)
                    ->assertDontSee('id="'.$facultySlug.'-project-7"', false)
                    ->assertSee('href="'.$path.'?page=2"', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?page=1">', false);

                $otherLocale = $locale === 'ar' ? 'en' : 'ar';
                $this->get($path.'?page=2')
                    ->assertOk()
                    ->assertSee('id="'.$facultySlug.'-project-7"', false)
                    ->assertDontSee('id="'.$facultySlug.'-project-1"', false)
                    ->assertSee('/'.$otherLocale.'/facilities/'.$facultySlug.'/projects?page=2', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?page=2">', false);

                $this->get($path.'?page=999')
                    ->assertOk()
                    ->assertSee('id="'.$facultySlug.'-project-7"', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?page=2">', false);
            }
        }
    }

    public function test_six_lab_routes_paginate_and_resolve_full_collection_details_with_related_labs(): void
    {
        foreach (Faculty::query()->whereIn('public_slug', $this->labFacultySlugs())->get() as $faculty) {
            for ($index = 1; $index <= 7; $index++) {
                $lab = FacultyLab::query()->create([
                    'faculty_id' => (int) $faculty->getKey(),
                    'slug' => 'parity-'.$faculty->public_slug.'-lab-'.$index,
                    'image' => '/images/slider-3.webp',
                    'sort_order' => 100 + $index,
                    'is_enabled' => true,
                ]);

                foreach (['ar', 'en'] as $locale) {
                    FacultyLabTranslation::query()->create([
                        'faculty_lab_id' => (int) $lab->getKey(),
                        'locale' => $locale,
                        'title' => 'Parity '.$faculty->public_slug.' Lab '.$index,
                        'department' => 'Parity Department',
                        'instructor' => 'Parity Instructor',
                        'description' => 'Parity lab description.',
                    ]);
                }
            }
        }

        Cache::flush();

        foreach ($this->labFacultySlugs() as $facultySlug) {
            foreach (['ar', 'en'] as $locale) {
                $path = '/'.$locale.'/facilities/'.$facultySlug.'/labs';
                $detailSlug = 'parity-'.$facultySlug.'-lab-7';

                $this->get($path.'?page=1')
                    ->assertOk()
                    ->assertSee('href="'.$path.'?page=2"', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?page=1">', false);

                $this->get($path.'?page=2')
                    ->assertOk()
                    ->assertSee('Parity '.$facultySlug.' Lab', false)
                    ->assertSee('aria-current="page"', false);

                $otherLocale = $locale === 'ar' ? 'en' : 'ar';
                $this->get($path.'?lab='.$detailSlug.'&page=2')
                    ->assertOk()
                    ->assertSee('Parity '.$facultySlug.' Lab 7')
                    ->assertSee($locale === 'ar' ? 'مخابر ذات صلة' : 'Related Labs')
                    ->assertSee('href="'.$path.'?lab=', false)
                    ->assertSee('page=2', false)
                    ->assertSee('/'.$otherLocale.'/facilities/'.$facultySlug.'/labs?lab='.$detailSlug.'&amp;page=2', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?lab='.$detailSlug.'&amp;page=2">', false);

                $this->get($path.'?page=999')
                    ->assertOk()
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$path.'?page=', false)
                    ->assertDontSee('?page=999', false);
            }
        }
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
            ->assertSee('بيانات الكلية وصورها')
            ->assertSee('أقسام التعريف بالكلية');

        $expectedTargets = [
            'facilities.'.$facultySlug,
            'facilities.'.$facultySlug.'.overview',
            'facilities.'.$facultySlug.'.departments',
            'facilities.'.$facultySlug.'.study_plan',
            'facilities.'.$facultySlug.'.labs',
            'facilities.'.$facultySlug.'.projects',
            'facilities.'.$facultySlug.'.research',
            'facilities.'.$facultySlug.'.alumni',
            'facilities.'.$facultySlug.'.valedictorians',
        ];

        if ($facultySlug === 'pharmacy') {
            $expectedTargets[] = 'facilities.pharmacy.training';
        }

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

    #[DataProvider('facultyResearchPageProvider')]
    public function test_faculty_research_pages_render_localized_reference_publications(string $facultySlug, string $englishTitle, string $arabicTitle): void
    {
        $this->get('/en/facilities/'.$facultySlug.'/research')
            ->assertOk()
            ->assertSee('Latest Research')
            ->assertSee($englishTitle)
            ->assertSee('/en/research/publications/', false)
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/facilities/'.$facultySlug.'/research">', false)
            ->assertSee('hreflang="ar"', false);

        $this->get('/ar/facilities/'.$facultySlug.'/research')
            ->assertOk()
            ->assertSee('أحدث الأبحاث')
            ->assertSee($arabicTitle)
            ->assertSee('/ar/research/publications/', false)
            ->assertSee('dir="rtl"', false);
    }

    /** @return iterable<string, array{facultySlug: string, englishTitle: string, arabicTitle: string}> */
    public static function facultyResearchPageProvider(): iterable
    {
        yield 'artificial intelligence' => [
            'facultySlug' => 'artificial-intelligence',
            'englishTitle' => 'Natural Language Processing for Arabic Medical Record Summarization',
            'arabicTitle' => 'معالجة اللغة الطبيعية لتلخيص السجلات الطبية العربية',
        ];
        yield 'business administration' => [
            'facultySlug' => 'business-administration',
            'englishTitle' => 'Business Analytics for Healthcare Supply Chain Resilience',
            'arabicTitle' => 'تحليلات الأعمال لمرونة سلسلة التوريد الصحية',
        ];
        yield 'building construction engineering' => [
            'facultySlug' => 'building-construction-engineering',
            'englishTitle' => 'Structural Performance of Fiber-Reinforced Concrete in Seismic Zones',
            'arabicTitle' => 'الأداء الإنشائي للخرسانة المسلحة بالألياف في المناطق الزلزالية',
        ];
        yield 'dentistry' => [
            'facultySlug' => 'dentistry',
            'englishTitle' => 'AI-Driven Predictive Models for Early Dental Caries Detection',
            'arabicTitle' => 'نماذج تنبؤية مدفوعة بالذكاء الاصطناعي للكشف المبكر عن تسوس الأسنان',
        ];
        yield 'medicine' => [
            'facultySlug' => 'medicine',
            'englishTitle' => 'Clinical Simulation Training Impact on Medical Student Diagnostic Accuracy',
            'arabicTitle' => 'تأثير تدريب المحاكاة السريرية على دقة تشخيص طلاب الطب',
        ];
        yield 'petroleum' => [
            'facultySlug' => 'petroleum',
            'englishTitle' => 'Deep Learning Framework for Reservoir Permeability Prediction',
            'arabicTitle' => 'إطار التعلم العميق للتنبؤ بنفاذية المكامن',
        ];
        yield 'pharmacy' => [
            'facultySlug' => 'pharmacy',
            'englishTitle' => 'Machine Learning Applications in Pharmaceutical Quality Control',
            'arabicTitle' => 'تطبيقات تعلم الآلة في مراقبة جودة الأدوية',
        ];
    }

    public function test_faculty_research_workflow_previews_and_publishes_page_copy(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.medicine.research');
        $payload['translations']['en']['title'] = 'Medicine Research CMS Preview';
        $payload['translations']['en']['summary'] = 'CMS-managed medicine research summary.';
        $payload['translations']['en']['seoTitle'] = 'Medicine Research CMS SEO';
        $payload['translations']['en']['seoDescription'] = 'Medicine research CMS metadata.';
        $payload['translations']['en']['seoImage'] = '/images/research-clinical-simulation.webp';
        $payload['translations']['en']['emptyTitle'] = 'CMS Empty Research Title';
        $payload['translations']['en']['emptySummary'] = 'CMS empty research summary.';

        $workflow->saveDraft('facilities.medicine.research', $payload, (int) $author->id);

        $this->get('/en/facilities/medicine/research')
            ->assertOk()
            ->assertDontSee('Medicine Research CMS Preview');

        $preview = $workflow->preview('facilities.medicine.research', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Medicine Research CMS Preview')
            ->assertSee('Clinical Simulation Training Impact on Medical Student Diagnostic Accuracy')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('facilities.medicine.research', (int) $author->id));
        $publishedPage = $facilities->getSubpage('medicine', 'research', 'en');
        $this->assertSame('CMS Empty Research Title', $publishedPage?->page['emptyTitle'] ?? null);
        $this->assertSame('CMS empty research summary.', $publishedPage?->page['emptySummary'] ?? null);
        $this->get('/en/facilities/medicine/research')
            ->assertOk()
            ->assertSee('Medicine Research CMS Preview')
            ->assertSee('Medicine Research CMS SEO')
            ->assertSee('/images/research-clinical-simulation.webp', false);
    }

    public function test_faculty_research_pagination_is_server_backed(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $research = app(ResearchPageServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $research->getEditablePayload('research.publications');

        foreach (['ar', 'en'] as $locale) {
            foreach ($payload['translations'][$locale]['items'] as $index => $item) {
                $payload['translations'][$locale]['items'][$index]['facultySlug'] = 'medicine';
                $payload['translations'][$locale]['items'][$index]['title'] = 'Paginated Medicine Publication '.($index + 1);
            }
        }

        $workflow->saveDraft('research.publications', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('research.publications', (int) $author->id));

        $this->get('/en/facilities/medicine/research')
            ->assertOk()
            ->assertSee('Paginated Medicine Publication 1')
            ->assertDontSee('Paginated Medicine Publication 7')
            ->assertSee('href="/en/facilities/medicine/research?page=2"', false);

        $this->get('/en/facilities/medicine/research?page=2')
            ->assertOk()
            ->assertSee('Paginated Medicine Publication 7')
            ->assertDontSee('Paginated Medicine Publication 1')
            ->assertSee('aria-current="page"', false)
            ->assertSee('/ar/facilities/medicine/research?page=2', false)
            ->assertSee('<link rel="canonical" href="'.config('app.url').'/en/facilities/medicine/research?page=2">', false);
    }

    public function test_disabled_faculty_research_page_returns_not_found(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        FacultyPage::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('slug', 'research')
            ->update(['is_enabled' => false]);
        Cache::flush();

        $this->get('/en/facilities/medicine/research')->assertNotFound();
    }

    public function test_faculty_research_page_has_one_main_landmark(): void
    {
        $content = $this->get('/en/facilities/medicine/research')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'id="main-content"'));
    }

    public function test_manage_faculty_exposes_research_editor_and_sitemap_lists_all_routes(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->set('data.target_key', 'facilities.medicine.research')
            ->call('loadTarget', 'facilities.medicine.research')
            ->assertSee('إعدادات صفحة البحث العلمي')
            ->assertSee('عنوان محركات البحث');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['artificial-intelligence', 'business-administration', 'building-construction-engineering', 'dentistry', 'medicine', 'petroleum', 'pharmacy'] as $facultySlug) {
            $this->assertStringContainsString('/en/facilities/'.$facultySlug.'/research', $sitemap);
            $this->assertStringContainsString('/ar/facilities/'.$facultySlug.'/research', $sitemap);
        }
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

    public function test_all_study_plan_and_course_routes_render_in_both_locales_with_valid_selector_state(): void
    {
        foreach ($this->facultySlugs() as $facultySlug) {
            foreach (['ar', 'en'] as $locale) {
                [$departmentId, $courseId, $type] = $this->firstStudyPlanSelection($facultySlug, $locale);
                $studyPath = '/'.$locale.'/facilities/'.$facultySlug.'/study-plan';
                $studyQuery = 'department='.rawurlencode($departmentId);
                $coursePath = $studyPath.'/course';
                $courseQuery = http_build_query([
                    'department' => $departmentId,
                    'course' => $courseId,
                    'type' => $type,
                ], '', '&', PHP_QUERY_RFC3986);
                $otherLocale = $locale === 'ar' ? 'en' : 'ar';

                $this->get($studyPath.'?'.$studyQuery)
                    ->assertOk()
                    ->assertSee('data-study-plan', false)
                    ->assertSee('role="dialog"', false)
                    ->assertSee('aria-modal="true"', false)
                    ->assertSee('aria-labelledby="study-plan-modal-title"', false)
                    ->assertSee('aria-describedby="study-plan-modal-description"', false)
                    ->assertSee('data-modal-initial-focus', false)
                    ->assertSee('aria-haspopup="dialog"', false)
                    ->assertSee('tabindex="0" role="region"', false)
                    ->assertSee($locale === 'ar' ? 'aria-label="تكبير المخطط"' : 'aria-label="Zoom in"', false)
                    ->assertSee($locale === 'ar' ? 'aria-label="تصغير المخطط"' : 'aria-label="Zoom out"', false)
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$studyPath.'?'.$studyQuery.'">', false)
                    ->assertSee('/'.$otherLocale.'/facilities/'.$facultySlug.'/study-plan?'.$studyQuery, false);

                $escapedCourseQuery = e($courseQuery);
                $this->get($coursePath.'?'.$courseQuery)
                    ->assertOk()
                    ->assertSee('<link rel="canonical" href="'.config('app.url').$coursePath.'?'.$escapedCourseQuery.'">', false)
                    ->assertSee('/'.$otherLocale.'/facilities/'.$facultySlug.'/study-plan/course?'.$escapedCourseQuery, false)
                    ->assertSee('href="'.$coursePath.'?department='.rawurlencode($departmentId).'&amp;course='.rawurlencode($courseId).'"', false)
                    ->assertDontSee('href="#"', false);
            }
        }
    }

    public function test_study_plan_and_course_selectors_reject_unknown_malformed_and_mismatched_state(): void
    {
        [$departmentId, $courseId] = $this->firstStudyPlanSelection('artificial-intelligence', 'en');
        $page = app(FacultyPageServiceInterface::class)->getSubpage('artificial-intelligence', 'study-plan', 'en');
        $departments = collect($page?->page['payload']['plan']['departments'] ?? []);
        $mismatch = null;

        foreach ($departments as $sourceDepartment) {
            $sourceCourses = collect($sourceDepartment['terms'] ?? [])->flatMap(fn (array $term): array => $term['courses'] ?? []);
            foreach ($departments as $otherDepartment) {
                if (($sourceDepartment['id'] ?? null) === ($otherDepartment['id'] ?? null)) {
                    continue;
                }

                $otherCourseIds = collect($otherDepartment['terms'] ?? [])->flatMap(fn (array $term): array => $term['courses'] ?? [])->pluck('id');
                $uniqueCourse = $sourceCourses->first(fn (array $course): bool => ! $otherCourseIds->contains($course['id'] ?? null));
                if (is_array($uniqueCourse)) {
                    $mismatch = [(string) ($otherDepartment['id'] ?? ''), (string) ($uniqueCourse['id'] ?? '')];
                    break 2;
                }
            }
        }

        $this->assertIsArray($mismatch);
        $coursePath = '/en/facilities/artificial-intelligence/study-plan/course';

        $this->get('/en/facilities/artificial-intelligence/study-plan?department=unknown')->assertNotFound();
        $this->get('/en/facilities/artificial-intelligence/study-plan?department[]=ai')->assertNotFound();
        $this->get($coursePath)->assertNotFound();
        $this->get($coursePath.'?department='.$departmentId.'&course=unknown')->assertNotFound();
        $this->get($coursePath.'?department='.$mismatch[0].'&course='.$mismatch[1])->assertNotFound();
        $this->get($coursePath.'?department='.$departmentId.'&course='.$courseId.'&type=unknown')->assertNotFound();
    }

    public function test_course_materials_and_instructor_links_render_only_when_resolved_safely(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('course-materials/verified.pdf', 'verified course material');
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.study_plan');
        $departmentId = (string) ($payload['translations']['en']['payload']['plan']['departments'][0]['id'] ?? '');
        $courseId = (string) ($payload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['id'] ?? '');

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'][0]['pdfUrl'] = '/storage/course-materials/verified.pdf';
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['instructor']['staffSlug'] = 'missing-profile';
        }

        $workflow->saveDraft('facilities.artificial-intelligence.study_plan', $payload, (int) $author->getKey());
        $this->assertTrue($workflow->publish('facilities.artificial-intelligence.study_plan', (int) $author->getKey()));

        $published = CmsTargetContent::query()
            ->where('target_key', 'facilities.artificial-intelligence.study_plan')
            ->where('status', 'published')
            ->firstOrFail();
        $publishedPayload = $published->payload_json;
        $publishedPayload['translations']['en']['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'][1]['pdfUrl'] = '/storage/../private/unsafe.pdf';
        $published->update(['payload_json' => $publishedPayload]);
        Cache::flush();

        $this->get('/en/facilities/artificial-intelligence/study-plan?department='.$departmentId)
            ->assertOk()
            ->assertDontSee('/storage/../private/unsafe.pdf', false);

        $this->get('/en/facilities/artificial-intelligence/study-plan/course?department='.$departmentId.'&course='.$courseId)
            ->assertOk()
            ->assertSee('href="/storage/course-materials/verified.pdf"', false)
            ->assertDontSee('/storage/../private/unsafe.pdf', false)
            ->assertDontSee('/en/about/profile/faculty-member/missing-profile', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_study_plan_publish_readiness_rejects_unsafe_or_missing_material_paths(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.study_plan');

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['payload']['plan']['departments'][0]['terms'][0]['courses'][0]['lessons'][0]['pdfUrl'] = '/storage/course-materials/missing.pdf';
        }

        $readiness = $workflow->readiness('facilities.artificial-intelligence.study_plan', $payload);

        $this->assertFalse($readiness->isReady);
        $this->assertContains('Course materials must reference an existing internal PDF file.', $readiness->errors['ar']);
        $this->assertContains('Course materials must reference an existing internal PDF file.', $readiness->errors['en']);
    }

    public function test_artificial_intelligence_study_plan_public_payload_is_trimmed_to_active_department(): void
    {
        $response = $this->get('/en/facilities/artificial-intelligence/study-plan');

        $response->assertOk()
            ->assertSee('data-study-plan-payload', false);

        $this->assertLessThan(500_000, strlen((string) $response->getContent()));
    }

    public function test_artificial_intelligence_study_plan_admin_hydrates_one_paired_term_workspace(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $facilities = app(FacultyPageServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.artificial-intelligence.study_plan');
        $departments = $payload['translations']['en']['payload']['plan']['departments'] ?? [];
        $totalCourses = collect($departments)->flatMap(fn (array $department): array => $department['terms'] ?? [])
            ->sum(fn (array $term): int => count($term['courses'] ?? []));

        $component = Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.study_plan')
            ->call('loadTarget', 'facilities.artificial-intelligence.study_plan')
            ->assertSee('القسم')
            ->assertSee('مساحة عمل الفصل باللغتين')
            ->assertSee('مقررات هذا الفصل')
            ->assertSee('المتطلبات السابقة')
            ->assertDontSee('Opens Course IDs');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $selectedDepartmentId = (string) ($data['study_plan_department_id'] ?? '');
        $options = is_array($data['study_plan_department_options'] ?? null) ? $data['study_plan_department_options'] : [];
        $courses = is_array($data['study_plan_workspace']['courses'] ?? null) ? $data['study_plan_workspace']['courses'] : [];

        $this->assertGreaterThan(1, count($options));
        $this->assertNotSame('', $selectedDepartmentId);
        $this->assertLessThan($totalCourses, count($courses));
        $this->assertArrayNotHasKey('departments', $data['en_content']['payload']['plan'] ?? []);
        $this->assertArrayHasKey('titleAr', $courses[array_key_first($courses)] ?? []);
        $this->assertArrayHasKey('titleEn', $courses[array_key_first($courses)] ?? []);
        $this->assertArrayNotHasKey('opensCourseIds', $courses[array_key_first($courses)] ?? []);
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
        $courseKey = array_key_first($data['study_plan_workspace']['courses'] ?? []);
        $this->assertNotNull($courseKey);

        $component
            ->set('data.study_plan_workspace.courses.'.$courseKey.'.titleEn', 'AI Edited Selected Course')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.artificial-intelligence.study_plan')->latest('id')->firstOrFail();
        $savedDepartments = collect($draft->payload_json['translations']['en']['payload']['plan']['departments'] ?? []);
        $savedSelectedDepartment = $savedDepartments->firstWhere('id', $selectedDepartmentId);
        $savedUntouchedDepartment = $savedDepartments->firstWhere('id', $untouchedDepartmentId);
        $savedSelectedCourse = collect($savedSelectedDepartment['terms'] ?? [])
            ->flatMap(fn (array $term): array => $term['courses'] ?? [])
            ->firstWhere('id', $data['study_plan_workspace']['courses'][$courseKey]['id']);

        $this->assertSame('AI Edited Selected Course', $savedSelectedCourse['title'] ?? null);
        $this->assertSame($untouchedDepartment['terms'] ?? [], $savedUntouchedDepartment['terms'] ?? []);
    }

    public function test_artificial_intelligence_study_plan_admin_persists_prerequisite_removal(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.artificial-intelligence.study_plan');
        $department = $payload['translations']['en']['payload']['plan']['departments'][0] ?? [];
        $term = collect($department['terms'] ?? [])->first(
            fn (array $candidate): bool => collect($candidate['courses'] ?? [])->contains(
                fn (array $course): bool => ($course['prerequisites'] ?? []) !== [],
            ),
        );
        $this->assertIsArray($term);

        $component = Livewire::withQueryParams([
            'target' => 'facilities.artificial-intelligence.study_plan',
            'department' => (string) ($department['id'] ?? ''),
            'term' => (string) ($term['id'] ?? ''),
        ])->test(ManageArtificialIntelligenceFaculty::class);

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $courses = $data['study_plan_workspace']['courses'] ?? [];
        $targetKey = collect($courses)->search(fn (array $course): bool => ($course['prerequisites'] ?? []) !== []);
        $this->assertNotFalse($targetKey);
        $targetId = (string) ($courses[$targetKey]['id'] ?? '');

        $component
            ->set('data.study_plan_workspace.courses.'.$targetKey.'.prerequisites', [])
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'facilities.artificial-intelligence.study_plan')->latest('id')->firstOrFail();
        $savedCourses = collect($draft->payload_json['translations']['en']['payload']['plan']['departments'] ?? [])
            ->firstWhere('id', $data['study_plan_department_id'])['terms'] ?? [];
        $targetCourse = collect($savedCourses)
            ->flatMap(fn (array $term): array => $term['courses'] ?? [])
            ->firstWhere('id', $targetId);

        $this->assertSame([], $targetCourse['prerequisites'] ?? null);
        $this->assertArrayNotHasKey('opensCourseIds', $targetCourse);
    }

    public function test_manage_artificial_intelligence_faculty_uses_page_specific_templates(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->assertSee('بيانات الكلية وصورها')
            ->assertSee('أقسام التعريف بالكلية');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.departments')
            ->call('loadTarget', 'facilities.artificial-intelligence.departments')
            ->assertSee('الأقسام الأكاديمية')
            ->assertSee('الدرجة أو المسار')
            ->assertDontSee('Subpage Items');

        Livewire::test(ManageArtificialIntelligenceFaculty::class)
            ->set('data.target_key', 'facilities.artificial-intelligence.projects')
            ->call('loadTarget', 'facilities.artificial-intelligence.projects')
            ->assertSee('مشاريع الطلاب')
            ->assertSee('المشرف')
            ->assertSee('فريق العمل');
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
            ->assertSee('مشاريع الطلاب')
            ->assertSee('المشرف');
    }

    public function test_reference_faculty_and_project_queries_redirect_to_canonical_routes(): void
    {
        $this->get('/en/facilities?id=ai-engineering')
            ->assertRedirect('/en/facilities/artificial-intelligence');

        $this->get('/en/facilities?id=Construction')
            ->assertRedirect('/en/facilities/building-construction-engineering');

        $this->get('/en/projects/detail?id=artificial-intelligence-project-1')
            ->assertRedirect('/en/facilities/artificial-intelligence/projects/artificial-intelligence-project-1');

        $this->get('/en/projects/detail?id=missing-project')->assertNotFound();
    }

    #[DataProvider('facultyOverviewProvider')]
    public function test_all_faculty_overviews_render_localized_central_research_and_canonical_dean_profiles(
        string $facultySlug,
        string $profileSlug,
        string $englishTitle,
        string $arabicTitle,
    ): void {
        $facilities = app(FacultyPageServiceInterface::class);

        foreach (['en' => $englishTitle, 'ar' => $arabicTitle] as $locale => $title) {
            $page = $facilities->getSubpage($facultySlug, 'overview', $locale);

            $this->assertNotNull($page);
            $this->assertNotEmpty($page->latestResearch);
            $this->assertSame('/'.$locale.'/about/profile/person/'.$profileSlug, $page->deanProfile?->path);
            $this->assertSame($title, $page->latestResearch[0]['title'] ?? null);
            $this->assertStringStartsWith('/'.$locale.'/research/publications/', (string) ($page->latestResearch[0]['url'] ?? ''));

            $this->get('/'.$locale.'/facilities/'.$facultySlug.'/overview')
                ->assertOk()
                ->assertSee('id="overview-latest-research"', false)
                ->assertSee('aria-labelledby="overview-latest-research-title"', false)
                ->assertSee($title)
                ->assertSee('/'.$locale.'/research/publications/'.($page->latestResearch[0]['slug'] ?? ''), false)
                ->assertSee('/'.$locale.'/about/profile/person/'.$profileSlug, false)
                ->assertDontSee('/'.$locale.'/about/profile/person/'.$facultySlug.'-dean', false)
                ->assertDontSee('SPU-'.strtoupper($facultySlug).'-', false)
                ->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
        }
    }

    public function test_all_faculty_overview_drafts_preview_localized_content_without_public_leaks_and_publish(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach (self::facultyOverviewProvider() as $case) {
            $facultySlug = $case['facultySlug'];
            $targetKey = 'facilities.'.$facultySlug.'.overview';
            $payload = $facilities->getEditablePayload($targetKey);
            $englishTitle = 'Draft family overview '.$facultySlug;
            $arabicTitle = 'مسودة لمحة الكلية '.$facultySlug;
            $payload['translations']['en']['title'] = $englishTitle;
            $payload['translations']['ar']['title'] = $arabicTitle;

            $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());

            $this->get('/en/facilities/'.$facultySlug.'/overview')->assertOk()->assertDontSee($englishTitle);
            $this->get('/ar/facilities/'.$facultySlug.'/overview')->assertOk()->assertDontSee($arabicTitle);

            foreach (['en' => $englishTitle, 'ar' => $arabicTitle] as $locale => $title) {
                $preview = $workflow->preview($targetKey, $locale, (int) $author->getKey());
                $this->get($preview->previewUrl)
                    ->assertOk()
                    ->assertSee($title)
                    ->assertSee('id="overview-latest-research"', false);
            }

            $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
            $this->get('/en/facilities/'.$facultySlug.'/overview')->assertOk()->assertSee($englishTitle);
            $this->get('/ar/facilities/'.$facultySlug.'/overview')->assertOk()->assertSee($arabicTitle);
        }
    }

    public function test_faculty_overviews_do_not_render_profile_links_without_canonical_public_targets(): void
    {
        Person::query()->where('category', 'dean')->update(['is_enabled' => false]);
        Cache::flush();

        foreach (self::facultyOverviewProvider() as $case) {
            foreach (['ar', 'en'] as $locale) {
                $this->get('/'.$locale.'/facilities/'.$case['facultySlug'].'/overview')
                    ->assertOk()
                    ->assertDontSee('/'.$locale.'/about/profile/person/'.$case['profileSlug'], false)
                    ->assertDontSee('/'.$locale.'/about/profile/person/'.$case['facultySlug'].'-dean', false);
            }
        }
    }

    public function test_faculty_overviews_hide_latest_research_region_when_central_publications_are_empty(): void
    {
        $this->mock(ResearchPageServiceInterface::class, function ($mock): void {
            $mock->shouldReceive('facultyPublications')->andReturnUsing(
                fn (string $facultySlug, string $locale): ResearchPageDTO => new ResearchPageDTO(
                    locale: $locale,
                    direction: $locale === 'ar' ? 'rtl' : 'ltr',
                    type: 'faculty-publications',
                    data: ['items' => []],
                    seoTitle: '',
                    seoDescription: '',
                    seoImage: '',
                    path: '/facilities/'.$facultySlug.'/research',
                ),
            );
        });
        Cache::flush();

        foreach (self::facultyOverviewProvider() as $case) {
            foreach (['ar', 'en'] as $locale) {
                $this->get('/'.$locale.'/facilities/'.$case['facultySlug'].'/overview')
                    ->assertOk()
                    ->assertDontSee('id="overview-latest-research"', false);
            }
        }
    }

    /** @return iterable<string, array{facultySlug: string, profileSlug: string, englishTitle: string, arabicTitle: string}> */
    public static function facultyOverviewProvider(): iterable
    {
        yield 'artificial intelligence' => ['facultySlug' => 'artificial-intelligence', 'profileSlug' => 'mouhib-alnoukari', 'englishTitle' => 'Natural Language Processing for Arabic Medical Record Summarization', 'arabicTitle' => 'معالجة اللغة الطبيعية لتلخيص السجلات الطبية العربية'];
        yield 'business administration' => ['facultySlug' => 'business-administration', 'profileSlug' => 'samar-habib', 'englishTitle' => 'Business Analytics for Healthcare Supply Chain Resilience', 'arabicTitle' => 'تحليلات الأعمال لمرونة سلسلة التوريد الصحية'];
        yield 'building construction engineering' => ['facultySlug' => 'building-construction-engineering', 'profileSlug' => 'ammar-ghada', 'englishTitle' => 'Structural Performance of Fiber-Reinforced Concrete in Seismic Zones', 'arabicTitle' => 'الأداء الإنشائي للخرسانة المسلحة بالألياف في المناطق الزلزالية'];
        yield 'dentistry' => ['facultySlug' => 'dentistry', 'profileSlug' => 'talaat-abu-hatab', 'englishTitle' => 'AI-Driven Predictive Models for Early Dental Caries Detection', 'arabicTitle' => 'نماذج تنبؤية مدفوعة بالذكاء الاصطناعي للكشف المبكر عن تسوس الأسنان'];
        yield 'medicine' => ['facultySlug' => 'medicine', 'profileSlug' => 'ayman-ali', 'englishTitle' => 'Clinical Simulation Training Impact on Medical Student Diagnostic Accuracy', 'arabicTitle' => 'تأثير تدريب المحاكاة السريرية على دقة تشخيص طلاب الطب'];
        yield 'petroleum' => ['facultySlug' => 'petroleum', 'profileSlug' => 'mahmoud-hadid', 'englishTitle' => 'Renewable Energy Integration Challenges in the Syrian Power Grid', 'arabicTitle' => 'تحديات دمج الطاقة المتجددة في الشبكة الكهربائية السورية'];
        yield 'pharmacy' => ['facultySlug' => 'pharmacy', 'profileSlug' => 'hossam-shahrour', 'englishTitle' => 'Machine Learning Applications in Pharmaceutical Quality Control', 'arabicTitle' => 'تطبيقات تعلم الآلة في مراقبة جودة الأدوية'];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function firstStudyPlanSelection(string $facultySlug, string $locale): array
    {
        $page = app(FacultyPageServiceInterface::class)->getSubpage($facultySlug, 'study-plan', $locale);
        $this->assertNotNull($page);
        $department = $page->detail['activeDepartment'] ?? null;
        $this->assertIsArray($department);
        $course = collect($department['terms'] ?? [])
            ->flatMap(fn (array $term): array => is_array($term['courses'] ?? null) ? $term['courses'] : [])
            ->first();
        $this->assertIsArray($course);
        $type = collect($course['lessons'] ?? [])->pluck('type')->first();

        return [
            (string) ($department['id'] ?? ''),
            (string) ($course['id'] ?? ''),
            is_string($type) && $type !== '' ? $type : 'all',
        ];
    }

    /** @return array<int, string> */
    private function facultySlugs(): array
    {
        return [
            'artificial-intelligence',
            'business-administration',
            'building-construction-engineering',
            'dentistry',
            'medicine',
            'petroleum',
            'pharmacy',
        ];
    }

    /** @return array<int, string> */
    private function projectFacultySlugs(): array
    {
        return [
            'artificial-intelligence',
            'business-administration',
            'building-construction-engineering',
            'dentistry',
            'medicine',
            'pharmacy',
        ];
    }

    /** @return array<int, string> */
    private function labFacultySlugs(): array
    {
        return [
            'artificial-intelligence',
            'building-construction-engineering',
            'dentistry',
            'medicine',
            'petroleum',
            'pharmacy',
        ];
    }
}
