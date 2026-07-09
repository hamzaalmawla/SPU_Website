<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Filament\Pages\ManageCampusLife;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CampusLifeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_campus_life_landing_workflow_draft_does_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.landing');
        $payload['translations']['en']['hero']['title'] = 'Published Campus Life Workflow';
        $payload['translations']['ar']['hero']['title'] = 'الحياة الجامعية المنشورة';

        $workflow->saveDraft('campus_life.landing', $payload, (int) $author->id);

        $this->get('/en/campus-life')
            ->assertOk()
            ->assertDontSee('Published Campus Life Workflow');

        $this->assertTrue($workflow->publish('campus_life.landing', (int) $author->id));

        $this->get('/en/campus-life')
            ->assertOk()
            ->assertSee('Published Campus Life Workflow');
    }

    public function test_campus_life_landing_preview_renders_draft_snapshot(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.landing');
        $payload['translations']['en']['hero']['title'] = 'Campus Life Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة الحياة الجامعية';

        $workflow->saveDraft('campus_life.landing', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.landing', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Campus Life Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/campus-life')
            ->assertOk()
            ->assertDontSee('Campus Life Preview Workflow');
    }

    public function test_campus_life_services_workflow_draft_does_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.services');
        $payload['translations']['en']['hero']['title'] = 'Published Campus Services Workflow';
        $payload['translations']['ar']['hero']['title'] = 'خدمات الحرم المنشورة';

        $workflow->saveDraft('campus_life.services', $payload, (int) $author->id);

        $this->get('/en/campus-life/services')
            ->assertOk()
            ->assertDontSee('Published Campus Services Workflow');

        $this->assertTrue($workflow->publish('campus_life.services', (int) $author->id));

        $this->get('/en/campus-life/services')
            ->assertOk()
            ->assertSee('Published Campus Services Workflow');
    }

    public function test_campus_life_services_preview_renders_draft_snapshot(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.services');
        $payload['translations']['en']['hero']['title'] = 'Campus Services Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة خدمات الحرم';

        $workflow->saveDraft('campus_life.services', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.services', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Campus Services Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/campus-life/services')
            ->assertOk()
            ->assertDontSee('Campus Services Preview Workflow');
    }

    public function test_campus_life_transport_workflow_draft_does_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.transport');
        $payload['translations']['en']['hero']['title'] = 'Published Transport Workflow';
        $payload['translations']['ar']['hero']['title'] = 'النقل المنشور';

        $workflow->saveDraft('campus_life.transport', $payload, (int) $author->id);

        $this->get('/en/campus-life/transport')
            ->assertOk()
            ->assertDontSee('Published Transport Workflow');

        $this->assertTrue($workflow->publish('campus_life.transport', (int) $author->id));

        $this->get('/en/campus-life/transport')
            ->assertOk()
            ->assertSee('Published Transport Workflow');
    }

    public function test_campus_life_transport_preview_renders_draft_snapshot(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.transport');
        $payload['translations']['en']['hero']['title'] = 'Transport Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة النقل';

        $workflow->saveDraft('campus_life.transport', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.transport', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Transport Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/campus-life/transport')
            ->assertOk()
            ->assertDontSee('Transport Preview Workflow');
    }

    public function test_campus_life_clubs_activities_workflow_draft_does_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.clubs-activities');
        $payload['translations']['en']['hero']['title'] = 'Published Clubs Workflow';
        $payload['translations']['ar']['hero']['title'] = 'الأندية المنشورة';

        $workflow->saveDraft('campus_life.clubs-activities', $payload, (int) $author->id);

        $this->get('/en/campus-life/clubs-activities')
            ->assertOk()
            ->assertDontSee('Published Clubs Workflow');

        $this->assertTrue($workflow->publish('campus_life.clubs-activities', (int) $author->id));

        $this->get('/en/campus-life/clubs-activities')
            ->assertOk()
            ->assertSee('Published Clubs Workflow');
    }

    public function test_campus_life_clubs_activities_preview_renders_draft_snapshot(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.clubs-activities');
        $payload['translations']['en']['hero']['title'] = 'Clubs Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة الأندية';

        $workflow->saveDraft('campus_life.clubs-activities', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.clubs-activities', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Clubs Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/campus-life/clubs-activities')
            ->assertOk()
            ->assertDontSee('Clubs Preview Workflow');
    }

    public function test_campus_life_career_development_workflow_draft_does_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.career-development');
        $payload['translations']['en']['hero']['title'] = 'Published Career Workflow';
        $payload['translations']['ar']['hero']['title'] = 'التطوير المهني المنشور';

        $workflow->saveDraft('campus_life.career-development', $payload, (int) $author->id);

        $this->get('/en/campus-life/career-development')
            ->assertOk()
            ->assertDontSee('Published Career Workflow');

        $this->assertTrue($workflow->publish('campus_life.career-development', (int) $author->id));

        $this->get('/en/campus-life/career-development')
            ->assertOk()
            ->assertSee('Published Career Workflow');
    }

    public function test_campus_life_career_development_preview_renders_draft_snapshot(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $campusLife->getEditablePayload('campus_life.career-development');
        $payload['translations']['en']['hero']['title'] = 'Career Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة التطوير المهني';

        $workflow->saveDraft('campus_life.career-development', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.career-development', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Career Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/campus-life/career-development')
            ->assertOk()
            ->assertDontSee('Career Preview Workflow');
    }

    public function test_remaining_campus_life_subpage_workflows_drafts_do_not_leak_until_published(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach ([
            'dental' => 'Published Dental Workflow',
            'hospital' => 'Published Hospital Workflow',
            'health-insurance' => 'Published Health Insurance Workflow',
        ] as $slug => $title) {
            $targetKey = 'campus_life.'.$slug;
            $payload = $campusLife->getEditablePayload($targetKey);
            $payload['translations']['en']['hero']['title'] = $title;
            $payload['translations']['ar']['hero']['title'] = 'عنوان منشور';

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);

            $this->get('/en/campus-life/'.$slug)
                ->assertOk()
                ->assertDontSee($title);

            $this->assertTrue($workflow->publish($targetKey, (int) $author->id));

            $this->get('/en/campus-life/'.$slug)
                ->assertOk()
                ->assertSee($title);
        }
    }

    public function test_remaining_campus_life_subpage_previews_render_draft_snapshots(): void
    {
        $campusLife = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach ([
            'dental' => 'Dental Preview Workflow',
            'hospital' => 'Hospital Preview Workflow',
            'health-insurance' => 'Health Insurance Preview Workflow',
        ] as $slug => $title) {
            $targetKey = 'campus_life.'.$slug;
            $payload = $campusLife->getEditablePayload($targetKey);
            $payload['translations']['en']['hero']['title'] = $title;
            $payload['translations']['ar']['hero']['title'] = 'عنوان معاينة';

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);
            $preview = $workflow->preview($targetKey, 'en', (int) $author->id);

            $this->get($preview->previewUrl)
                ->assertOk()
                ->assertSee($title)
                ->assertSee('Preview mode');

            $this->get('/en/campus-life/'.$slug)
                ->assertOk()
                ->assertDontSee($title);
        }
    }

    public function test_manage_campus_life_uses_curated_landing_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->assertSet('data.target_key', 'campus_life.landing')
            ->assertSee('Hero')
            ->assertSee('Intro')
            ->assertSee('Feature Cards')
            ->assertSee('Digital Portals');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $quickLinks = is_array($data['en_landing']['hero']['quickLinks'] ?? null) ? $data['en_landing']['hero']['quickLinks'] : [];
        $quickLinks[] = [
            'label' => 'Curated Campus Link',
            'href' => '/en/campus-life/services',
        ];

        $component
            ->set('data.en_landing.hero.title', 'Curated Campus Life Landing')
            ->set('data.en_landing.hero.quickLinks', $quickLinks)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.landing')->latest('id')->firstOrFail();
        $linkLabels = collect($draft->payload_json['translations']['en']['hero']['quickLinks'] ?? [])->pluck('label')->all();

        $this->assertSame('Curated Campus Life Landing', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Campus Link', $linkLabels);
    }

    public function test_manage_campus_life_skips_virtual_tour_until_the_end(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageCampusLife::class)
            ->assertSet('data.target_key', 'campus_life.landing')
            ->assertDontSee('Virtual Tour');
    }

    public function test_imported_campus_life_pages_render_and_have_curated_editors(): void
    {
        foreach ([
            'campus_life.damascus-research-pub' => ['path' => '/en/campus-life/damascus-research-pub', 'state' => 'en_damascus_research_pub', 'title' => 'Damascus Research Center Publications'],
            'campus_life.rules-regulations' => ['path' => '/en/campus-life/rules-regulations', 'state' => 'en_rules_regulations', 'title' => 'Rules & Regulations'],
            'campus_life.general-rules' => ['path' => '/en/campus-life/general-rules', 'state' => 'en_general_rules', 'title' => 'General Rules & Instructions'],
            'campus_life.exam-instructions' => ['path' => '/en/campus-life/exam-instructions', 'state' => 'en_exam_instructions', 'title' => 'Exam Instructions'],
            'campus_life.exam-penalties' => ['path' => '/en/campus-life/exam-penalties', 'state' => 'en_exam_penalties', 'title' => 'Exam Penalties'],
        ] as $targetKey => $case) {
            $this->get($case['path'])
                ->assertOk()
                ->assertSee($case['title']);
        }

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach ([
            'campus_life.damascus-research-pub' => 'en_damascus_research_pub',
            'campus_life.rules-regulations' => 'en_rules_regulations',
            'campus_life.general-rules' => 'en_general_rules',
            'campus_life.exam-instructions' => 'en_exam_instructions',
            'campus_life.exam-penalties' => 'en_exam_penalties',
        ] as $targetKey => $stateKey) {
            $component = Livewire::test(ManageCampusLife::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Information Cards')
                ->assertDontSee('Target Schema Pending');

            /** @var array<string, mixed> $data */
            $data = $component->get('data');
            $items = is_array($data[$stateKey]['items'] ?? null) ? $data[$stateKey]['items'] : [];
            $items[] = ['title' => 'Curated Imported Campus Life Card', 'body' => 'Saved through the imported campus life editor.'];

            $component
                ->set('data.'.$stateKey.'.items', $items)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
            $itemTitles = collect($draft->payload_json['translations']['en']['items'] ?? [])->pluck('title')->all();

            $this->assertContains('Curated Imported Campus Life Card', $itemTitles);
        }
    }

    public function test_manage_campus_life_services_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.services')
            ->call('loadTarget', 'campus_life.services')
            ->assertSee('Hero')
            ->assertSee('Services Directory')
            ->assertSee('Support Panel');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $items = is_array($data['en_services']['services']['items'] ?? null) ? $data['en_services']['services']['items'] : [];
        $items[] = [
            'id' => 'curated-campus-service',
            'title' => 'Curated Campus Service',
            'access' => 'Use the curated service path.',
            'href' => '/en/campus-life/services#curated-campus-service',
            'image' => '/images/campus-feature-01.webp',
            'wide' => false,
        ];

        $component
            ->set('data.en_services.hero.title', 'Curated Campus Services')
            ->set('data.en_services.services.items', $items)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.services')->latest('id')->firstOrFail();
        $serviceTitles = collect($draft->payload_json['translations']['en']['services']['items'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Campus Services', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Campus Service', $serviceTitles);
    }

    public function test_manage_campus_life_transport_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.transport')
            ->call('loadTarget', 'campus_life.transport')
            ->assertSee('Hero')
            ->assertSee('Transport Cards')
            ->assertSee('Success Panel');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $cards = is_array($data['en_transport']['cards'] ?? null) ? $data['en_transport']['cards'] : [];
        $cards[] = [
            'title' => 'Curated Transport Card',
            'description' => 'Curated transport support details.',
            'cta' => 'Open Route',
            'href' => '/en/campus-life/transport#curated-route',
            'icon' => '/images/icon-map-outline.svg',
        ];

        $component
            ->set('data.en_transport.hero.title', 'Curated Transport Services')
            ->set('data.en_transport.cards', $cards)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.transport')->latest('id')->firstOrFail();
        $cardTitles = collect($draft->payload_json['translations']['en']['cards'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Transport Services', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Transport Card', $cardTitles);
    }

    public function test_manage_campus_life_clubs_activities_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.clubs-activities')
            ->call('loadTarget', 'campus_life.clubs-activities')
            ->assertSee('Hero')
            ->assertSee('Clubs Directory')
            ->assertSee('Featured Activity')
            ->assertSee('Activity List');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $clubs = is_array($data['en_clubs_activities']['clubs']['items'] ?? null) ? $data['en_clubs_activities']['clubs']['items'] : [];
        $clubs[] = [
            'id' => 'curated-campus-club',
            'tag' => 'Culture',
            'title' => 'Curated Campus Club',
            'summary' => 'A curated student club entry.',
            'image' => '/images/campus-clubs.webp',
            'href' => '/en/campus-life/clubs-activities#curated-campus-club',
        ];

        $component
            ->set('data.en_clubs_activities.hero.title', 'Curated Clubs Activities')
            ->set('data.en_clubs_activities.clubs.items', $clubs)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.clubs-activities')->latest('id')->firstOrFail();
        $clubTitles = collect($draft->payload_json['translations']['en']['clubs']['items'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Clubs Activities', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Campus Club', $clubTitles);
    }

    public function test_manage_campus_life_career_development_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.career-development')
            ->call('loadTarget', 'campus_life.career-development')
            ->assertSee('Hero')
            ->assertSee('Career Services')
            ->assertSee('Success Panel');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $items = is_array($data['en_career_development']['services']['items'] ?? null) ? $data['en_career_development']['services']['items'] : [];
        $items[] = [
            'id' => 'curated-career-service',
            'icon' => '/images/icon-award-outline.svg',
            'title' => 'Curated Career Service',
            'summary' => 'A curated career service entry.',
            'link' => 'Explore Service',
            'href' => '/en/campus-life/career-development#curated-career-service',
        ];

        $component
            ->set('data.en_career_development.hero.title', 'Curated Career Development')
            ->set('data.en_career_development.services.items', $items)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.career-development')->latest('id')->firstOrFail();
        $serviceTitles = collect($draft->payload_json['translations']['en']['services']['items'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Career Development', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Career Service', $serviceTitles);
    }

    public function test_manage_campus_life_clinical_targets_use_curated_editors_and_save_payloads(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach ([
            'dental' => ['field' => 'services', 'label' => 'Dental Services', 'title' => 'Curated Dental Clinics', 'item' => 'Curated Dental Service'],
            'hospital' => ['field' => 'departments', 'label' => 'Medical Departments', 'title' => 'Curated University Hospital', 'item' => 'Curated Hospital Department'],
        ] as $slug => $case) {
            $component = Livewire::test(ManageCampusLife::class)
                ->set('data.target_key', 'campus_life.'.$slug)
                ->call('loadTarget', 'campus_life.'.$slug)
                ->assertSee('Hero')
                ->assertSee($case['label'])
                ->assertSee('Weekly Schedule');

            /** @var array<string, mixed> $data */
            $data = $component->get('data');
            $formKey = 'en_'.$slug;
            $field = (string) $case['field'];
            $items = is_array($data[$formKey][$field] ?? null) ? $data[$formKey][$field] : [];
            $items[] = [
                'title' => $case['item'],
                'description' => 'Curated clinical content entry.',
                'icon' => '/images/icons/hospital.svg',
            ];

            $component
                ->set('data.'.$formKey.'.hero.title', $case['title'])
                ->set('data.'.$formKey.'.'.$field, $items)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', 'campus_life.'.$slug)->latest('id')->firstOrFail();
            $titles = collect($draft->payload_json['translations']['en'][$field] ?? [])->pluck('title')->all();

            $this->assertSame($case['title'], $draft->payload_json['translations']['en']['hero']['title'] ?? null);
            $this->assertContains($case['item'], $titles);
        }
    }

    public function test_manage_campus_life_health_insurance_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.health-insurance')
            ->call('loadTarget', 'campus_life.health-insurance')
            ->assertSee('Hero')
            ->assertSee('Health Insurance Sections');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $sections = is_array($data['en_health_insurance']['sections'] ?? null) ? $data['en_health_insurance']['sections'] : [];
        $sections[] = [
            'id' => 'curated-health-section',
            'type' => 'highlight',
            'title' => 'Curated Health Section',
            'description' => 'Curated health insurance content.',
            'items' => [],
            'list' => [],
        ];

        $component
            ->set('data.en_health_insurance.hero.title', 'Curated Health Insurance')
            ->set('data.en_health_insurance.sections', $sections)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'campus_life.health-insurance')->latest('id')->firstOrFail();
        $sectionTitles = collect($draft->payload_json['translations']['en']['sections'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Health Insurance', $draft->payload_json['translations']['en']['hero']['title'] ?? null);
        $this->assertContains('Curated Health Section', $sectionTitles);
    }
}
