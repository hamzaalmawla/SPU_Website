<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsDraft;
use App\Models\Content\Directorate;
use App\Models\Content\Partnership;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AboutEntityCmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private AboutEntityCmsServiceInterface $entities;

    private CmsWorkflowServiceInterface $workflow;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entities = app(AboutEntityCmsServiceInterface::class);
        $this->workflow = app(CmsWorkflowServiceInterface::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor']);
    }

    public function test_new_shells_are_drafts_and_dynamic_targets_resolve(): void
    {
        $registry = app(CmsTargetRegistryInterface::class);

        foreach ($this->payloads() as $type => $payload) {
            $prepared = $this->prepare($type, $payload);

            $this->assertNotNull($prepared->entityId);
            $this->assertSame('entity.'.$type.'.'.$prepared->entityId, $prepared->targetKey);
            $this->assertSame(PublicationStatus::Draft->value, $this->model($type, $prepared->entityId)->publication_status);
            $this->assertNull($this->model($type, $prepared->entityId)->published_at);
            $this->assertSame($prepared->targetKey, $registry->find((string) $prepared->targetKey)?->key);
        }

        $this->assertNull($registry->find('entity.person.999999'));
        $this->assertNull($registry->find('entity.unknown.1'));
    }

    public function test_person_draft_is_isolated_and_protected_preview_renders_snapshot(): void
    {
        $prepared = $this->prepare('person', $this->personPayload('Published Person'));
        $targetKey = (string) $prepared->targetKey;
        $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->get('/en/about/profile/person/workflow-person')->assertNotFound();
        $this->assertTrue($this->workflow->publish($targetKey, (int) $this->editor->getKey()));

        $person = Person::query()->with('translations')->findOrFail($prepared->entityId);
        $this->assertSame('Published Person', $person->translations->firstWhere('locale', 'en')?->name);
        $this->assertSame(1, Person::query()->public()->count());
        $this->get('/en/about/profile/person/workflow-person')
            ->assertOk()
            ->assertSee('Published Person');

        $draftPayload = $this->personPayload('Protected Draft Person');
        $updated = $this->entities->prepareDraft(
            new AboutEntityCmsDataDTO('person', $prepared->entityId, $draftPayload),
            (int) $this->editor->getKey(),
        );
        $this->workflow->saveDraft($targetKey, $updated->payload, (int) $this->editor->getKey());

        $person->refresh()->load('translations');
        $this->assertSame('Published Person', $person->translations->firstWhere('locale', 'en')?->name);
        $this->get('/en/about/profile/person/workflow-person')
            ->assertOk()
            ->assertSee('Published Person')
            ->assertDontSee('Protected Draft Person');

        $preview = $this->workflow->preview($targetKey, 'en', (int) $this->editor->getKey());
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Protected Draft Person')
            ->assertSee('Preview mode')
            ->assertSee('noindex,nofollow', false);

        $this->get('/en/preview?token=invalid-token')->assertNotFound();
    }

    public function test_faculty_member_aggregate_isolated_preview_and_public_visibility(): void
    {
        $prepared = $this->prepare('faculty-member', $this->facultyMemberPayload('Published Faculty Member'));
        $targetKey = (string) $prepared->targetKey;
        $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey());

        $this->get('/en/about/profile/faculty-member/workflow-faculty-member')->assertNotFound();
        $this->assertFalse(app(AboutPageServiceInterface::class)->getStaffDirectory('en')->items->contains(
            fn ($item): bool => $item->name === 'Published Faculty Member',
        ));

        $preview = $this->workflow->preview($targetKey, 'en', (int) $this->editor->getKey());
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Published Faculty Member')
            ->assertSee('PhD')
            ->assertSee('Preview mode');

        $this->assertTrue($this->workflow->publish($targetKey, (int) $this->editor->getKey()));
        $member = FacultyMember::query()->with(['translations', 'educations.translations'])->findOrFail($prepared->entityId);
        $this->assertSame(PublicationStatus::Published->value, $member->publication_status);
        $this->assertSame($prepared->payload['photo_media_id'], $member->photo_media_id);
        $this->assertSame($prepared->payload['cv_media_id'], $member->cv_media_id);
        $this->assertSame('PhD', $member->educations->first()?->translations->firstWhere('locale', 'en')?->degree);
        $this->get('/en/about/profile/faculty-member/workflow-faculty-member')
            ->assertOk()
            ->assertSee('Published Faculty Member')
            ->assertSee('PhD');
        $this->assertTrue(app(AboutPageServiceInterface::class)->getStaffDirectory('en')->items->contains(
            fn ($item): bool => $item->name === 'Published Faculty Member',
        ));

        $changed = $this->facultyMemberPayload('Protected Faculty Draft');
        $updated = $this->entities->prepareDraft(
            new AboutEntityCmsDataDTO('faculty-member', $prepared->entityId, $changed),
            (int) $this->editor->getKey(),
        );
        $this->workflow->saveDraft($targetKey, $updated->payload, (int) $this->editor->getKey());

        $this->assertSame('Published Faculty Member', $member->fresh()->translations->firstWhere('locale', 'en')?->full_name);
        $this->get('/en/about/profile/faculty-member/workflow-faculty-member')
            ->assertOk()
            ->assertSee('Published Faculty Member')
            ->assertDontSee('Protected Faculty Draft');
        $draftPreview = $this->workflow->preview($targetKey, 'en', (int) $this->editor->getKey());
        $this->get($draftPreview->previewUrl)
            ->assertOk()
            ->assertSee('Protected Faculty Draft');
    }

    public function test_faculty_editor_draft_and_preview_remain_record_scoped(): void
    {
        $payload = $this->facultyMemberPayload('Scoped Faculty Member');
        $prepared = $this->prepare('faculty-member', $payload);
        $targetKey = (string) $prepared->targetKey;
        $faculty = Faculty::query()->findOrFail($payload['faculty_id']);
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => $faculty->faculty_scope_slug,
            'is_locked' => false,
        ]);

        $scopedPayload = $prepared->payload;
        $scopedPayload['photo_media_id'] = null;
        $scopedPayload['cv_media_id'] = null;
        $this->workflow->saveDraft($targetKey, $scopedPayload, (int) $facultyEditor->getKey());
        $this->assertStringContainsString('/en/preview?token=', $this->workflow->preview($targetKey, 'en', (int) $facultyEditor->getKey())->previewUrl);
        $this->actingAs($facultyEditor)
            ->get('/admin/faculty-members/'.$prepared->entityId.'/edit')
            ->assertOk()
            ->assertSee('Preview AR')
            ->assertSee('Preview EN')
            ->assertDontSee('Publish')
            ->assertDontSee('Schedule')
            ->assertDontSee('Unpublish');

        $otherFaculty = Faculty::query()->create([
            'slug' => 'other-workflow-faculty',
            'public_slug' => 'other-workflow-faculty',
            'faculty_scope_slug' => 'other-workflow-faculty',
            'is_enabled' => true,
        ]);
        $outOfScope = $scopedPayload;
        $outOfScope['faculty_id'] = (int) $otherFaculty->getKey();
        $outOfScope['department_id'] = null;

        $this->expectException(AuthorizationException::class);
        $this->workflow->saveDraft($targetKey, $outOfScope, (int) $facultyEditor->getKey());
    }

    public function test_all_entity_types_publish_schedule_and_unpublish_through_shared_workflow(): void
    {
        foreach ($this->payloads() as $type => $payload) {
            $prepared = $this->prepare($type, $payload);
            $targetKey = (string) $prepared->targetKey;
            $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey());

            $this->assertTrue($this->workflow->schedule($targetKey, now()->addMinute(), (int) $this->editor->getKey()));
            $this->assertSame(PublicationStatus::Scheduled->value, $this->model($type, $prepared->entityId)->fresh()->publication_status);
            $this->assertSame(0, $this->publicQuery($type)->count());

            CmsDraft::query()->where('target_key', $targetKey)->update(['scheduled_at' => now()->subMinute()]);
            $this->assertSame(1, $this->workflow->publishDueScheduled());

            $published = $this->model($type, $prepared->entityId)->fresh();
            $this->assertSame(PublicationStatus::Published->value, $published->publication_status);
            $this->assertNotNull($published->published_at);
            $this->assertSame(1, $this->publicQuery($type)->count());
            $this->assertDatabaseHas('cms_target_contents', [
                'target_key' => $targetKey,
                'status' => PublicationStatus::Published->value,
            ]);

            $this->assertTrue($this->workflow->unpublish($targetKey, (int) $this->editor->getKey()));
            $this->assertSame(PublicationStatus::Draft->value, $published->fresh()->publication_status);
            $this->assertNull($published->fresh()->published_at);
            $this->assertSame(0, $this->publicQuery($type)->count());
        }
    }

    public function test_saving_after_schedule_supersedes_the_pending_release(): void
    {
        $prepared = $this->prepare('directorate', $this->directoratePayload('Scheduled Directorate'));
        $targetKey = (string) $prepared->targetKey;
        $first = $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->workflow->schedule($targetKey, now()->addMinute(), (int) $this->editor->getKey());

        $changed = $prepared->payload;
        $changed['translations']['en']['title'] = 'Replacement Draft';
        $this->workflow->saveDraft($targetKey, $changed, (int) $this->editor->getKey(), $first->version);
        CmsDraft::query()->where('target_key', $targetKey)->where('status', PublicationStatus::Scheduled->value)->update(['scheduled_at' => now()->subMinute()]);

        $this->assertSame(0, $this->workflow->publishDueScheduled());
        $this->assertSame(PublicationStatus::Draft->value, Directorate::query()->findOrFail($prepared->entityId)->publication_status);
        $this->assertDatabaseHas('cms_drafts', [
            'target_key' => $targetKey,
            'status' => PublicationStatus::Superseded->value,
            'version' => 1,
        ]);
    }

    public function test_schedule_validates_entity_payload_and_unpublish_cancels_pending_release(): void
    {
        $prepared = $this->prepare('person', $this->personPayload('Published Person'));
        $targetKey = (string) $prepared->targetKey;
        $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->workflow->publish($targetKey, (int) $this->editor->getKey());

        $invalid = $prepared->payload;
        unset($invalid['category']);
        $this->workflow->saveDraft($targetKey, $invalid, (int) $this->editor->getKey());

        try {
            $this->workflow->schedule($targetKey, now()->addMinute(), (int) $this->editor->getKey());
            $this->fail('Invalid entity content was scheduled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }

        $replacement = $this->entities->prepareDraft(
            new AboutEntityCmsDataDTO('person', $prepared->entityId, $this->personPayload('Scheduled Replacement')),
            (int) $this->editor->getKey(),
        );
        $version = $this->workflow->latestEditableDraftVersion($targetKey);
        $this->workflow->saveDraft($targetKey, $replacement->payload, (int) $this->editor->getKey(), $version);
        $this->workflow->schedule($targetKey, now()->addMinute(), (int) $this->editor->getKey());

        $this->assertSame(PublicationStatus::Published->value, Person::query()->findOrFail($prepared->entityId)->publication_status);
        $this->assertTrue($this->workflow->unpublish($targetKey, (int) $this->editor->getKey()));
        $this->assertDatabaseHas('cms_drafts', [
            'target_key' => $targetKey,
            'status' => PublicationStatus::Draft->value,
            'scheduled_at' => null,
        ]);

        CmsDraft::query()->where('target_key', $targetKey)->update(['scheduled_at' => now()->subMinute()]);
        $this->assertSame(0, $this->workflow->publishDueScheduled());
        $this->assertSame(PublicationStatus::Draft->value, Person::query()->findOrFail($prepared->entityId)->publication_status);
    }

    public function test_filament_editors_expose_the_shared_workflow_actions(): void
    {
        $routes = [];
        foreach ($this->payloads() as $type => $payload) {
            $prepared = $this->prepare($type, $payload);
            $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
            $routes[] = match ($type) {
                'person' => '/admin/people/'.$prepared->entityId.'/edit',
                'faculty-member' => '/admin/faculty-members/'.$prepared->entityId.'/edit',
                'directorate' => '/admin/directorates/'.$prepared->entityId.'/edit',
                'partnership' => '/admin/partnerships/'.$prepared->entityId.'/edit',
            };
        }

        $this->actingAs($this->editor);
        foreach ($routes as $route) {
            $this->get($route)
                ->assertOk()
                ->assertSee('Preview AR')
                ->assertSee('Preview EN')
                ->assertSee('Publish')
                ->assertSee('Schedule')
                ->assertSee('Unpublish');
        }
    }

    /** @param array<string, mixed> $payload */
    private function prepare(string $type, array $payload): AboutEntityCmsDataDTO
    {
        return $this->entities->prepareDraft(
            new AboutEntityCmsDataDTO($type, null, $payload),
            (int) $this->editor->getKey(),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function payloads(): array
    {
        return [
            'person' => $this->personPayload('Workflow Person'),
            'faculty-member' => $this->facultyMemberPayload('Workflow Faculty Member'),
            'directorate' => $this->directoratePayload('Workflow Directorate'),
            'partnership' => $this->partnershipPayload('Workflow Partnership'),
        ];
    }

    /** @return array<string, mixed> */
    private function personPayload(string $englishName): array
    {
        return [
            'slug' => 'workflow-person',
            'category' => 'rector',
            'is_enabled' => true,
            'translations' => [
                ['locale' => 'ar', 'name' => 'شخصية سير العمل', 'role' => 'رئيس الجامعة', 'bio' => 'سيرة عربية.'],
                ['locale' => 'en', 'name' => $englishName, 'role' => 'Rector', 'bio' => 'English biography.'],
            ],
            'educations' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function directoratePayload(string $englishTitle): array
    {
        return [
            'slug' => 'workflow-directorate',
            'is_enabled' => true,
            'translations' => [
                ['locale' => 'ar', 'title' => 'مديرية سير العمل', 'description' => 'وصف عربي.'],
                ['locale' => 'en', 'title' => $englishTitle, 'description' => 'English description.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function facultyMemberPayload(string $englishName): array
    {
        $faculty = Faculty::query()->firstOrCreate(
            ['slug' => 'workflow-faculty'],
            [
                'public_slug' => 'workflow-faculty',
                'faculty_scope_slug' => 'workflow-faculty',
                'is_enabled' => true,
            ],
        );
        $faculty->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'كلية سير العمل']);
        $faculty->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Workflow Faculty']);
        $department = Department::query()->firstOrCreate(
            ['slug' => 'workflow-department'],
            ['faculty_id' => $faculty->getKey(), 'is_enabled' => true],
        );
        $department->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'قسم سير العمل']);
        $department->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Workflow Department']);
        $photo = $this->mediaAsset('workflow-profile.jpg', 'image/jpeg', 'image');
        $cv = $this->mediaAsset('workflow-profile.pdf', 'application/pdf', 'pdf');

        return [
            'slug' => 'workflow-faculty-member',
            'faculty_id' => (int) $faculty->getKey(),
            'department_id' => (int) $department->getKey(),
            'photo_media_id' => (int) $photo->getKey(),
            'cv_media_id' => (int) $cv->getKey(),
            'email' => 'faculty-workflow@example.test',
            'is_enabled' => true,
            'translations' => [
                ['locale' => 'ar', 'full_name' => 'عضو هيئة سير العمل', 'position' => 'مدرس', 'bio' => 'سيرة عربية.', 'specializations' => ['الذكاء الصنعي']],
                ['locale' => 'en', 'full_name' => $englishName, 'position' => 'Lecturer', 'bio' => 'English biography.', 'specializations' => ['Applied AI']],
            ],
            'educations' => [[
                'sort_order' => 0,
                'is_enabled' => true,
                'translations' => [
                    ['locale' => 'ar', 'degree' => 'دكتوراه', 'institution' => 'الجامعة السورية الخاصة'],
                    ['locale' => 'en', 'degree' => 'PhD', 'institution' => 'Syrian Private University'],
                ],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function partnershipPayload(string $englishName): array
    {
        return [
            'slug' => 'workflow-partnership',
            'category_key' => 'academic',
            'status_key' => 'active',
            'is_enabled' => true,
            'translations' => [
                ['locale' => 'ar', 'name' => 'شراكة سير العمل', 'description' => 'وصف عربي.'],
                ['locale' => 'en', 'name' => $englishName, 'description' => 'English description.'],
            ],
        ];
    }

    private function model(string $type, ?int $id): Person|FacultyMember|Directorate|Partnership
    {
        return match ($type) {
            'person' => Person::query()->findOrFail($id),
            'faculty-member' => FacultyMember::query()->findOrFail($id),
            'directorate' => Directorate::query()->findOrFail($id),
            'partnership' => Partnership::query()->findOrFail($id),
        };
    }

    private function publicQuery(string $type): mixed
    {
        return match ($type) {
            'person' => Person::query()->public(),
            'faculty-member' => FacultyMember::query()->public(),
            'directorate' => Directorate::query()->public(),
            'partnership' => Partnership::query()->public(),
        };
    }

    private function mediaAsset(string $filename, string $mimeType, string $mediaType): MediaAsset
    {
        $path = 'media/'.$mediaType.'/'.$filename;

        return MediaAsset::query()->firstOrCreate(['checksum' => hash('sha256', $path)], [
            'disk' => 'public',
            'directory' => dirname($path),
            'filename' => $filename,
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'size_bytes' => 100,
            'media_type' => $mediaType,
            'library_scope' => 'main',
            'metadata_status' => 'missing',
            'path' => $path,
        ]);
    }
}
