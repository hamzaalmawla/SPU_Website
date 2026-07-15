<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Filament\Pages\ManageAbout;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AboutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_about_landing_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.landing');
        $payload['translations']['en']['headline'] = 'About Published Workflow';
        $payload['translations']['ar']['headline'] = 'نبذة منشورة';

        $workflow->saveDraft('about.landing', $payload, (int) $author->id);

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('About Published Workflow');

        $this->assertTrue($workflow->publish('about.landing', (int) $author->id));

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('About Published Workflow');
    }

    public function test_about_landing_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.landing');
        $payload['translations']['en']['headline'] = 'About Preview Workflow';
        $payload['translations']['ar']['headline'] = 'معاينة عن الجامعة';

        $workflow->saveDraft('about.landing', $payload, (int) $author->id);
        $preview = $workflow->preview('about.landing', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('About Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about')
            ->assertOk()
            ->assertDontSee('About Preview Workflow');
    }

    public function test_manage_about_uses_curated_landing_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->assertSet('data.target_key', 'about.landing')
            ->assertSee('Hero and Story')
            ->assertSee('Stats');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $stats = is_array($data['en_landing']['stats'] ?? null) ? $data['en_landing']['stats'] : [];
        $stats[] = [
            'value' => '99',
            'label' => 'Curated About Stat',
            'icon' => '/images/icon-award-outline.svg',
        ];

        $component
            ->set('data.en_landing.headline', 'Curated About Landing')
            ->set('data.en_landing.stats', $stats)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.landing')->latest('id')->firstOrFail();
        $statLabels = collect($draft->payload_json['translations']['en']['stats'] ?? [])->pluck('label')->all();

        $this->assertSame('Curated About Landing', $draft->payload_json['translations']['en']['headline'] ?? null);
        $this->assertContains('Curated About Stat', $statLabels);
    }

    public function test_manage_about_all_targets_have_curated_editors(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.directorates_staff')
            ->call('loadTarget', 'about.directorates_staff')
            ->assertSee('Hero')
            ->assertDontSee('Subpage Schema Pending');
    }

    public function test_imported_about_pages_render_redirects_and_have_curated_editors(): void
    {
        $this->get('/en/about/university-council')->assertRedirect('/en/about/leadership');
        $this->get('/en/about/partnership')->assertRedirect('/en/about/partnerships');

        foreach ([
            'about.quality-policy' => ['path' => '/en/about/quality-policy', 'state' => 'en_quality_policy', 'title' => 'Quality Policy at SPU'],
            'about.ethical-charter' => ['path' => '/en/about/ethical-charter', 'state' => 'en_ethical_charter', 'title' => 'Ethical Charter of SPU'],
            'about.organizational-structure' => ['path' => '/en/about/organizational-structure', 'state' => 'en_organizational_structure', 'title' => 'Organizational Structure of SPU'],
        ] as $targetKey => $case) {
            $this->get($case['path'])
                ->assertOk()
                ->assertSee($case['title']);
        }

        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach ([
            'about.quality-policy' => 'en_quality_policy',
            'about.ethical-charter' => 'en_ethical_charter',
            'about.organizational-structure' => 'en_organizational_structure',
        ] as $targetKey => $stateKey) {
            $component = Livewire::test(ManageAbout::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Content Cards')
                ->assertDontSee('Subpage Schema Pending');

            /** @var array<string, mixed> $data */
            $data = $component->get('data');
            $sections = is_array($data[$stateKey]['sections'] ?? null) ? $data[$stateKey]['sections'] : [];
            $sections[] = ['title' => 'Curated Imported About Card', 'body' => 'Saved through the imported about editor.'];

            $component
                ->set('data.'.$stateKey.'.sections', $sections)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();
            $sectionTitles = collect($draft->payload_json['translations']['en']['sections'] ?? [])->pluck('title')->all();

            $this->assertContains('Curated Imported About Card', $sectionTitles);
        }
    }

    public function test_about_history_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.history');
        $payload['translations']['en']['sections']['foundingTitle'] = 'History Published Workflow';
        $payload['translations']['ar']['sections']['foundingTitle'] = 'تاريخ منشور';

        $workflow->saveDraft('about.history', $payload, (int) $author->id);

        $this->get('/en/about/history')
            ->assertOk()
            ->assertDontSee('History Published Workflow');

        $this->assertTrue($workflow->publish('about.history', (int) $author->id));

        $this->get('/en/about/history')
            ->assertOk()
            ->assertSee('History Published Workflow');
    }

    public function test_about_history_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.history');
        $payload['translations']['en']['sections']['foundingTitle'] = 'History Preview Workflow';
        $payload['translations']['ar']['sections']['foundingTitle'] = 'معاينة التاريخ';

        $workflow->saveDraft('about.history', $payload, (int) $author->id);
        $preview = $workflow->preview('about.history', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('History Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about/history')
            ->assertOk()
            ->assertDontSee('History Preview Workflow');
    }

    public function test_manage_about_history_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.history')
            ->call('loadTarget', 'about.history')
            ->assertSee('Founding Vision')
            ->assertSee('Institutional Timeline')
            ->assertSee('Narratives')
            ->assertSee('Legacy');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $timeline = is_array($data['en_history']['timeline'] ?? null) ? $data['en_history']['timeline'] : [];
        $timeline[] = [
            'year' => '2030',
            'title' => 'Curated History Milestone',
            'body' => 'A milestone added through the curated history editor.',
        ];

        $component
            ->set('data.en_history.foundingTitle', 'Curated Founding Vision')
            ->set('data.en_history.timeline', $timeline)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.history')->latest('id')->firstOrFail();
        $timelineTitles = collect($draft->payload_json['translations']['en']['sections']['timeline'] ?? [])->pluck('title')->all();

        $this->assertSame('Curated Founding Vision', $draft->payload_json['translations']['en']['sections']['foundingTitle'] ?? null);
        $this->assertContains('Curated History Milestone', $timelineTitles);
    }

    public function test_about_leadership_workflow_draft_does_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.leadership');
        $payload['translations']['en']['headline'] = 'Leadership Published Workflow';
        $payload['translations']['ar']['headline'] = 'قيادة منشورة';

        $workflow->saveDraft('about.leadership', $payload, (int) $author->id);

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertDontSee('Leadership Published Workflow');

        $this->assertTrue($workflow->publish('about.leadership', (int) $author->id));

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertSee('Leadership Published Workflow');
    }

    public function test_about_leadership_preview_renders_draft_snapshot(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $about->getEditablePayload('about.leadership');
        $payload['translations']['en']['headline'] = 'Leadership Preview Workflow';
        $payload['translations']['ar']['headline'] = 'معاينة القيادة';

        $workflow->saveDraft('about.leadership', $payload, (int) $author->id);
        $preview = $workflow->preview('about.leadership', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Leadership Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/about/leadership')
            ->assertOk()
            ->assertDontSee('Leadership Preview Workflow');
    }

    public function test_manage_about_leadership_uses_curated_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageAbout::class)
            ->set('data.target_key', 'about.leadership')
            ->call('loadTarget', 'about.leadership')
            ->assertSee('Hero')
            ->set('data.en_leadership.headline', 'Curated Leadership Shell')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'about.leadership')->latest('id')->firstOrFail();

        $this->assertSame('Curated Leadership Shell', $draft->payload_json['translations']['en']['headline'] ?? null);
    }

    public function test_about_directorates_and_partnerships_workflow_drafts_do_not_leak_until_published(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach ([
            'about.directorates' => '/en/about/directorates',
            'about.directorates_staff' => '/en/about/directorates/staff',
            'about.partnerships' => '/en/about/partnerships',
        ] as $targetKey => $path) {
            $payload = $about->getEditablePayload($targetKey);
            $payload['translations']['en']['headline'] = 'Published '.$targetKey;
            $payload['translations']['ar']['headline'] = 'منشور '.$targetKey;

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);

            $this->get($path)
                ->assertOk()
                ->assertDontSee('Published '.$targetKey);

            $this->assertTrue($workflow->publish($targetKey, (int) $author->id));

            $this->get($path)
                ->assertOk()
                ->assertSee('Published '.$targetKey);
        }
    }

    public function test_about_directorates_and_partnerships_previews_render_draft_snapshots(): void
    {
        $about = app(AboutPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        foreach (['about.directorates', 'about.directorates_staff', 'about.partnerships'] as $targetKey) {
            $payload = $about->getEditablePayload($targetKey);
            $payload['translations']['en']['headline'] = 'Preview '.$targetKey;
            $payload['translations']['ar']['headline'] = 'معاينة '.$targetKey;

            $workflow->saveDraft($targetKey, $payload, (int) $author->id);
            $preview = $workflow->preview($targetKey, 'en', (int) $author->id);

            $this->get($preview->previewUrl)
                ->assertOk()
                ->assertSee('Preview '.$targetKey)
                ->assertSee('Preview mode');
        }
    }

    public function test_manage_about_directorates_and_partnerships_use_curated_shell_editors(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        foreach (['about.directorates' => 'en_directorates', 'about.directorates_staff' => 'en_directorates_staff', 'about.partnerships' => 'en_partnerships'] as $targetKey => $stateKey) {
            Livewire::test(ManageAbout::class)
                ->set('data.target_key', $targetKey)
                ->call('loadTarget', $targetKey)
                ->assertSee('Hero')
                ->set('data.'.$stateKey.'.headline', 'Curated '.$targetKey)
                ->call('save');

            $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();

            $this->assertSame('Curated '.$targetKey, $draft->payload_json['translations']['en']['headline'] ?? null);
        }
    }
}
