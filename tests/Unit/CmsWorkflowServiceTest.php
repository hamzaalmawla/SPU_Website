<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Enums\PublicationStatus;
use App\Exceptions\ConflictException;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Shared\PreviewToken;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class CmsWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beginDatabaseTransaction(): void
    {
        // Each test gets a fresh schema from setUp instead.
    }

    private CmsWorkflowServiceInterface $service;

    protected function setUp(): void
    {
        // These tests deliberately exercise post-commit callbacks, so they
        // cannot run inside RefreshDatabase's outer transaction.
        RefreshDatabaseState::$migrated = false;
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

    public function test_publish_invalidates_cache_after_commit(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $cacheCalls = 0;
        $cache = Mockery::mock(CacheServiceInterface::class);
        $cache->shouldReceive('flushTags')
            ->once()
            ->with(['public-pages', 'public-shell', 'seo', 'sitemap', 'cms', 'cms:contact'])
            ->andReturnUsing(function () use (&$cacheCalls): bool {
                $cacheCalls++;
                $this->assertSame(0, DB::connection()->transactionLevel());
                $this->assertDatabaseHas('cms_target_contents', [
                    'target_key' => 'contact',
                    'status' => PublicationStatus::Published->value,
                ]);

                return true;
            });
        $cache->shouldReceive('forget')->times(8)->andReturn(true);

        $this->app->instance(CacheServiceInterface::class, $cache);
        $this->app->forgetInstance(CmsWorkflowServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);

        $workflow->saveDraft('contact', $this->completePayload('تواصل معنا', 'Contact us'), (int) $user->getKey());
        $workflow->publish('contact', (int) $user->getKey());

        $this->assertSame(1, $cacheCalls);

    }

    public function test_failed_publish_transaction_does_not_invalidate_cache(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $cache = Mockery::mock(CacheServiceInterface::class);
        $cache->shouldReceive('flushTags')->never();
        $cache->shouldReceive('flushAll')->never();
        $cache->shouldReceive('forget')->never();

        $about = Mockery::mock(AboutEntityCmsServiceInterface::class);
        $about->shouldReceive('authorizeTarget')->andReturn(true);
        $about->shouldReceive('publishErrors')->andReturn([]);
        $about->shouldReceive('markDraft')->andReturn(true);
        $about->shouldReceive('publishTarget')->andThrow(new \RuntimeException('publish failed'));

        $news = Mockery::mock(NewsArticleCmsServiceInterface::class);
        $news->shouldReceive('authorizeTarget')->andReturn(true);
        $news->shouldReceive('publishErrors')->andReturn([]);
        $news->shouldReceive('markDraft')->andReturn(true);

        $this->app->instance(CacheServiceInterface::class, $cache);
        $this->app->instance(AboutEntityCmsServiceInterface::class, $about);
        $this->app->instance(NewsArticleCmsServiceInterface::class, $news);
        $this->app->forgetInstance(CmsTargetRegistryInterface::class);
        $this->app->forgetInstance(CmsWorkflowServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);

        $workflow->saveDraft('contact', $this->completePayload('تواصل معنا', 'Contact us'), (int) $user->getKey());

        $this->expectException(\RuntimeException::class);

        try {
            $workflow->publish('contact', (int) $user->getKey());
        } finally {
            $this->assertDatabaseMissing('cms_target_contents', ['target_key' => 'contact']);
        }
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

    public function test_research_catalogs_reject_duplicate_or_mismatched_bilingual_ids(): void
    {
        $research = app(ResearchPageServiceInterface::class);
        $paths = [
            'research.publications' => 'items',
            'research.experts' => 'researchers',
            'research.conferences' => 'upcoming',
            'research.policies' => 'sections',
        ];

        foreach ($paths as $targetKey => $path) {
            $payload = $research->getEditablePayload($targetKey);
            data_set($payload, 'translations.en.'.$path.'.0.id', 'mismatched-english-id');
            $readiness = $this->service->readiness($targetKey, $payload);

            $this->assertFalse($readiness->isReady, $targetKey);
            $this->assertArrayHasKey('research', $readiness->errors, $targetKey);

            $items = data_get($payload, 'translations.ar.'.$path, []);
            if (is_array($items) && count($items) > 1) {
                data_set($payload, 'translations.ar.'.$path.'.1.id', data_get($payload, 'translations.ar.'.$path.'.0.id'));
                $this->assertFalse($this->service->readiness($targetKey, $payload)->isReady, $targetKey.' duplicate');
            }
        }
    }

    public function test_invalid_conference_registration_and_proceedings_states_block_publish(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = $this->validResearchConferencesPayload();
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['upcoming'][0]['formId'] = 'unsupported-registration';
            $payload['translations'][$locale]['past'][0]['hasProceedings'] = true;
            $payload['translations'][$locale]['past'][0]['proceedingsUrl'] = '#';
        }

        $this->service->saveDraft('research.conferences', $payload, (int) $user->getKey());
        $readiness = $this->service->readiness('research.conferences');
        $this->assertFalse($readiness->isReady);
        $this->assertStringContainsString('approved conference registration form', implode(' ', $readiness->errors['research'] ?? []));
        $this->assertStringContainsString('valid proceedings file', implode(' ', $readiness->errors['research'] ?? []));

        $this->expectException(ValidationException::class);
        $this->service->publish('research.conferences', (int) $user->getKey());
    }

    public function test_missing_or_placeholder_policy_documents_block_publish(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.policies');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['sections'][0]['documents'][0]['url'] = '#';
            $payload['translations'][$locale]['sections'][1]['documents'] = [];
        }

        $this->service->saveDraft('research.policies', $payload, (int) $user->getKey());
        $readiness = $this->service->readiness('research.policies');
        $this->assertFalse($readiness->isReady);
        $this->assertStringContainsString('valid file', implode(' ', $readiness->errors['research'] ?? []));
        $this->assertStringContainsString('at least one document', implode(' ', $readiness->errors['research'] ?? []));

        $this->expectException(ValidationException::class);
        $this->service->publish('research.policies', (int) $user->getKey());
    }

    public function test_unsafe_enabled_research_links_block_publish(): void
    {
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.publications');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['items'][0]['scholarUrl'] = 'javascript:alert(1)';
        }

        $readiness = $this->service->readiness('research.publications', $payload);
        $this->assertFalse($readiness->isReady);
        $this->assertStringContainsString('safe public HTTPS', implode(' ', $readiness->errors['research'] ?? []));
    }

    /** @return array<string, mixed> */
    private function validResearchConferencesPayload(): array
    {
        $payload = app(ResearchPageServiceInterface::class)->getEditablePayload('research.conferences');

        foreach (['ar', 'en'] as $locale) {
            foreach ($payload['translations'][$locale]['past'] ?? [] as $index => $conference) {
                if ((bool) ($conference['hasProceedings'] ?? false)) {
                    $payload['translations'][$locale]['past'][$index]['proceedingsUrl'] = '/storage/research/proceedings-'.$index.'.pdf';
                }
            }
        }

        return $payload;
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
