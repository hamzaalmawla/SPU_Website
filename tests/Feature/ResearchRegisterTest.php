<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\Contracts\Research\ResearchRegisterImportServiceInterface;
use App\DTOs\Research\ResearcherDataDTO;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ResearchPublicationResource\Pages\CreateResearchPublication;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\User\User;
use App\Services\Research\ResearchRegisterImportService;
use App\Support\PersonName;
use App\Support\ResearchPublicationSlug;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The research office's register fields (research number, publication and
 * study type, ISSN, researchers with roles) in the admin and on the site, and
 * the import of the register's papers.
 */
final class ResearchRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }

    public function test_a_paper_entered_with_register_fields_and_researchers_publishes_them(): void
    {
        $this->actingAs($this->editor, 'web');
        $cms = app(ResearchPublicationCmsServiceInterface::class);
        $lead = $cms->createResearcher(new ResearcherDataDTO('Dr. Lina Haddad', affiliation: 'Faculty of Medicine'), (int) $this->editor->getKey());
        $second = $cms->createResearcher(new ResearcherDataDTO('Omar Saleh'), (int) $this->editor->getKey());

        $this->assertSame([$lead => 'Dr. Lina Haddad (Faculty of Medicine)'], $cms->researcherOptions('Lina'));

        Livewire::test(CreateResearchPublication::class)
            ->fillForm([
                'translations' => [
                    ['locale' => 'ar', 'title' => 'دراسة سريرية جديدة'],
                    ['locale' => 'en', 'title' => 'A New Clinical Study'],
                ],
                'publication_type' => 'original_research',
                'study_type' => 'clinical',
                'issn' => '2168-8184',
                'external_url' => 'https://www.cureus.com/articles/1',
                'published_at' => '2026-05-01',
                'researchers' => [
                    ['researcher_id' => $second, 'role' => 'co_author'],
                    ['researcher_id' => $lead, 'role' => 'lead_author'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $publication = ResearchPublication::query()->latest('id')->firstOrFail();
        $this->assertTrue(app(CmsWorkflowServiceInterface::class)->publish('entity.research-publication.'.$publication->getKey(), (int) $this->editor->getKey()));

        $publication->refresh()->load('researchers');
        $this->assertMatchesRegularExpression('/^RES\d{4}001$/', (string) $publication->research_number);
        $this->assertSame(['original_research', 'clinical', '2168-8184'], [$publication->publication_type, $publication->study_type, $publication->issn]);
        $this->assertSame([[$second, 'co_author', 1], [$lead, 'lead_author', 2]], $publication->researchers
            ->map(fn (Researcher $researcher): array => [(int) $researcher->getKey(), $researcher->pivot->getAttribute('role'), (int) $researcher->pivot->getAttribute('sort_order')])->all());

        Cache::flush();
        $this->get('/en/research/publications/'.$publication->slug)
            ->assertOk()
            ->assertSee('Original research')
            ->assertSee('Clinical')
            ->assertSee('2168-8184')
            ->assertSee('Lead author')
            ->assertSeeInOrder(['Omar Saleh', 'Dr. Lina Haddad'])
            ->assertSee('Read the paper')
            ->assertSee('https://www.cureus.com/articles/1', false)
            ->assertDontSee('View on Google Scholar');
        $this->get('/ar/research/publications/'.$publication->slug)
            ->assertOk()
            ->assertSee('بحث أصيل')
            ->assertSee('المؤلف الرئيسي');
    }

    public function test_register_codes_are_validated_on_publish(): void
    {
        $publication = ResearchPublication::query()->create(['is_enabled' => false]);
        $targetKey = 'entity.research-publication.'.$publication->getKey();
        $errors = app(ResearchPublicationCmsServiceInterface::class)->publishErrors($targetKey, [
            'translations' => ['ar' => ['title' => 'عنوان'], 'en' => ['title' => 'Title']],
            'publication_type' => 'poster',
            'study_type' => 'guesswork',
            'researchers' => [['researcher_id' => 999999, 'role' => 'co_author']],
        ]);

        $this->assertArrayHasKey('publication_type', $errors);
        $this->assertArrayHasKey('study_type', $errors);
        $this->assertArrayHasKey('researchers', $errors);
    }

    public function test_the_register_import_adds_only_new_papers_and_can_run_again(): void
    {
        $medicine = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $member = FacultyMember::query()->create(['slug' => 'dr-sami-khoury', 'faculty_id' => $medicine->getKey(), 'is_enabled' => true, 'publication_status' => PublicationStatus::Published->value, 'published_at' => now()->subDay()]);
        $member->translations()->create(['locale' => 'en', 'full_name' => 'Dr. Sami Khoury']);
        $member->translations()->create(['locale' => 'ar', 'full_name' => 'د. سامي خوري']);
        $existing = ResearchPublication::query()->create(['is_enabled' => true, 'published_at' => '2020-01-01']);
        ResearchPublicationTranslation::query()->create(['research_publication_id' => $existing->getKey(), 'locale' => 'en', 'title' => 'A Paper The Site Already Has']);

        $path = $this->registerFile([
            $this->paper(1, 'RES2025001', 'A Paper The Site Already Has', [10]),
            $this->paper(2, 'RES2025002', 'Antibiotic Use Among Syrian Adults: A Cross-Sectional Study', [10, 11], '2024-03-01'),
            $this->paper(3, 'RES2025003', 'An Undated Case Report', [11], null),
        ]);
        $import = app(ResearchRegisterImportServiceInterface::class);

        $dryRun = $import->import($path, false, null);
        $this->assertSame([2, 1, 1], [$dryRun->created, $dryRun->alreadyOnSite, $dryRun->hiddenWithoutDate]);
        $this->assertSame(0, Researcher::query()->count());

        try {
            $import->import($path, true, 'wrong-token');
            $this->fail('The import wrote without its approval token.');
        } catch (InvalidArgumentException) {
        }

        $result = $import->import($path, true, ResearchRegisterImportService::APPROVAL_TOKEN);
        $this->assertSame([2, 2, 1], [$result->created, $result->researchersCreated, $result->researchersLinkedToStaff]);

        // The paper the site already had keeps its content and gains its researcher.
        $this->assertSame(['Sami Khoury'], $existing->refresh()->researchers->pluck('full_name')->all());
        $this->assertNull($existing->research_number);
        $this->assertSame([1, 1], [$result->existingPapersGivenResearchers, $result->authorLinksAdded]);

        $paper = ResearchPublication::query()->where('research_number', 'RES2025002')->with('researchers')->firstOrFail();
        $this->assertTrue((bool) $paper->is_enabled);
        $this->assertSame((int) $medicine->getKey(), (int) $paper->faculty_id);
        $this->assertSame(['Sami Khoury', 'Rana Aziz'], $paper->researchers->pluck('full_name')->all());
        $this->assertSame((int) $member->getKey(), (int) $paper->researchers->first()->faculty_member_id);
        $this->assertSame(['original_research', 'empirical'], [$paper->publication_type, $paper->study_type]);
        $this->assertFalse((bool) ResearchPublication::query()->where('research_number', 'RES2025003')->value('is_enabled'));

        Cache::flush();
        $this->get('/en/research/publications/'.$paper->slug)
            ->assertOk()
            ->assertSee('Antibiotic Use Among Syrian Adults')
            ->assertSee('href="/en/about/profile/dr-sami-khoury"', false)
            ->assertSee('Cureus');

        $again = $import->import($path, true, ResearchRegisterImportService::APPROVAL_TOKEN);
        $this->assertSame([0, 2, 0], [$again->created, $again->alreadyImported, $again->researchersCreated]);
        $this->assertSame(3, ResearchPublication::query()->count());
    }

    public function test_a_second_run_adds_links_and_faculties_without_overwriting_editors(): void
    {
        $dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
        $import = app(ResearchRegisterImportServiceInterface::class);
        $first = $this->registerFile([
            $this->paper(1, 'RES2025010', 'Orthodontic Pain After Separation', [11]),
            $this->paper(2, 'RES2025011', 'Essential Oil Activity Against Fungi', [11]),
        ]);
        $import->import($first, true, ResearchRegisterImportService::APPROVAL_TOKEN);

        // An editor sets one paper's faculty by hand before the second run.
        $edited = ResearchPublication::query()->where('research_number', 'RES2025011')->firstOrFail();
        $edited->forceFill(['faculty_id' => $pharmacy->getKey()])->save();

        $second = $this->registerFile([
            [...$this->paper(1, 'RES2025010', 'Orthodontic Pain After Separation', [11]), 'doi' => '10.1186/S40510-024-00001-1', 'doi_source' => 'Crossref (exact title, same ISSN)', 'faculty' => 'dentistry', 'faculty_reason' => 'subject: orthodontic'],
            [...$this->paper(2, 'RES2025011', 'Essential Oil Activity Against Fungi', [11]), 'faculty' => 'medicine', 'faculty_reason' => 'subject: clinical or health topic'],
        ]);
        $result = $import->import($second, true, ResearchRegisterImportService::APPROVAL_TOKEN);

        $this->assertSame([0, 1, 1, 1], [$result->created, $result->updated, $result->linksAdded, $result->facultiesAdded]);
        $paper = ResearchPublication::query()->where('research_number', 'RES2025010')->firstOrFail();
        $this->assertSame(['10.1186/s40510-024-00001-1', 'https://doi.org/10.1186/s40510-024-00001-1', (int) $dentistry->getKey()], [$paper->doi, $paper->external_url, (int) $paper->faculty_id]);
        $this->assertStringContainsString('Faculty suggested from subject: orthodontic.', (string) $paper->notes);
        $this->assertSame((int) $pharmacy->getKey(), (int) $edited->refresh()->faculty_id);
        $this->assertSame((int) $dentistry->getKey(), (int) Researcher::query()->where('full_name', 'Rana Aziz')->value('faculty_id'));

        Cache::flush();
        $this->get('/en/research/publications/'.$paper->slug)
            ->assertOk()
            ->assertSee('Read the paper')
            ->assertSee('https://doi.org/10.1186/s40510-024-00001-1', false)
            ->assertSee('Faculty of Dentistry');
    }

    public function test_the_duplicate_report_lists_people_entered_twice_and_changes_nothing(): void
    {
        $medicine = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        foreach (['dr-ramez-skerek' => 'Dr. Ramez Skerek', 'dr-ramez-slaiman-skerek' => 'Dr. Ramez Slaiman Skerek'] as $slug => $name) {
            FacultyMember::query()->create(['slug' => $slug, 'faculty_id' => $medicine->getKey(), 'is_enabled' => true, 'publication_status' => 'published'])
                ->translations()->create(['locale' => 'en', 'full_name' => $name]);
        }
        Researcher::query()->create(['full_name' => 'Saeed A Kadri']);
        Researcher::query()->create(['full_name' => 'Saeed Kadri']);
        $counts = [FacultyMember::query()->count(), Researcher::query()->count()];

        $this->artisan('people:duplicates')
            ->expectsOutputToContain('Dr. Ramez Slaiman Skerek')
            ->expectsOutputToContain('Saeed Kadri')
            ->assertSuccessful();

        $this->assertSame($counts, [FacultyMember::query()->count(), Researcher::query()->count()]);
    }

    public function test_person_names_match_without_titles_or_spelling_noise(): void
    {
        $this->assertTrue(PersonName::same('Assoc. Prof. Dr. Mouhib Al-Noukari', 'Mouhib Alnoukari'));
        $this->assertTrue(PersonName::same('أ.م.د. مهيب النقري', 'د. مهيب النقري'));
        $this->assertTrue(PersonName::same('Mhd Amin Alzabibi', 'Amin Alzabibi'));
        $this->assertFalse(PersonName::same('Dr. Ali', 'Prof. Ayman Ali'));
    }

    public function test_long_derived_slugs_are_cut_to_fit_and_keep_their_suffix(): void
    {
        $slug = ResearchPublicationSlug::storable(ResearchPublicationSlug::derive(str_repeat('very long title words ', 20), null, 515));

        $this->assertLessThanOrEqual(180, strlen($slug));
        $this->assertStringEndsWith('-515', $slug);
        $this->assertTrue(ResearchPublicationSlug::isValid($slug));
        $this->assertSame('short-title-7', ResearchPublicationSlug::storable('short-title-7'));
    }

    /** @param list<int> $researchers @return array<string, mixed> */
    private function paper(int $id, string $number, string $title, array $researchers, ?string $publishedAt = '2023-01-01'): array
    {
        return [
            'source_id' => $id,
            'research_number' => $number,
            'title' => $title,
            'publication_type' => 'original_research',
            'study_type' => 'empirical',
            'venue' => 'Cureus',
            'issn' => '2168-8184',
            'publication_info' => '2024 Mar 1; 16(3): e1',
            'published_at' => $publishedAt,
            'journal_rank' => 'Q2',
            'original_study_type' => 'Cross-sectional Study',
            'original_venue' => 'Cureus – peer-reviewed',
            'researchers' => $researchers,
        ];
    }

    /** @param list<array<string, mixed>> $papers */
    private function registerFile(array $papers): string
    {
        $path = storage_path('framework/testing/research-register-'.uniqid().'.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'researchers' => [
                ['source_id' => 10, 'full_name' => 'Sami Khoury', 'affiliation' => null, 'registration_status' => 'registered'],
                ['source_id' => 11, 'full_name' => 'Rana Aziz', 'affiliation' => 'Pharmacy', 'registration_status' => 'pending'],
            ],
            'papers' => $papers,
        ]));
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
