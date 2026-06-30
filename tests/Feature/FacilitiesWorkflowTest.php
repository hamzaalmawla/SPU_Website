<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
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
}
