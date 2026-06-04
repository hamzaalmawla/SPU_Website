<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageSectionTranslationDTO;
use App\DTOs\NavigationActionDTO;
use App\Models\HomepageDraft;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use App\Models\PreviewToken;
use App\Models\User;
use App\Support\HomepagePayloadMapper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class HomepageCmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_get_sections_returns_only_fixed_homepage_keys_in_order_with_locale_payloads(): void
    {
        $sections = $this->homepageService()->getSections();

        $this->assertCount(11, $sections);
        $this->assertSame(
            HomepageSectionServiceInterface::SECTION_KEYS,
            $sections->pluck('key')->all(),
        );

        $hero = $sections->firstWhere('key', 'hero');

        $this->assertInstanceOf(HomepageSectionDTO::class, $hero);
        $this->assertSame('الجامعة السورية الخاصة', $hero->arabicPayload?->title);
        $this->assertSame('Syrian Private University', $hero->englishPayload?->title);

        $choosePath = $sections->firstWhere('key', 'choose_your_path');

        $this->assertInstanceOf(HomepageSectionDTO::class, $choosePath);
        $this->assertSame('اختر مسارك', $choosePath->arabicPayload?->title);
        $this->assertSame('Choose Your Path', $choosePath->englishPayload?->title);
        $this->assertCount(4, $choosePath->englishPayload?->items ?? []);
    }

    public function test_update_section_is_locale_specific_and_does_not_leak_publicly_before_publish(): void
    {
        $this->actingAs($this->author(), 'web');

        $updated = $this->homepageService()->updateSection(
            'hero',
            $this->validHeroPayload('en', 'Draft Homepage Hero'),
            'en',
        );

        $this->assertTrue($updated);

        $hero = $this->homepageService()->getSectionByKey('hero');

        $this->assertInstanceOf(HomepageSectionDTO::class, $hero);
        $this->assertSame('Draft Homepage Hero', $hero->englishPayload?->title);
        $this->assertSame('الجامعة السورية الخاصة', $hero->arabicPayload?->title);
        $this->assertSame('Syrian Private University', $this->heroTitleFromPublicHomepage('en'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.section_updated']);
    }

    public function test_draft_save_and_preview_token_work_without_public_leakage(): void
    {
        $this->actingAs($this->author(), 'web');

        $this->assertTrue(
            $this->homepageService()->updateSection('hero', $this->validHeroPayload('en', 'Preview Draft Hero'), 'en'),
        );

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $preview = $this->previewService()->createToken('homepage', null, 'en', $this->author()->id, 'mobile');

        $this->assertSame($draft->id, HomepageDraft::query()->latest('id')->value('id'));
        $this->assertTrue($this->previewService()->validateToken($preview->token));
        $this->assertSame('mobile', $preview->device);

        $storedPreview = PreviewToken::query()->latest('id')->firstOrFail();

        $this->assertSame(hash_hmac('sha256', $preview->token, (string) config('app.key')), $storedPreview->token_hash);
        $this->assertFalse(array_key_exists('token', $storedPreview->getAttributes()));

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Preview Draft Hero')
            ->assertSee('Preview mode');

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('Preview Draft Hero');

        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.draft_saved']);
    }

    public function test_save_draft_normalizes_fixed_sections_on_empty_homepage_install(): void
    {
        $this->actingAs($this->author(), 'web');

        HomepageSectionTranslation::query()->delete();
        HomepageSection::query()->delete();

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: []),
            $this->author()->id,
        );

        $this->assertCount(11, $draft->payload->homepage?->sections ?? []);
        $this->assertSame(
            HomepageSectionServiceInterface::SECTION_KEYS,
            array_map(static fn (HomepageSectionDTO $section): string => $section->key, $draft->payload->homepage?->sections ?? []),
        );
    }

    public function test_homepage_preview_token_stays_bound_to_original_draft_snapshot(): void
    {
        $this->actingAs($this->author(), 'web');

        $this->assertTrue(
            $this->homepageService()->updateSection('hero', $this->validHeroPayload('en', 'Snapshot Preview Hero'), 'en'),
        );

        $preview = $this->previewService()->createToken('homepage', null, 'en', $this->author()->id);

        $this->assertTrue(
            $this->homepageService()->updateSection('hero', $this->validHeroPayload('en', 'Later Homepage Draft'), 'en'),
        );

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Snapshot Preview Hero')
            ->assertDontSee('Later Homepage Draft');
    }

    public function test_preview_token_creation_rejects_unsupported_locale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->previewService()->createToken('homepage', null, 'fr', $this->author()->id);
    }

    public function test_publish_blocks_when_required_content_is_missing(): void
    {
        $sections = $this->homepageService()->getSections()->all();
        $hero = $this->replaceSection(
            $sections,
            'hero',
            function (HomepageSectionDTO $section): HomepageSectionDTO {
                $invalidEnglishPayload = new HomepageSectionDataDTO(
                    title: 'Broken hero',
                    subtitle: 'Missing required media path',
                    primaryAction: new NavigationActionDTO('Explore', '/en/faculties'),
                    secondaryAction: new NavigationActionDTO('Apply', '/en/admissions'),
                );

                return new HomepageSectionDTO(
                    id: $section->id,
                    key: $section->key,
                    sortOrder: $section->sortOrder,
                    isEnabled: $section->isEnabled,
                    payload: $section->payload,
                    arabicTranslation: $section->arabicTranslation,
                    englishTranslation: new HomepageSectionTranslationDTO(locale: 'en', headline: 'Broken hero', body: 'Missing required media path'),
                    arabicPayload: $section->arabicPayload,
                    englishPayload: $invalidEnglishPayload,
                );
            },
        );

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $hero),
            $this->author()->id,
        );

        $this->assertFalse($this->publishingService()->publish($draft->id, $this->author()->id));
        $this->assertSame('Syrian Private University', $this->heroTitleFromPublicHomepage('en'));
    }

    public function test_editable_draft_missing_section_action_recovers_from_published_payload(): void
    {
        $sections = HomepagePayloadMapper::serializeSections($this->homepageService()->getSections()->all());

        foreach ($sections as &$section) {
            if (! in_array($section['key'] ?? null, ['university_news', 'research_studies'], true)) {
                continue;
            }

            unset(
                $section['payload']['sectionAction'],
                $section['arabicPayload']['sectionAction'],
                $section['englishPayload']['sectionAction'],
            );
        }
        unset($section);

        HomepageDraft::forceCreate([
            'target_type' => 'homepage',
            'target_id' => null,
            'payload_json' => ['homepage' => ['sections' => $sections]],
            'status' => 'draft',
            'draft_notes' => 'Corrupted editor snapshot',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
            'approved_by' => null,
            'scheduled_at' => null,
            'published_at' => null,
        ]);

        $news = $this->homepageService()->getSectionByKey('university_news');
        $research = $this->homepageService()->getSectionByKey('research_studies');

        $this->assertSame('/en/news', $news?->englishPayload?->sectionAction?->url);
        $this->assertSame('/en/research', $research?->englishPayload?->sectionAction?->url);
    }

    public function test_homepage_draft_save_supersedes_older_editable_drafts(): void
    {
        $firstDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $secondDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
            $firstDraft->version,
        );

        $this->assertSame($firstDraft->version + 1, $secondDraft->version);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $firstDraft->id,
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $secondDraft->id,
            'status' => 'draft',
        ]);
    }

    public function test_save_after_publishing_latest_draft_does_not_conflict_with_superseded_drafts(): void
    {
        $firstDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $secondDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
            $firstDraft->version,
        );

        $this->assertTrue($this->publishingService()->publish($secondDraft->id, $this->author()->id));

        $nextDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
            $secondDraft->version,
        );

        $this->assertSame($secondDraft->version + 1, $nextDraft->version);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $firstDraft->id,
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $secondDraft->id,
            'status' => 'published',
        ]);
    }

    public function test_save_with_expected_published_version_ignores_older_editable_draft_residue(): void
    {
        $firstDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $secondDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
            $firstDraft->version,
        );

        $this->assertTrue($this->publishingService()->publish($secondDraft->id, $this->author()->id));

        HomepageDraft::query()
            ->whereKey($firstDraft->id)
            ->update(['status' => 'draft']);

        $nextDraft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
            $secondDraft->version,
        );

        $this->assertSame($secondDraft->version + 1, $nextDraft->version);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $firstDraft->id,
            'status' => 'superseded',
        ]);
    }

    public function test_publish_updates_public_homepage_invalidates_cache_and_writes_audit_logs(): void
    {
        $this->actingAs($this->author(), 'web');

        $this->get('/en')->assertOk()->assertHeader('X-Cache', 'BYPASS');

        Auth::guard('web')->logout();

        $this->get('/en')->assertOk()->assertHeader('X-Cache', 'MISS')->assertSee('Syrian Private University');
        $this->get('/en')->assertOk()->assertHeader('X-Cache', 'HIT');

        $this->actingAs($this->author(), 'web');
        $this->assertTrue(
            $this->homepageService()->updateSection('hero', $this->validHeroPayload('en', 'Published Homepage Hero'), 'en'),
        );

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $this->assertTrue($this->publishingService()->publish($draft->id, $this->author()->id));

        Auth::guard('web')->logout();

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertSee('Published Homepage Hero');

        $this->assertSame('Published Homepage Hero', $this->heroTitleFromPublicHomepage('en'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.publish']);
    }

    public function test_publish_sanitizes_homepage_payload_strings_recursively(): void
    {
        $sections = $this->homepageService()->getSections()->all();
        $updatedSections = $this->replaceSection(
            $sections,
            'hero',
            function (HomepageSectionDTO $section): HomepageSectionDTO {
                $payload = new HomepageSectionDataDTO(
                    eyebrow: 'Hero',
                    subtitle: 'Safe subtitle',
                    badge: 'Badge',
                    title: 'Safe title',
                    summary: '<p>Allowed</p><script>alert(1)</script>',
                    backgroundImageUrl: '/images/home/test-hero-en.jpg',
                    videoUrl: '/videos/home/test-hero-en.mp4',
                    primaryAction: new NavigationActionDTO('Explore', '/en/faculties'),
                    secondaryAction: new NavigationActionDTO('Apply', '/en/admissions'),
                    content: ['nested' => ['caption' => '<img src=x onerror=alert(1)>Caption']],
                );

                return new HomepageSectionDTO(
                    id: $section->id,
                    key: $section->key,
                    sortOrder: $section->sortOrder,
                    isEnabled: $section->isEnabled,
                    payload: $section->payload,
                    arabicTranslation: $section->arabicTranslation,
                    englishTranslation: $section->englishTranslation,
                    arabicPayload: $section->arabicPayload,
                    englishPayload: $payload,
                );
            },
        );

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $updatedSections),
            $this->author()->id,
        );

        $this->assertTrue($this->publishingService()->publish($draft->id, $this->author()->id));

        $stored = HomepageSectionTranslation::query()
            ->where('locale', 'en')
            ->whereHas('section', fn ($query) => $query->where('key', 'hero'))
            ->firstOrFail();

        $encodedPayload = json_encode($stored->payload_json, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('<script', $encodedPayload);
        $this->assertStringNotContainsString('onerror', $encodedPayload);
        $this->assertStringContainsString('Caption', $encodedPayload);
    }

    public function test_unpublish_removes_homepage_from_public_payload_and_logs_audit(): void
    {
        $this->assertTrue($this->publishingService()->unpublish('homepage', null, $this->author()->id));
        $this->assertSame([], $this->publicSectionKeys('en'));
        $this->get('/en')->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.unpublish']);
    }

    public function test_publish_after_unpublish_reenables_public_homepage(): void
    {
        $this->assertTrue($this->publishingService()->unpublish('homepage', null, $this->author()->id));
        $this->get('/en')->assertNotFound();

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        $this->assertTrue($this->publishingService()->publish($draft->id, $this->author()->id));
        $this->assertSame(HomepageSectionServiceInterface::SECTION_KEYS, $this->publicSectionKeys('en'));
        $this->get('/en')->assertOk()->assertSee('Syrian Private University');
    }

    public function test_schedule_publish_stores_intent_without_changing_public_state(): void
    {
        $this->actingAs($this->author(), 'web');

        $this->assertTrue(
            $this->homepageService()->updateSection('hero', $this->validHeroPayload('en', 'Scheduled Homepage Hero'), 'en'),
        );

        $draft = $this->publishingService()->saveDraft(
            new HomepageDraftDataDTO(sections: $this->homepageService()->getSections()->all()),
            $this->author()->id,
        );

        Auth::guard('web')->logout();

        $this->get('/en')->assertOk()->assertHeader('X-Cache', 'MISS');
        $this->get('/en')->assertOk()->assertHeader('X-Cache', 'HIT');

        $scheduled = $this->publishingService()->schedulePublish(
            $draft->id,
            now()->addDay(),
            $this->author()->id,
        );

        $this->assertTrue($scheduled);
        $this->assertDatabaseHas('homepage_drafts', [
            'id' => $draft->id,
            'status' => 'scheduled',
        ]);

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertDontSee('Scheduled Homepage Hero');

        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.schedule']);
    }

    private function homepageService(): HomepageSectionServiceInterface
    {
        return app(HomepageSectionServiceInterface::class);
    }

    private function publishingService(): HomepagePublishingServiceInterface
    {
        return app(HomepagePublishingServiceInterface::class);
    }

    private function previewService(): PreviewServiceInterface
    {
        return app(PreviewServiceInterface::class);
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    private function heroTitleFromPublicHomepage(string $locale): ?string
    {
        $hero = collect($this->homepageService()->getPublicHomepage($locale)->sections)->firstWhere('key', 'hero');

        return $hero instanceof HomepageSectionDTO ? $hero->payload->title : null;
    }

    /**
     * @return array<int, string>
     */
    private function publicSectionKeys(string $locale): array
    {
        return array_values(array_map(
            static fn (HomepageSectionDTO $section): string => $section->key,
            $this->homepageService()->getPublicHomepage($locale)->sections,
        ));
    }

    private function validHeroPayload(string $locale, string $title): HomepageSectionDataDTO
    {
        return new HomepageSectionDataDTO(
            eyebrow: $locale === 'ar' ? 'الجامعة الخاصة السورية' : 'Syrian Private University',
            subtitle: $locale === 'ar' ? 'عنوان فرعي محدث لتدفق النشر.' : 'Updated subheadline for publish workflow testing.',
            badge: $locale === 'ar' ? 'مسودة' : 'Draft',
            title: $title,
            summary: $locale === 'ar' ? 'محتوى تمهيدي لاختبار تدفق PX03.' : 'Starter content used to validate the PX03 workflow.',
            backgroundImageUrl: '/images/home/test-hero-'.$locale.'.jpg',
            videoUrl: '/videos/home/test-hero-'.$locale.'.mp4',
            primaryAction: new NavigationActionDTO(
                label: $locale === 'ar' ? 'استكشف' : 'Explore',
                url: '/'.$locale.'/faculties',
            ),
            secondaryAction: new NavigationActionDTO(
                label: $locale === 'ar' ? 'قدّم الآن' : 'Apply now',
                url: '/'.$locale.'/admissions',
            ),
            content: [
                'overlay' => ['style' => 'dark-gradient', 'opacity' => '70'],
                'alignment' => ['desktop' => 'start', 'mobile' => 'center'],
                'imageAlt' => $locale === 'ar' ? 'صورة اختبارية للواجهة الرئيسية' : 'Test hero image',
            ],
        );
    }

    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     * @return array<int, HomepageSectionDTO>
     */
    private function replaceSection(array $sections, string $key, callable $callback): array
    {
        return array_map(
            fn (HomepageSectionDTO $section): HomepageSectionDTO => $section->key === $key ? $callback($section) : $section,
            $sections,
        );
    }
}
