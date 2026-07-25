<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Filament\Pages\ManageResearch;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminResearchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_research_workspace_uses_task_cards_and_clear_actions(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->get('/admin/manage-research?target=research.projects')
            ->assertOk()
            ->assertSee('ما المحتوى الذي تريد إدارته؟')
            ->assertSee('المشاريع البحثية الجارية والمنجزة')
            ->assertSee('حفظ المسودة')
            ->assertSee('حفظ ومعاينة العربية')
            ->assertSee('نشر الآن')
            ->assertSee('المحتوى العربي')
            ->assertSee('مقدمة المشاريع')
            ->assertSee('جارٍ')
            ->assertSee('dir="rtl"', false)
            ->assertSee('dir="ltr"', false)
            ->assertDontSee('Projects Hero')
            ->assertDontSee('Project Filters')
            ->assertDontSee('Project Slug')
            ->assertDontSee('Faculty Slug')
            ->assertDontSee('Theme Slug');
    }

    public function test_research_workspace_loads_the_requested_task(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::withQueryParams(['target' => 'research.projects'])
            ->test(ManageResearch::class)
            ->assertSet('data.target_key', 'research.projects');
    }

    public function test_faculty_editor_cannot_access_global_research_workspace(): void
    {
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
            'is_locked' => false,
        ]);

        $this->actingAs($facultyEditor, 'web')
            ->get('/admin/manage-research')
            ->assertForbidden();
    }

    public function test_preview_rejects_unsupported_locale_without_saving(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::withQueryParams(['target' => 'research.projects'])
            ->test(ManageResearch::class)
            ->call('openPreview', 'fr')
            ->assertNotified(__('admin.research_workspace.notifications.preview_failed'));

        $this->assertDatabaseCount('cms_drafts', 0);
    }

    public function test_all_research_targets_preserve_unrendered_legacy_payload_keys(): void
    {
        $user = $this->editor();
        $this->actingAs($user, 'web');
        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $paths = [
            'research.index' => 'translations.en.stats.0',
            'research.publications' => 'translations.en.items.0',
            'research.centers' => 'translations.en.items.0',
            'research.projects' => 'translations.en.items.0',
            'research.themes' => 'translations.en.items.0',
            'research.experts' => 'translations.en.researchers.0',
            'research.conferences' => 'translations.en.upcoming.0',
            'research.library' => 'translations.en.databases.0',
            'research.office' => 'translations.en.leadership.items.0',
            'research.policies' => 'translations.en.sections.0',
        ];

        foreach ($paths as $targetKey => $path) {
            $payload = $research->getEditablePayload($targetKey);
            $payload['legacyTopLevel'] = ['preserve' => $targetKey];
            data_set($payload, $path.'.legacyMarker', 'keep-'.$targetKey);
            $workflow->saveDraft($targetKey, $payload, (int) $user->getKey());

            Livewire::withQueryParams(['target' => $targetKey])
                ->test(ManageResearch::class)
                ->call('save');

            $saved = $workflow->latestEditableDraftPayload($targetKey, (int) $user->getKey());
            $this->assertSame($targetKey, data_get($saved, 'legacyTopLevel.preserve'), $targetKey);
            $this->assertSame('keep-'.$targetKey, data_get($saved, $path.'.legacyMarker'), $targetKey);
        }
    }

    public function test_expert_identity_expertise_and_conference_form_are_normalized_and_persisted(): void
    {
        $user = $this->editor();
        $this->actingAs($user, 'web');
        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);

        $experts = $research->getEditablePayload('research.experts');
        foreach (['ar', 'en'] as $locale) {
            $experts['translations'][$locale]['researchers'][0]['id'] = '';
            $experts['translations'][$locale]['researchers'][0]['slug'] = '';
            $experts['translations'][$locale]['researchers'][0]['facultyId'] = 'ai';
            $experts['translations'][$locale]['researchers'][0]['facultySlug'] = '';
            $experts['translations'][$locale]['researchers'][0]['expertiseSlugs'] = ['ai-machine-learning'];
        }
        $workflow->saveDraft('research.experts', $experts, (int) $user->getKey());

        Livewire::withQueryParams(['target' => 'research.experts'])
            ->test(ManageResearch::class)
            ->call('save');

        $savedExperts = $workflow->latestEditableDraftPayload('research.experts', (int) $user->getKey());
        $arabicExpert = $savedExperts['translations']['ar']['researchers'][0] ?? [];
        $englishExpert = $savedExperts['translations']['en']['researchers'][0] ?? [];
        $this->assertNotSame('', $arabicExpert['id'] ?? '');
        $this->assertSame($arabicExpert['id'] ?? null, $englishExpert['id'] ?? null);
        $this->assertSame($arabicExpert['slug'] ?? null, $englishExpert['slug'] ?? null);
        $this->assertSame('artificial-intelligence', $englishExpert['facultySlug'] ?? null);
        $this->assertSame(['ai-machine-learning'], $englishExpert['expertiseSlugs'] ?? null);

        $conferences = $research->getEditablePayload('research.conferences');
        foreach (['ar', 'en'] as $locale) {
            $conferences['translations'][$locale]['upcoming'][0]['formId'] = 'symposium-registration';
        }
        $workflow->saveDraft('research.conferences', $conferences, (int) $user->getKey());

        Livewire::withQueryParams(['target' => 'research.conferences'])
            ->test(ManageResearch::class)
            ->call('save');

        $savedConferences = $workflow->latestEditableDraftPayload('research.conferences', (int) $user->getKey());
        $this->assertSame('symposium-registration', $savedConferences['translations']['en']['upcoming'][0]['formId'] ?? null);
        $this->assertStringContainsString('event=', (string) ($savedConferences['translations']['en']['upcoming'][0]['registrationUrl'] ?? ''));
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
