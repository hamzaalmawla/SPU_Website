<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class FacultyResearchPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_published_picks_from_other_faculties_render_first_then_the_faculty_newest_fill_in_both_locales(): void
    {
        $pharmacyFirst = $this->createPublication('pharmacy', 'Picked Pharmacy Stability Study', 'دراسة ثبات صيدلانية مختارة');
        $dentistrySecond = $this->createPublication('dentistry', 'Picked Dental Imaging Review', 'مراجعة تصوير أسنان مختارة');
        $medicineOwn = $this->createPublication('medicine', 'Own Medicine Cohort Study', 'دراسة أترابية طبية خاصة', now()->toDateString());

        $this->publishFacultyPicks('medicine', [$pharmacyFirst['slug'], $dentistrySecond['slug']]);

        foreach (['en', 'ar'] as $locale) {
            $page = app(FacultyPageServiceInterface::class)->getFaculty('medicine', $locale);
            $this->assertNotNull($page);
            $cards = $page->content['latestResearch'];

            $this->assertCount(3, $cards);
            $this->assertSame([$pharmacyFirst['slug'], $dentistrySecond['slug']], [$cards[0]['slug'], $cards[1]['slug']]);
            $this->assertSame('medicine', $cards[2]['facultySlug']);
            $this->assertSame($pharmacyFirst[$locale], $cards[0]['title']);

            $html = $this->get('/'.$locale.'/faculties/medicine')
                ->assertOk()
                ->assertSee('id="latest-research"', false)
                ->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false)
                ->assertSee('/'.$locale.'/research/publications/'.$pharmacyFirst['slug'], false)
                ->assertSee('/'.$locale.'/research/publications/'.$dentistrySecond['slug'], false)
                ->getContent();

            $this->assertLessThan(strpos($html, $dentistrySecond[$locale]), strpos($html, $pharmacyFirst[$locale]));

            $this->get('/'.$locale.'/faculties/medicine/overview')
                ->assertOk()
                ->assertSee('id="overview-latest-research"', false)
                ->assertSee($pharmacyFirst[$locale])
                ->assertSee($dentistrySecond[$locale]);
        }

        $this->assertSame($medicineOwn['slug'], $cards[2]['slug']);
    }

    public function test_unpublished_disabled_and_deleted_picks_never_render(): void
    {
        $deleted = $this->createPublication('pharmacy', 'Deleted Picked Research', 'بحث مختار محذوف');
        $disabled = $this->createPublication('pharmacy', 'Disabled Picked Research', 'بحث مختار معطل');
        $scheduled = $this->createPublication('pharmacy', 'Scheduled Picked Research', 'بحث مختار مجدول', now()->addWeek()->toDateString());
        $kept = $this->createPublication('pharmacy', 'Kept Picked Research', 'بحث مختار باق');

        $this->publishFacultyPicks('medicine', [$deleted['slug'], $disabled['slug'], $scheduled['slug']]);
        ResearchPublication::query()->whereKey($deleted['id'])->delete();
        ResearchPublication::query()->whereKey($disabled['id'])->update(['is_enabled' => false]);
        Cache::flush();

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/faculties/medicine')
                ->assertOk()
                ->assertDontSee($deleted[$locale])
                ->assertDontSee($disabled[$locale])
                ->assertDontSee($scheduled[$locale])
                ->assertDontSee('/research/publications/'.$deleted['slug'], false)
                ->assertDontSee('/research/publications/'.$scheduled['slug'], false);
        }

        $this->publishFacultyPicks('medicine', [$kept['slug']]);
        $this->get('/en/faculties/medicine')->assertOk()->assertSee($kept['en']);
    }

    public function test_draft_picks_preview_without_leaking_until_published(): void
    {
        $picked = $this->createPublication('pharmacy', 'Draft Picked Research', 'بحث مختار في مسودة');
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $workflow->saveDraft('facilities.medicine', $this->payloadWithPicks('medicine', [$picked['slug']]), (int) $author->id);

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/faculties/medicine')->assertOk()->assertDontSee($picked[$locale]);

            $preview = $workflow->preview('facilities.medicine', $locale, (int) $author->id);
            $this->get($preview->previewUrl)->assertOk()->assertSee($picked[$locale]);
        }

        $this->assertTrue($workflow->publish('facilities.medicine', (int) $author->id));

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/faculties/medicine')->assertOk()->assertSee($picked[$locale]);
        }
    }

    public function test_faculty_editor_saves_picks_of_any_faculty_research_as_references_in_both_locales(): void
    {
        $picked = $this->createPublication('pharmacy', 'Editor Picked Pharmacy Research', 'بحث صيدلة اختاره المحرر');
        $editor = User::factory()->create(['role_slug' => 'faculty_editor', 'faculty_scope_slug' => 'medicine']);
        $this->actingAs($editor, 'web');

        Livewire::test(ManageMedicineFaculty::class)
            ->call('loadTarget', 'facilities.medicine')
            ->assertSee(__('admin.faculty_workspace.editor.sections.selected_research'))
            ->set('data.selected_research', [['publication_slug' => $picked['slug']]])
            ->call('save')
            ->assertHasNoErrors();

        $draft = CmsDraft::query()->where('target_key', 'facilities.medicine')->latest('id')->firstOrFail();
        $payload = $draft->payload_json;

        foreach (['ar', 'en'] as $locale) {
            $this->assertSame([$picked['slug']], $payload['translations'][$locale]['selectedResearchSlugs']);
            $this->assertSame([], $payload['translations'][$locale]['latestResearch']);
        }

        $this->assertSame(1, ResearchPublication::query()->whereKey($picked['id'])->count());
        $this->assertSame(1, ResearchPublicationTranslation::query()->where('title', $picked['en'])->count());

        Livewire::test(ManageMedicineFaculty::class)
            ->call('loadTarget', 'facilities.medicine')
            ->assertSet('data.selected_research', fn (mixed $items): bool => is_array($items) && array_values($items) === [['publication_slug' => $picked['slug']]]);
    }

    public function test_picker_opens_on_newest_research_and_search_reaches_imported_publications(): void
    {
        $imported = $this->createPublication('pharmacy', 'Imported Legacy Bioavailability Study', 'دراسة توافر حيوي مستوردة', now()->subYears(6)->toDateString());
        ResearchPublication::query()->whereKey($imported['id'])->update(['legacy_source_table' => 'jx_member_categories', 'legacy_source_id' => 7001, 'extraction_status' => 'published']);
        Cache::flush();
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
        app()->setLocale('en');

        $page = Livewire::test(ManageMedicineFaculty::class)
            ->call('loadTarget', 'facilities.medicine')
            ->set('data.selected_research', ['item-1' => ['publication_slug' => null]]);

        $results = $page->instance()->getFormSelectSearchResults('data.selected_research.item-1.publication_slug', 'Bioavailability');
        $this->assertContains($imported['slug'], array_column($results, 'value'));
        $this->assertContains('Imported Legacy Bioavailability Study ('.now()->subYears(6)->format('Y').')', array_column($results, 'label'));

        $options = $page->instance()->getFormSelectOptions('data.selected_research.item-1.publication_slug');
        $this->assertNotSame([], $options);
    }

    public function test_old_manual_cards_keep_rendering_only_when_they_point_at_published_research(): void
    {
        $linked = $this->createPublication('pharmacy', 'Legacy Linked Research', 'بحث قديم مرتبط');
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.medicine');

        foreach (['en', 'ar'] as $locale) {
            unset($payload['translations'][$locale]['selectedResearchSlugs']);
            $payload['translations'][$locale]['latestResearch'] = [
                ['slug' => $linked['slug'], 'title' => 'Stale copied title '.$locale],
                ['title' => 'Copy without a publication '.$locale, 'url' => '/'.$locale.'/research'],
            ];
        }

        $workflow->saveDraft('facilities.medicine', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.medicine', (int) $author->id));

        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/faculties/medicine')
                ->assertOk()
                ->assertSee($linked[$locale])
                ->assertDontSee('Stale copied title '.$locale)
                ->assertDontSee('Copy without a publication '.$locale);
        }

        $this->actingAs($author, 'web');
        Livewire::test(ManageMedicineFaculty::class)
            ->call('loadTarget', 'facilities.medicine')
            ->assertSet('data.selected_research', fn (mixed $items): bool => is_array($items) && array_values($items) === [['publication_slug' => $linked['slug']]]);
    }

    public function test_publish_rejects_more_than_three_or_mismatched_picks(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $tooMany = $this->payloadWithPicks('medicine', ['a-1', 'b-2', 'c-3', 'd-4']);
        $this->assertFalse($workflow->readiness('facilities.medicine', $tooMany)->isReady);

        $mismatched = $this->payloadWithPicks('medicine', ['a-1']);
        $mismatched['translations']['ar']['selectedResearchSlugs'] = ['b-2'];
        $this->assertFalse($workflow->readiness('facilities.medicine', $mismatched)->isReady);

        $workflow->saveDraft('facilities.medicine', $tooMany, (int) $author->id);
        $this->expectException(ValidationException::class);
        $workflow->publish('facilities.medicine', (int) $author->id);
    }

    /** @param array<int, string> $slugs @return array<string, mixed> */
    private function payloadWithPicks(string $facultySlug, array $slugs): array
    {
        $payload = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.'.$facultySlug);

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['selectedResearchSlugs'] = $slugs;
        }

        return $payload;
    }

    /** @param array<int, string> $slugs */
    private function publishFacultyPicks(string $facultySlug, array $slugs): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $workflow->saveDraft('facilities.'.$facultySlug, $this->payloadWithPicks($facultySlug, $slugs), (int) $author->id);
        $this->assertTrue($workflow->publish('facilities.'.$facultySlug, (int) $author->id));
    }

    /** @return array{id: int, slug: string, en: string, ar: string} */
    private function createPublication(string $facultySlug, string $englishTitle, string $arabicTitle, ?string $publishedAt = null): array
    {
        $faculty = Faculty::query()->where('public_slug', $facultySlug)->firstOrFail();
        $member = FacultyMember::query()->firstOrCreate(
            ['slug' => 'picker-researcher-'.$facultySlug],
            [
                'faculty_id' => (int) $faculty->getKey(),
                'is_enabled' => true,
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now()->subDay(),
            ],
        );

        if ($member->wasRecentlyCreated) {
            foreach (['ar' => 'باحث الاختيار', 'en' => 'Picker Researcher'] as $locale => $name) {
                FacultyMemberTranslation::query()->create([
                    'faculty_member_id' => (int) $member->getKey(),
                    'locale' => $locale,
                    'full_name' => $name,
                    'position' => $locale === 'en' ? 'Researcher' : 'باحث',
                ]);
            }
        }

        $publication = ResearchPublication::query()->create([
            'faculty_member_id' => (int) $member->getKey(),
            'published_at' => $publishedAt ?? now()->subMonth()->toDateString(),
            'publication_year' => (int) now()->format('Y'),
            'is_enabled' => true,
        ]);

        foreach (['ar' => $arabicTitle, 'en' => $englishTitle] as $locale => $title) {
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => (int) $publication->getKey(),
                'locale' => $locale,
                'title' => $title,
                'excerpt' => $locale === 'en' ? 'Picker test summary.' : 'ملخص اختبار الاختيار.',
            ]);
        }

        Cache::flush();

        return [
            'id' => (int) $publication->getKey(),
            'slug' => Str::slug($englishTitle).'-'.$publication->getKey(),
            'en' => $englishTitle,
            'ar' => $arabicTitle,
        ];
    }
}
