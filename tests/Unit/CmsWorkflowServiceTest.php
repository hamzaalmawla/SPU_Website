<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Exceptions\ConflictException;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Shared\PreviewToken;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CmsWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private CmsWorkflowServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CmsWorkflowServiceInterface::class);
    }

    public function test_draft_save_allows_incomplete_payload_and_tracks_versions(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);

        $first = $this->service->saveDraft('contact', ['translations' => ['ar' => ['title' => 'اتصل بنا']]], (int) $user->getKey());
        $second = $this->service->saveDraft('contact', ['translations' => ['ar' => ['title' => 'مرحبا']]], (int) $user->getKey(), $first->version);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(2, $this->service->latestEditableDraftVersion('contact', (int) $user->getKey()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.draft.saved']);
    }

    public function test_draft_save_rejects_stale_expected_version(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);

        $this->service->saveDraft('contact', ['translations' => ['ar' => ['title' => 'اتصل بنا']]], (int) $user->getKey());

        $this->expectException(ConflictException::class);

        $this->service->saveDraft('contact', ['translations' => ['ar' => ['title' => 'قديم']]], (int) $user->getKey(), 999);
    }

    public function test_publish_blocks_incomplete_payload_but_draft_save_succeeds(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);

        $this->service->saveDraft('contact', ['translations' => ['ar' => ['title' => 'اتصل بنا']]], (int) $user->getKey());

        $readiness = $this->service->readiness('contact');

        $this->assertFalse($readiness->isReady);

        $this->expectException(ValidationException::class);

        $this->service->publish('contact', (int) $user->getKey());
    }

    public function test_preview_token_carries_generic_cms_draft_snapshot(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = $this->completePayload('تواصل معنا', 'Contact us');

        $draft = $this->service->saveDraft('contact', $payload, (int) $user->getKey());
        $preview = $this->service->preview('contact', 'ar', (int) $user->getKey(), 'mobile');

        $token = PreviewToken::query()->where('target_type', 'cms')->first();

        $this->assertNotNull($token);
        $this->assertSame('contact', $preview->targetKey);
        $this->assertSame('mobile', $preview->device);
        $this->assertStringContainsString('/ar/preview?token=', $preview->previewUrl);
        $this->assertSame('contact', $token->payload_json['target_key'] ?? null);
        $this->assertSame($draft->id, $token->payload_json['draft_id'] ?? null);
        $this->assertSame('Contact us', $token->payload_json['payload']['translations']['en']['title'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.preview.created']);
    }

    public function test_publish_writes_published_snapshot_and_invalidates_generic_preview_tokens(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = $this->completePayload('تواصل معنا', 'Contact us');

        $this->service->saveDraft('contact', $payload, (int) $user->getKey());
        $this->service->preview('contact', 'ar', (int) $user->getKey());

        $this->assertTrue($this->service->publish('contact', (int) $user->getKey()));

        $this->assertSame($payload, $this->service->getPublishedPayload('contact'));
        $this->assertDatabaseHas(CmsTargetContent::class, [
            'target_key' => 'contact',
            'status' => PublicationStatus::Published->value,
        ]);
        $this->assertDatabaseHas(CmsDraft::class, [
            'target_key' => 'contact',
            'status' => PublicationStatus::Published->value,
        ]);
        $this->assertSame(0, PreviewToken::query()->where('target_type', 'cms')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.published']);
    }

    public function test_schedule_and_publish_due_scheduled_targets(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = $this->completePayload('الخدمات الإلكترونية', 'E-services');

        $this->service->saveDraft('e_services', $payload, (int) $user->getKey());
        $this->assertTrue($this->service->schedule('e_services', now()->addMinute(), (int) $user->getKey()));

        CmsDraft::query()->where('target_key', 'e_services')->update(['scheduled_at' => now()->subMinute()]);

        $this->assertSame(1, $this->service->publishDueScheduled());
        $this->assertSame($payload, $this->service->getPublishedPayload('e_services'));
    }

    public function test_saving_a_new_draft_preserves_the_scheduled_release(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $scheduledPayload = $this->completePayload('تواصل مجدول', 'Scheduled contact');
        $workingPayload = $this->completePayload('تواصل جديد', 'Later draft');

        $scheduledDraft = $this->service->saveDraft('contact', $scheduledPayload, (int) $user->getKey());
        $this->service->schedule('contact', now()->addMinute(), (int) $user->getKey());
        $workingDraft = $this->service->saveDraft('contact', $workingPayload, (int) $user->getKey(), $scheduledDraft->version);

        $this->assertSame($scheduledDraft->version + 1, $workingDraft->version);
        $this->assertDatabaseHas(CmsDraft::class, [
            'id' => $scheduledDraft->id,
            'status' => PublicationStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas(CmsDraft::class, [
            'id' => $workingDraft->id,
            'status' => PublicationStatus::Draft->value,
        ]);

        CmsDraft::query()->whereKey($scheduledDraft->id)->update(['scheduled_at' => now()->subMinute()]);

        $this->assertSame(1, $this->service->publishDueScheduled());
        $this->assertSame($scheduledPayload, $this->service->getPublishedPayload('contact'));
        $this->assertSame($workingPayload, $this->service->latestEditableDraftPayload('contact', (int) $user->getKey()));
    }

    public function test_due_scheduled_target_is_not_published_after_approver_is_locked(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $draft = $this->service->saveDraft('contact', $this->completePayload('تواصل', 'Contact'), (int) $user->getKey());
        $this->service->schedule('contact', now()->addMinute(), (int) $user->getKey());
        CmsDraft::query()->whereKey($draft->id)->update(['scheduled_at' => now()->subMinute()]);
        $user->forceFill(['is_locked' => true])->save();

        $this->assertSame(0, $this->service->publishDueScheduled());
        $this->assertDatabaseHas(CmsDraft::class, [
            'id' => $draft->id,
            'status' => PublicationStatus::Scheduled->value,
        ]);
        $this->assertNull($this->service->getPublishedPayload('contact'));
    }

    public function test_unpublish_hides_published_payload(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);

        $this->service->saveDraft('contact', $this->completePayload('تواصل معنا', 'Contact us'), (int) $user->getKey());
        $this->service->publish('contact', (int) $user->getKey());

        $this->assertTrue($this->service->unpublish('contact', (int) $user->getKey()));
        $this->assertNull($this->service->getPublishedPayload('contact'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.unpublished']);
    }

    public function test_published_draft_is_not_returned_as_editable(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = $this->completePayload('تواصل معنا', 'Contact us');

        $this->service->saveDraft('contact', $payload, (int) $user->getKey());
        $this->assertTrue($this->service->publish('contact', (int) $user->getKey()));

        $this->assertNull($this->service->latestEditableDraftVersion('contact', (int) $user->getKey()));
        $this->assertNull($this->service->latestEditableDraftPayload('contact', (int) $user->getKey()));
    }

    public function test_research_centers_require_complete_matching_bilingual_catalogs(): void
    {
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.centers');

        $this->assertTrue($this->service->readiness('research.centers', $payload)->isReady);

        $payload['translations']['en']['items'][0]['slug'] = 'different-center-slug';
        $readiness = $this->service->readiness('research.centers', $payload);

        $this->assertFalse($readiness->isReady);
        $this->assertArrayHasKey('centers', $readiness->errors);
    }

    public function test_faculty_editor_cannot_manage_global_research_centers(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $this->expectException(AuthorizationException::class);

        $this->service->saveDraft(
            'research.centers',
            app(ResearchPageServiceInterface::class)->getEditablePayload('research.centers'),
            (int) $user->getKey(),
        );
    }

    public function test_research_project_and_theme_catalogs_require_matching_bilingual_invariants(): void
    {
        $research = app(ResearchPageServiceInterface::class);

        foreach (['research.projects', 'research.themes'] as $targetKey) {
            $payload = $research->getEditablePayload($targetKey);
            $this->assertTrue($this->service->readiness($targetKey, $payload)->isReady, $targetKey);

            $payload['translations']['en']['items'][0]['slug'] = 'mismatched-slug';
            $readiness = $this->service->readiness($targetKey, $payload);

            $this->assertFalse($readiness->isReady);
            $this->assertArrayHasKey(substr($targetKey, strlen('research.')), $readiness->errors);
        }
    }

    public function test_research_project_publish_rejects_invalid_relationships_but_draft_save_allows_them(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.projects');
        $payload['translations']['ar']['items'][0]['themeSlug'] = 'unknown-theme';
        $payload['translations']['en']['items'][0]['themeSlug'] = 'unknown-theme';

        $this->service->saveDraft('research.projects', $payload, (int) $user->getKey());
        $this->assertFalse($this->service->readiness('research.projects')->isReady);

        $this->expectException(ValidationException::class);
        $this->service->publish('research.projects', (int) $user->getKey());
    }

    public function test_faculty_editor_cannot_manage_global_project_or_theme_catalogs(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        foreach (['research.projects', 'research.themes'] as $targetKey) {
            try {
                $this->service->saveDraft(
                    $targetKey,
                    app(ResearchPageServiceInterface::class)->getEditablePayload($targetKey),
                    (int) $user->getKey(),
                );
                $this->fail('Faculty editor unexpectedly managed '.$targetKey);
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('cms_drafts', ['target_key' => $targetKey]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function completePayload(string $arabicTitle, string $englishTitle): array
    {
        return [
            'translations' => [
                'ar' => [
                    'title' => $arabicTitle,
                    'body' => 'محتوى عربي جاهز للنشر.',
                ],
                'en' => [
                    'title' => $englishTitle,
                    'body' => 'English content ready for publishing.',
                ],
            ],
        ];
    }
}
