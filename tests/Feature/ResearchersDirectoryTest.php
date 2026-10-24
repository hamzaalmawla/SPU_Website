<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Resources\ResearcherResource\Pages\CreateResearcher;
use App\Filament\Resources\ResearcherResource\Pages\ListResearchers;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use App\Services\Research\ResearcherAdminService;
use App\Support\PersonName;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Researchers in the admin, the public researchers directory (which Expert
 * Finder now redirects to), and their papers on linked staff profiles.
 */
final class ResearchersDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Faculty $dentistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
    }

    public function test_editors_add_researchers_and_link_them_to_staff_from_a_suggestion(): void
    {
        $this->actingAs($this->editor, 'web');
        $member = $this->member('a-m-nzar-dahr', 'Asst. Prof. Nizar Daher');

        Livewire::test(CreateResearcher::class)
            ->fillForm([
                'full_name' => 'Nizar Aldaher',
                'faculty_id' => $this->dentistry->getKey(),
                'orcid_url' => 'https://orcid.org/0000-0002-0000-0001',
                'expertise' => ['Orthodontics'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $researcher = Researcher::query()->where('full_name', 'Nizar Aldaher')->firstOrFail();
        $this->assertSame(['Orthodontics'], $researcher->expertise);
        $this->assertSame([(int) $member->getKey()], array_keys(app(ResearcherAdminServiceInterface::class)->staffSuggestions((int) $researcher->getKey())));

        Livewire::test(ListResearchers::class)
            ->assertCanSeeTableRecords([$researcher])
            ->callTableAction('link_staff', $researcher, ['faculty_member_id' => $member->getKey()])
            ->assertHasNoTableActionErrors();

        $this->assertSame((int) $member->getKey(), (int) $researcher->refresh()->faculty_member_id);
    }

    public function test_everyone_with_a_paper_is_in_the_directory_and_staff_profiles_show_linked_papers(): void
    {
        $member = $this->member('d-rana-aziz', 'Dr. Rana Aziz');
        $linked = Researcher::query()->create(['full_name' => 'Rana Aziz', 'faculty_id' => $this->dentistry->getKey(), 'faculty_member_id' => $member->getKey()]);
        $external = Researcher::query()->create(['full_name' => 'Omar Haddad', 'faculty_id' => $this->dentistry->getKey(), 'expertise' => ['Endodontics']]);
        $paperless = Researcher::query()->create(['full_name' => 'Nobody Paperless']);
        $hidden = Researcher::query()->create(['full_name' => 'Hidden Researcher', 'is_enabled' => false]);
        $paper = $this->paper('Root Canal Outcomes in Syrian Adults');
        $paper->researchers()->attach([$linked->getKey() => ['role' => 'lead_author', 'sort_order' => 1], $external->getKey() => ['role' => 'co_author', 'sort_order' => 2], $hidden->getKey() => ['role' => 'co_author', 'sort_order' => 3]]);
        Cache::flush();

        $directory = $this->get('/en/research/researchers')->assertOk();
        $directory->assertSee('Omar Haddad')
            ->assertSee('/en/research/researchers/'.$external->publicSlug(), false)
            ->assertSee('/en/about/profile/d-rana-aziz', false)
            ->assertDontSee('Nobody Paperless')
            ->assertDontSee('Hidden Researcher');
        $this->get('/en/research/researchers?expertise=endodontics')->assertOk()->assertSee('Omar Haddad')->assertDontSee('Dr. Rana Aziz');
        $this->get('/en/research/researchers?faculty=pharmacy')->assertOk()->assertDontSee('Omar Haddad');

        $this->get('/en/research/researchers/'.$external->publicSlug())
            ->assertOk()
            ->assertSee('Omar Haddad')
            ->assertSee('/en/research/publications/'.$paper->slug, false);
        $this->get('/en/research/researchers/'.$linked->publicSlug())->assertRedirect('/en/about/profile/d-rana-aziz');
        $this->get('/en/research/researchers/'.$paperless->publicSlug())->assertNotFound();

        $this->get('/en/about/profile/d-rana-aziz')->assertOk()->assertSee('Root Canal Outcomes in Syrian Adults');
        $this->get('/ar/research/expert-finder?q=Omar')->assertRedirect('/ar/research/researchers?q=Omar');
    }

    public function test_clear_name_matches_are_linked_and_looser_ones_stay_suggestions(): void
    {
        $exact = $this->member('d-saeed-kadri', 'Dr. Saeed Kadri');
        $this->member('a-yousra-haddah', 'Prof. Yousra Haddah');
        $clear = Researcher::query()->create(['full_name' => 'Saeed Kadri']);
        $loose = Researcher::query()->create(['full_name' => 'Yousra Haddeh']);

        $service = app(ResearcherAdminServiceInterface::class);
        $this->assertCount(1, $service->linkClearMatches(false, null));
        $service->linkClearMatches(true, ResearcherAdminService::LINK_APPROVAL_TOKEN);

        $this->assertSame((int) $exact->getKey(), (int) $clear->refresh()->faculty_member_id);
        $this->assertNull($loose->refresh()->faculty_member_id);
        $this->assertTrue(PersonName::likelySame('Yousra Haddeh', 'Prof. Yousra Haddah'));
        $this->assertFalse(PersonName::likelySame('Omar Haddad', 'Ali Haddad'));
    }

    public function test_the_directory_search_finds_a_researcher_by_either_name_and_a_near_spelling(): void
    {
        $member = $this->member('d-ayman-khaled', 'Dr. Ayman Khaled');
        $member->translations()->where('locale', 'ar')->update(['full_name' => 'د. أيمن خالد']);
        $staff = Researcher::query()->create(['full_name' => 'Ayman Khaled', 'faculty_id' => $this->dentistry->getKey(), 'faculty_member_id' => $member->getKey()]);
        $other = Researcher::query()->create(['full_name' => 'Omar Haddad', 'faculty_id' => $this->dentistry->getKey()]);
        $this->paper('Implant Stability in Smokers')->researchers()->attach([
            $staff->getKey() => ['role' => 'lead_author', 'sort_order' => 1],
            $other->getKey() => ['role' => 'co_author', 'sort_order' => 2],
        ]);
        Cache::flush();

        // English typed on the Arabic site, Arabic on the English site.
        $this->get('/ar/research/researchers?q='.rawurlencode('Ayman Khaled'))->assertOk()->assertSee('د. أيمن خالد')->assertDontSee('Omar Haddad');
        $this->get('/en/research/researchers?q='.rawurlencode('أيمن خالد'))->assertOk()->assertSee('Dr. Ayman Khaled')->assertDontSee('Omar Haddad');
        // Another spelling, and words in any order.
        $this->get('/en/research/researchers?q='.rawurlencode('Aymen Khalid'))->assertOk()->assertSee('Dr. Ayman Khaled');
        $this->get('/en/research/researchers?q='.rawurlencode('haddad omar'))->assertOk()->assertSee('Omar Haddad')->assertDontSee('Dr. Ayman Khaled');
    }

    private function member(string $slug, string $name): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $this->dentistry->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $name]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $name]);

        return $member;
    }

    private function paper(string $title): ResearchPublication
    {
        $paper = ResearchPublication::query()->create([
            'slug' => Str::slug($title),
            'faculty_id' => $this->dentistry->getKey(),
            'is_enabled' => true,
            'published_at' => '2024-01-01',
            'publication_year' => 2024,
        ]);
        foreach (['ar', 'en'] as $locale) {
            $paper->translations()->create(['locale' => $locale, 'title' => $title]);
        }

        return $paper;
    }
}
