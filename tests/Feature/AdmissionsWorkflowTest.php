<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Filament\Pages\ManageAdmissions;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdmissionsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admissions_landing_workflow_draft_does_not_leak_until_published(): void
    {
        $admissions = app(AdmissionsPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $admissions->getEditablePayload('admissions.landing');
        $payload['translations']['en']['hero']['title'] = 'Admissions Published Workflow';
        $payload['translations']['ar']['hero']['title'] = 'قبول منشور';

        $workflow->saveDraft('admissions.landing', $payload, (int) $author->id);

        $this->get('/en/admissions')
            ->assertOk()
            ->assertDontSee('Admissions Published Workflow');

        $this->assertTrue($workflow->publish('admissions.landing', (int) $author->id));

        $this->get('/en/admissions')
            ->assertOk()
            ->assertSee('Admissions Published Workflow');
    }

    public function test_admissions_section_workflow_draft_does_not_leak_until_published(): void
    {
        $admissions = app(AdmissionsPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $admissions->getEditablePayload('admissions.requirements');
        $payload['translations']['en']['title'] = 'Requirements Published Workflow';
        $payload['translations']['ar']['title'] = 'متطلبات منشورة';

        $workflow->saveDraft('admissions.requirements', $payload, (int) $author->id);

        $this->get('/en/admissions/requirements')
            ->assertOk()
            ->assertDontSee('Requirements Published Workflow');

        $this->assertTrue($workflow->publish('admissions.requirements', (int) $author->id));

        $this->get('/en/admissions/requirements')
            ->assertOk()
            ->assertSee('Requirements Published Workflow');
    }

    public function test_admissions_landing_preview_renders_draft_snapshot(): void
    {
        $admissions = app(AdmissionsPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $admissions->getEditablePayload('admissions.landing');
        $payload['translations']['en']['hero']['title'] = 'Admissions Preview Workflow';
        $payload['translations']['ar']['hero']['title'] = 'معاينة القبول';

        $workflow->saveDraft('admissions.landing', $payload, (int) $author->id);
        $preview = $workflow->preview('admissions.landing', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Admissions Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/admissions')
            ->assertOk()
            ->assertDontSee('Admissions Preview Workflow');
    }

    public function test_admissions_section_preview_renders_draft_snapshot(): void
    {
        $admissions = app(AdmissionsPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $admissions->getEditablePayload('admissions.requirements');
        $payload['translations']['en']['title'] = 'Requirements Preview Workflow';
        $payload['translations']['ar']['title'] = 'معاينة المتطلبات';

        $workflow->saveDraft('admissions.requirements', $payload, (int) $author->id);
        $preview = $workflow->preview('admissions.requirements', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Requirements Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/admissions/requirements')
            ->assertOk()
            ->assertDontSee('Requirements Preview Workflow');
    }

    public function test_manage_admissions_uses_structured_fields_instead_of_json_payload_textareas(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->assertSet('data.target_key', 'admissions.landing');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');

        $this->assertArrayHasKey('ar_landing', $data);
        $this->assertArrayHasKey('en_landing', $data);
        $this->assertSame('Admissions', $data['en_landing']['hero_title'] ?? null);
        $this->assertArrayHasKey('trust_bar', $data['en_landing']);
        $this->assertArrayNotHasKey('ar_payload', $data);
        $this->assertArrayNotHasKey('en_payload', $data);
    }

    public function test_manage_admissions_renders_populated_structured_field_values(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAdmissions::class)
            ->assertSee('Admissions')
            ->assertSee('القبول والتسجيل');
    }

    public function test_manage_admissions_can_add_landing_repeater_items(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class);
        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $trustBar = is_array($data['en_landing']['trust_bar'] ?? null) ? $data['en_landing']['trust_bar'] : [];
        $trustBar[] = [
            'title' => 'New Admissions Repeater Item',
            'icon' => '/images/icon-award-outline.svg',
        ];

        $component
            ->set('data.en_landing.trust_bar', $trustBar)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.landing')->latest('id')->firstOrFail();

        $this->assertSame('New Admissions Repeater Item', $draft->payload_json['translations']['en']['trustBar'][count($trustBar) - 1]['title'] ?? null);
    }

    public function test_manage_admissions_all_targets_have_curated_editors(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.transfer')
            ->call('loadTarget', 'admissions.transfer')
            ->assertSee('Transfer and International Tabs')
            ->assertDontSee('Subpage Schema Pending');
    }

    public function test_imported_admissions_pages_render_redirects_and_have_curated_editors(): void
    {
        $this->get('/en/admissions/study-system')->assertRedirect('/en/admissions/documents');
        $this->get('/en/admissions/academic-warnings')->assertRedirect('/en/admissions/documents');

        foreach ([
            'admissions.filling-vacancies' => ['path' => '/en/admissions/filling-vacancies', 'state' => 'en_filling_vacancies', 'title' => 'Filling Vacant Seats'],
            'admissions.graduation-exams' => ['path' => '/en/admissions/graduation-exams', 'state' => 'en_graduation_exams', 'title' => 'Graduation & National Examinations'],
        ] as $targetKey => $case) {
            $this->get($case['path'])
                ->assertOk()
                ->assertSee($case['title'])
                ->assertSee('/images/admission/front-img.jpg');
        }

        $this->assertFileExists(public_path('images/admission/front-img.jpg'));

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach ([
            'admissions.filling-vacancies' => 'en_filling_vacancies',
            'admissions.graduation-exams' => 'en_graduation_exams',
        ] as $targetKey => $stateKey) {
            $component = Livewire::test(ManageAdmissions::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Hero and Intro')
                ->assertDontSee('Subpage Schema Pending');

            /** @var array<string, mixed> $data */
            $data = $component->get('data');
            $cards = is_array($data[$stateKey]['cards'] ?? null) ? $data[$stateKey]['cards'] : [];
            $cards[] = ['title' => 'Curated Imported Admissions Card', 'body' => 'Saved through the imported admissions editor.'];

            $component
                ->set('data.'.$stateKey.'.cards', $cards)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
            $cardTitles = collect($draft->payload_json['translations']['en']['cards'] ?? [])->pluck('title')->all();

            $this->assertContains('Curated Imported Admissions Card', $cardTitles);
        }
    }

    public function test_manage_admissions_requirements_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.requirements')
            ->call('loadTarget', 'admissions.requirements')
            ->assertSee('Eligibility Criteria')
            ->assertSee('Required Documents')
            ->assertSee('Ready Checklist');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $tabs = is_array($data['en_requirements']['tabs'] ?? null) ? $data['en_requirements']['tabs'] : [];
        $tabs[0]['criteria'][] = [
            'title' => 'Curated Requirements Criterion',
            'desc' => 'A requirement added through the curated requirements editor.',
        ];

        $component
            ->set('data.en_requirements.title', 'Curated Admission Requirements')
            ->set('data.en_requirements.tabs', $tabs)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.requirements')->latest('id')->firstOrFail();
        $criteriaTitles = collect($draft->payload_json['translations']['en']['tabs'] ?? [])
            ->flatMap(static fn (array $tab): array => is_array($tab['criteria'] ?? null) ? $tab['criteria'] : [])
            ->pluck('title')
            ->all();

        $this->assertSame('Curated Admission Requirements', $draft->payload_json['translations']['en']['title'] ?? null);
        $this->assertContains('Curated Requirements Criterion', $criteriaTitles);
    }

    public function test_manage_admissions_tuition_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.tuition')
            ->call('loadTarget', 'admissions.tuition')
            ->assertSee('Fee Table')
            ->assertSee('Payment Methods')
            ->assertSee('Financial Notes');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $feeRows = is_array($data['en_tuition']['fee_rows'] ?? null) ? $data['en_tuition']['fee_rows'] : [];
        $feeRows[] = [
            'faculty' => 'Curated Faculty',
            'type' => 'New',
            'tuitionFee' => '$1,000',
            'registrationFee' => '$100',
            'additionalFees' => '$50',
            'notes' => 'Saved through the curated tuition editor.',
        ];

        $component
            ->set('data.en_tuition.title', 'Curated Tuition Page')
            ->set('data.en_tuition.fee_rows', $feeRows)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.tuition')->latest('id')->firstOrFail();
        $faculties = collect($draft->payload_json['translations']['en']['feeRows'] ?? [])->pluck('faculty')->all();

        $this->assertSame('Curated Tuition Page', $draft->payload_json['translations']['en']['title'] ?? null);
        $this->assertContains('Curated Faculty', $faculties);
    }

    public function test_manage_admissions_how_to_apply_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.how-to-apply')
            ->call('loadTarget', 'admissions.how-to-apply')
            ->assertSee('Hero and Intro')
            ->assertSee('Feature Cards')
            ->assertSee('Step-by-Step Guide');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $steps = is_array($data['en_how_to_apply']['steps'] ?? null) ? $data['en_how_to_apply']['steps'] : [];
        $steps[] = [
            'number' => '05',
            'title' => 'Curated Application Step',
            'desc' => 'A step added through the curated how-to-apply editor.',
            'cta' => 'Continue',
            'href' => '/en/admissions/documents',
        ];

        $component
            ->set('data.en_how_to_apply.hero_title', 'Curated Admissions Journey')
            ->set('data.en_how_to_apply.steps', $steps)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.how-to-apply')->latest('id')->firstOrFail();
        $stepTitles = collect($draft->payload_json['translations']['en']['steps'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Admissions Journey', $draft->payload_json['translations']['en']['heroTitle'] ?? null);
        $this->assertContains('Curated Application Step', $stepTitles);
    }

    public function test_manage_admissions_faq_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.faq')
            ->call('loadTarget', 'admissions.faq')
            ->assertSee('Hero and Search')
            ->assertSee('FAQ Groups')
            ->assertSee('Questions');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $sections = is_array($data['en_faq']['sections'] ?? null) ? $data['en_faq']['sections'] : [];
        $sections[0]['items'][] = [
            'q' => 'Curated FAQ Question?',
            'a' => 'This answer was saved through the curated FAQ editor.',
        ];

        $component
            ->set('data.en_faq.search_placeholder', 'Curated FAQ search')
            ->set('data.en_faq.sections', $sections)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.faq')->latest('id')->firstOrFail();
        $questions = collect($draft->payload_json['translations']['en']['sections'] ?? [])
            ->flatMap(static fn (array $section): array => is_array($section['items'] ?? null) ? $section['items'] : [])
            ->pluck('q')
            ->all();

        $this->assertSame('Curated FAQ search', $draft->payload_json['translations']['en']['searchPlaceholder'] ?? null);
        $this->assertContains('Curated FAQ Question?', $questions);
    }

    public function test_manage_admissions_calendar_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.calendar')
            ->call('loadTarget', 'admissions.calendar')
            ->assertSee('Highlights and Deadlines')
            ->assertSee('Academic Timeline')
            ->assertSee('Download and Notice');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $deadlines = is_array($data['en_calendar']['deadlines'] ?? null) ? $data['en_calendar']['deadlines'] : [];
        $deadlines[] = [
            'type' => 'Curated',
            'title' => 'Curated Calendar Deadline',
            'date' => 'Dec 31, 2026',
        ];

        $component
            ->set('data.en_calendar.timeline_title', 'Curated Academic Timeline')
            ->set('data.en_calendar.deadlines', $deadlines)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.calendar')->latest('id')->firstOrFail();
        $deadlineTitles = collect($draft->payload_json['translations']['en']['deadlines'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Academic Timeline', $draft->payload_json['translations']['en']['timelineTitle'] ?? null);
        $this->assertContains('Curated Calendar Deadline', $deadlineTitles);
    }

    public function test_manage_admissions_documents_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.documents')
            ->call('loadTarget', 'admissions.documents')
            ->assertSee('Admission Checklist')
            ->assertSee('University Documents')
            ->assertSee('Study System and GPA')
            ->assertSee('Academic Warnings');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $items = is_array($data['en_documents']['granted_items'] ?? null) ? $data['en_documents']['granted_items'] : [];
        $items[] = [
            'title' => 'Curated University Document',
            'desc' => 'A document added through the curated documents editor.',
            'availability' => 'On request',
        ];

        $component
            ->set('data.en_documents.last_reviewed', 'Curated Review Date')
            ->set('data.en_documents.granted_items', $items)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.documents')->latest('id')->firstOrFail();
        $grantedTab = collect($draft->payload_json['translations']['en']['tabs'] ?? [])->firstWhere('id', 'granted');
        $documentTitles = collect(is_array($grantedTab['items'] ?? null) ? $grantedTab['items'] : [])->pluck('title')->all();

        $this->assertSame('Curated Review Date', $draft->payload_json['translations']['en']['lastReviewed'] ?? null);
        $this->assertContains('Curated University Document', $documentTitles);
    }

    public function test_manage_admissions_transfer_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAdmissions::class)
            ->set('data.target_key', 'admissions.transfer')
            ->call('loadTarget', 'admissions.transfer')
            ->assertSee('Hero and Labels')
            ->assertSee('Transfer and International Tabs')
            ->assertSee('Process Steps');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $tabs = is_array($data['en_transfer']['tabs'] ?? null) ? $data['en_transfer']['tabs'] : [];
        $tabs[0]['steps'][] = [
            'title' => 'Curated Transfer Step',
            'desc' => 'A step added through the curated transfer editor.',
        ];

        $component
            ->set('data.en_transfer.notes_title', 'Curated Transfer Notes')
            ->set('data.en_transfer.tabs', $tabs)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'admissions.transfer')->latest('id')->firstOrFail();
        $stepTitles = collect($draft->payload_json['translations']['en']['tabs'] ?? [])
            ->flatMap(static fn (array $tab): array => is_array($tab['steps'] ?? null) ? $tab['steps'] : [])
            ->pluck('title')
            ->all();

        $this->assertSame('Curated Transfer Notes', $draft->payload_json['translations']['en']['notesTitle'] ?? null);
        $this->assertContains('Curated Transfer Step', $stepTitles);
    }
}
