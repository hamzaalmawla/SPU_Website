<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ResearchPublicationResource;
use App\Filament\Resources\ResearchPublicationResource\Pages\CreateResearchPublication;
use App\Filament\Resources\ResearchPublicationResource\Pages\EditResearchPublication;
use App\Filament\Resources\ResearchPublicationResource\Pages\ListResearchPublications;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Research\LegacyResearchFileReference;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ResearchPublicationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private int $legacyId = 9000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }

    public function test_imported_publications_are_listed_a_page_at_a_time_and_searchable_in_the_database(): void
    {
        $publications = collect(range(1, 30))->map(fn (int $index): ResearchPublication => $this->legacyPublication('medicine', 'Imported cohort study '.$index));
        $needle = $this->legacyPublication('pharmacy', 'Distinctive Stability Assay', authors: 'Rare Author Name');
        $this->actingAs($this->editor, 'web');

        $list = Livewire::test(ListResearchPublications::class)
            ->assertOk()
            ->assertCountTableRecords(31);
        $this->assertCount(25, $list->instance()->getTableRecords());

        Livewire::test(ListResearchPublications::class)
            ->searchTable('Rare Author')
            ->assertCanSeeTableRecords([$needle])
            ->assertCanNotSeeTableRecords($publications->all());

        Livewire::test(ListResearchPublications::class)
            ->filterTable('faculty', 'pharmacy')
            ->assertCanSeeTableRecords([$needle])
            ->assertCountTableRecords(1);
    }

    public function test_duplicates_held_back_by_the_import_are_listed_for_review_and_stay_private(): void
    {
        $published = $this->legacyPublication('medicine', 'Published imported study');
        $duplicate = $this->legacyPublication('medicine', 'Possible duplicate study', status: 'duplicate_review', enabled: false);
        $this->actingAs($this->editor, 'web');

        Livewire::test(ListResearchPublications::class)
            ->filterTable('status', 'needs_review')
            ->assertCanSeeTableRecords([$duplicate])
            ->assertCanNotSeeTableRecords([$published]);

        $this->assertSame('needs_review', ResearchPublicationResource::status($duplicate));
        $this->assertFalse($this->isPublic($duplicate, 'en'));

        $this->publish($duplicate, ['title_en' => 'Reviewed duplicate study']);

        $this->assertTrue($this->isPublic($duplicate->fresh(), 'en'));
        $this->get('/en/research/publications/'.$this->slug($duplicate->fresh()))
            ->assertOk()
            ->assertSee('Reviewed duplicate study');
    }

    public function test_editing_an_imported_publication_keeps_the_live_version_until_publish_and_keeps_its_url(): void
    {
        $publication = $this->legacyPublication('medicine', 'Original imported title', abstract: "Author:\nDr. Legacy Writer\nPublished in:\nJournal of Legacy\nAbstract:\nThe parsed abstract body.\nKeywords:\nalpha, beta");
        $originalSlug = $this->slug($publication);
        $this->actingAs($this->editor, 'web');

        $edit = Livewire::test(EditResearchPublication::class, ['record' => $publication->getRouteKey()])
            ->assertOk();
        $translations = array_values($edit->get('data.translations'));

        $this->assertSame('Dr. Legacy Writer', $translations[1]['authors']);
        $this->assertSame('Journal of Legacy', $translations[1]['publisher']);
        $this->assertSame('The parsed abstract body.', $translations[1]['abstract']);
        $this->assertSame(['alpha', 'beta'], $translations[1]['keywords']);

        $translations[1]['title'] = 'Edited English title';
        $translations[0]['title'] = 'عنوان عربي معدل';
        $edit->set('data.translations', $translations)->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('cms_drafts', ['target_key' => $this->targetKey($publication), 'status' => PublicationStatus::Draft->value]);
        $this->get('/en/research/publications/'.$originalSlug)->assertOk()->assertSee('Original imported title')->assertDontSee('Edited English title');

        $edit->callAction('publish')->assertHasNoActionErrors();

        $publication->refresh();
        $this->assertSame($originalSlug, $publication->slug);
        $this->get('/en/research/publications/'.$originalSlug)->assertOk()->assertSee('Edited English title')->assertSee('The parsed abstract body.');
        $this->get('/ar/research/publications/'.$originalSlug)->assertOk()->assertSee('عنوان عربي معدل')->assertSee('dir="rtl"', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.published']);

        $edit->callAction('unpublish');
        $this->get('/en/research/publications/'.$originalSlug)->assertNotFound();
    }

    public function test_draft_preview_renders_the_revision_without_publishing_it(): void
    {
        $publication = $this->legacyPublication('medicine', 'Previewed live title');
        $payload = $this->editedPayload($publication, ['title_en' => 'Previewed draft title']);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft($this->targetKey($publication), $payload, (int) $this->editor->getKey());

        $preview = $workflow->preview($this->targetKey($publication), 'en', (int) $this->editor->getKey());

        $this->get($preview->previewUrl)->assertOk()->assertSee('Previewed draft title');
        $this->get('/en/research/publications/'.$this->slug($publication))->assertOk()->assertDontSee('Previewed draft title');
    }

    public function test_a_new_publication_is_private_until_published(): void
    {
        $member = $this->member('medicine');
        $this->actingAs($this->editor, 'web');

        Livewire::test(CreateResearchPublication::class)
            ->fillForm([
                'translations' => [
                    ['locale' => 'ar', 'title' => 'بحث جديد في الطب'],
                    ['locale' => 'en', 'title' => 'New Medicine Research'],
                ],
                'faculty_member_id' => $member->getKey(),
                'publication_year' => 2026,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $publication = ResearchPublication::query()->whereNull('legacy_source_table')->latest('id')->firstOrFail();
        $this->assertFalse((bool) $publication->is_enabled);
        $this->assertSame(0, $publication->translations()->count());

        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish($this->targetKey($publication), (int) $this->editor->getKey()));

        $publication->refresh();
        $this->assertSame('new-medicine-research-'.$publication->getKey(), $publication->slug);
        $this->get('/en/research/publications/'.$publication->slug)->assertOk()->assertSee('New Medicine Research');
        $this->get('/en/faculties/medicine')->assertOk()->assertSee('New Medicine Research');
    }

    public function test_faculty_editors_manage_only_their_own_faculty_publications(): void
    {
        $own = $this->legacyPublication('medicine', 'Medicine owned study');
        $other = $this->legacyPublication('pharmacy', 'Pharmacy owned study');
        $facultyEditor = User::factory()->create(['role_slug' => 'faculty_editor', 'faculty_scopes' => ['medicine'], 'is_locked' => false]);
        $this->actingAs($facultyEditor, 'web');

        Livewire::test(ListResearchPublications::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        // Out-of-scope records are outside the list query, so they do not resolve at all.
        $this->get(ResearchPublicationResource::getUrl('edit', ['record' => $other]))->assertNotFound();

        $service = app(ResearchPublicationCmsServiceInterface::class);
        $this->assertNotSame([], $service->facultyMemberOptions((int) $facultyEditor->getKey()));
        $this->assertSame([], array_diff_key($service->facultyMemberOptions((int) $facultyEditor->getKey()), $this->memberIdsOf('medicine')));

        $this->expectException(AuthorizationException::class);
        $service->prepareDraft(new ResearchPublicationCmsDataDTO((int) $other->getKey(), $this->editedPayload($other, [])), (int) $facultyEditor->getKey());
    }

    public function test_deleting_a_publication_removes_it_from_the_site_and_from_faculty_picks(): void
    {
        $publication = $this->legacyPublication('pharmacy', 'Soon deleted study');
        $slug = $this->slug($publication);
        $this->get('/en/research/publications/'.$slug)->assertOk();

        $this->assertTrue(app(ResearchPublicationCmsServiceInterface::class)->deletePublication($this->targetKey($publication), (int) $this->editor->getKey()));

        $this->assertSoftDeleted($publication);
        $this->get('/en/research/publications/'.$slug)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'research_publication.deleted', 'entity_id' => $publication->getKey()]);
    }

    public function test_a_faculty_list_links_each_paper_file_and_filters_papers_without_one(): void
    {
        $withFile = $this->legacyPublication('medicine', 'Study with a paper file');
        $withoutFile = $this->legacyPublication('medicine', 'Study without a file');
        LegacyResearchFileReference::query()->create([
            'research_publication_id' => (int) $withFile->getKey(),
            'legacy_source_table' => 'jx_member_categories',
            'legacy_source_id' => (int) $withFile->legacy_source_id,
            'legacy_path' => 'images/research/study-with-file.pdf',
            'sort_order' => 0,
        ]);
        $this->actingAs($this->editor, 'web');

        Livewire::test(ListResearchPublications::class)
            ->filterTable('faculty', 'medicine')
            ->assertSee('study-with-file.pdf')
            ->assertSee(__('admin.research_publication.table.no_file'))
            ->filterTable('file', false)
            ->assertCanSeeTableRecords([$withoutFile])
            ->assertCanNotSeeTableRecords([$withFile])
            ->filterTable('file', true)
            ->assertCanSeeTableRecords([$withFile])
            ->assertCanNotSeeTableRecords([$withoutFile]);
    }

    public function test_selected_publications_are_deleted_together_and_each_deletion_is_audited(): void
    {
        $first = $this->legacyPublication('medicine', 'Wrong medicine study');
        $second = $this->legacyPublication('medicine', 'Another wrong medicine study');
        $kept = $this->legacyPublication('medicine', 'Correct medicine study');
        $this->actingAs($this->editor, 'web');

        Livewire::test(ListResearchPublications::class)
            ->filterTable('faculty', 'medicine')
            ->callTableBulkAction('delete_selected', [$first, $second])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($kept);
        $this->assertDatabaseHas('audit_logs', ['action' => 'research_publication.deleted', 'entity_id' => $first->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'research_publication.deleted', 'entity_id' => $second->getKey()]);
        $this->get('/en/research/publications/'.$this->slug($first))->assertNotFound();
        $this->get('/en/research/publications/'.$this->slug($kept))->assertOk();
    }

    public function test_a_bulk_delete_reaching_outside_the_users_faculties_deletes_nothing(): void
    {
        $own = $this->legacyPublication('medicine', 'Medicine study to delete');
        $other = $this->legacyPublication('pharmacy', 'Pharmacy study to keep');
        $facultyEditor = User::factory()->create(['role_slug' => 'faculty_editor', 'faculty_scopes' => ['medicine'], 'is_locked' => false]);

        try {
            app(ResearchPublicationCmsServiceInterface::class)->deletePublications([(int) $own->getKey(), (int) $other->getKey()], (int) $facultyEditor->getKey());
            $this->fail('A delete outside the faculty limit must be refused.');
        } catch (AuthorizationException) {
        }

        $this->assertNotSoftDeleted($own);
        $this->assertNotSoftDeleted($other);
    }

    /** @param array{title_en?: string} $changes */
    private function publish(ResearchPublication $publication, array $changes): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft($this->targetKey($publication), $this->editedPayload($publication, $changes), (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish($this->targetKey($publication), (int) $this->editor->getKey()));
    }

    /** @param array{title_en?: string} $changes @return array<string, mixed> */
    private function editedPayload(ResearchPublication $publication, array $changes): array
    {
        $payload = app(ResearchPublicationCmsServiceInterface::class)->getStoredData($this->targetKey($publication))?->payload ?? [];

        if (isset($changes['title_en'])) {
            $payload['translations']['en']['title'] = $changes['title_en'];
        }

        return app(ResearchPublicationCmsServiceInterface::class)
            ->prepareDraft(new ResearchPublicationCmsDataDTO((int) $publication->getKey(), $payload), (int) $this->editor->getKey())
            ->payload;
    }

    private function isPublic(ResearchPublication $publication, string $locale): bool
    {
        Cache::flush();

        return app(ResearchPageServiceInterface::class)->publication($locale, $this->slug($publication)) !== null;
    }

    private function slug(ResearchPublication $publication): string
    {
        if (is_string($publication->slug) && $publication->slug !== '') {
            return $publication->slug;
        }

        $title = (string) $publication->translations()->where('locale', 'en')->value('title');

        return Str::slug($title).'-'.$publication->legacy_source_id;
    }

    private function targetKey(ResearchPublication $publication): string
    {
        return 'entity.research-publication.'.$publication->getKey();
    }

    private function legacyPublication(string $facultySlug, string $title, string $status = 'published', bool $enabled = true, ?string $authors = null, ?string $abstract = null): ResearchPublication
    {
        $publication = ResearchPublication::query()->create([
            'faculty_member_id' => (int) $this->member($facultySlug)->getKey(),
            'published_at' => now()->subYear()->toDateString(),
            'publication_year' => 2024,
            'legacy_source_table' => 'jx_member_categories',
            'legacy_source_id' => ++$this->legacyId,
            'extraction_status' => $status,
            'is_enabled' => $enabled,
        ]);

        // The importer logs each row; the public slug suffix comes from this log.
        MigrationLog::query()->create([
            'module' => 'research',
            'batch_name' => 'test',
            'source_table' => 'jx_member_categories',
            'source_id' => $this->legacyId,
            'target_table' => 'research_publications',
            'target_id' => (int) $publication->getKey(),
            'status' => 'success',
        ]);

        foreach (['ar' => $title, 'en' => $title] as $locale => $localizedTitle) {
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => (int) $publication->getKey(),
                'locale' => $locale,
                'title' => $localizedTitle,
                'authors' => $authors,
                'abstract' => $abstract ?? 'Imported abstract.',
            ]);
        }

        Cache::flush();

        return $publication->fresh(['translations']);
    }

    private function member(string $facultySlug): FacultyMember
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $member = FacultyMember::query()->firstOrCreate(
            ['slug' => 'admin-test-researcher-'.$facultySlug],
            [
                'faculty_id' => (int) $faculty->getKey(),
                'is_enabled' => true,
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now()->subDay(),
            ],
        );

        if ($member->wasRecentlyCreated) {
            foreach (['ar' => 'باحث الإدارة', 'en' => 'Admin Researcher'] as $locale => $name) {
                FacultyMemberTranslation::query()->create(['faculty_member_id' => (int) $member->getKey(), 'locale' => $locale, 'full_name' => $name]);
            }
        }

        return $member;
    }

    /** @return array<int, true> */
    private function memberIdsOf(string $facultySlug): array
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();

        return FacultyMember::query()->where('faculty_id', $faculty->getKey())->pluck('id')->mapWithKeys(fn (mixed $id): array => [(int) $id => true])->all();
    }
}
