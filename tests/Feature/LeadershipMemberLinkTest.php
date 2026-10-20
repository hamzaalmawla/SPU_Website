<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Content\PeopleMergeServiceInterface;
use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Resources\FacultyMemberResource\Pages\EditFacultyMember;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A dean with an empty leadership profile who sent a full faculty member
 * profile through the staff intake form: linking the two makes the full
 * profile what the dean's page shows everywhere, papers included.
 */
final class LeadershipMemberLinkTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $faculty;

    private Person $dean;

    private FacultyMember $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->faculty = Faculty::query()->where('public_slug', 'artificial-intelligence')->firstOrFail();
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();

        $this->dean = Person::query()->create([
            'slug' => 'test-dean-leadership',
            'category' => 'dean',
            'faculty_scope_slug' => 'artificial-intelligence',
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $this->dean->translations()->create(['locale' => 'en', 'name' => 'Dr. Zayd Testdean', 'role' => 'Dean of the Faculty', 'bio' => 'Short old text.']);
        $this->dean->translations()->create(['locale' => 'ar', 'name' => 'د. زيد تستدين', 'role' => 'عميد الكلية', 'bio' => 'نص قديم قصير.']);

        $this->member = FacultyMember::query()->create([
            'slug' => 'zayd-testdean',
            'faculty_id' => $this->faculty->getKey(),
            'email' => 'zayd@spu.edu.sy',
            'social_links' => ['orcid' => 'https://orcid.org/0000-0002-0000-0001'],
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $this->member->translations()->create(['locale' => 'en', 'full_name' => 'Prof. Zayd Testdean', 'title' => 'Professor', 'position' => 'Professor', 'bio' => 'A full biography sent through the intake form.', 'specializations' => ['Data Mining']]);
        $this->member->translations()->create(['locale' => 'ar', 'full_name' => 'أ. د. زيد تستدين', 'title' => 'أستاذ', 'position' => 'أستاذ', 'bio' => 'نبذة كاملة أرسلت عبر النموذج.', 'specializations' => ['التنقيب في البيانات']]);
        $education = $this->member->educations()->create(['sort_order' => 1, 'is_enabled' => true]);
        $education->translations()->create(['locale' => 'en', 'degree' => 'PhD in Computer Science', 'institution' => 'Test University']);
        $education->translations()->create(['locale' => 'ar', 'degree' => 'دكتوراه في علوم الحاسب', 'institution' => 'جامعة الاختبار']);
    }

    public function test_the_leadership_profile_shows_the_linked_members_content_and_papers(): void
    {
        $paper = $this->paper('Mining Test Data at Scale');
        $researcher = Researcher::query()->create(['full_name' => 'Zayd TESTDEAN']);
        $researcher->publications()->attach($paper->getKey(), ['role' => 'lead_author', 'sort_order' => 1]);

        app(PeopleMergeServiceInterface::class)->linkFacultyMember((int) $this->member->getKey(), (int) $this->dean->getKey(), (int) $this->admin->getKey());

        $this->assertSame((int) $this->dean->getKey(), (int) $this->member->refresh()->person_id);
        $this->assertTrue($this->dean->appointments()->where('type', 'faculty_member')->where('faculty_id', $this->faculty->getKey())->where('is_enabled', true)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'faculty_member.linked_to_person', 'entity_id' => $this->member->getKey()]);

        // The researcher is suggested for the member (spelling differs) and linking brings the paper.
        $researchers = app(ResearcherAdminServiceInterface::class);
        $this->assertArrayHasKey((int) $this->member->getKey(), $researchers->staffSuggestions((int) $researcher->getKey()));
        $researchers->linkToStaff((int) $researcher->getKey(), (int) $this->member->getKey(), (int) $this->admin->getKey());

        Cache::flush();
        $this->get('/en/about/profile/test-dean-leadership')
            ->assertOk()
            ->assertSee('Dr. Zayd Testdean')
            ->assertSee('Dean of the Faculty')
            ->assertSee('A full biography sent through the intake form.')
            ->assertDontSee('Short old text.')
            ->assertSee('PhD in Computer Science')
            ->assertSee('Data Mining')
            ->assertSee('orcid.org/0000-0002-0000-0001', false)
            ->assertSee('Mining Test Data at Scale');
        $this->get('/ar/about/profile/test-dean-leadership')
            ->assertOk()
            ->assertSee('نبذة كاملة أرسلت عبر النموذج.')
            ->assertSee('عميد الكلية');

        // The member's own address shows the same page, and the faculty lists them once.
        $this->get('/en/about/profile/zayd-testdean')->assertRedirect('/en/about/profile/test-dean-leadership');
        $members = (string) $this->get('/en/faculties/artificial-intelligence/members')->assertOk()->getContent();
        $this->assertStringContainsString('/en/about/profile/test-dean-leadership', $members);
        $this->assertStringNotContainsString('/en/about/profile/zayd-testdean', $members);
    }

    public function test_unlinking_restores_the_leadership_profiles_own_content(): void
    {
        $links = app(PeopleMergeServiceInterface::class);
        $links->linkFacultyMember((int) $this->member->getKey(), (int) $this->dean->getKey(), (int) $this->admin->getKey());
        $links->unlinkFacultyMember((int) $this->member->getKey(), (int) $this->admin->getKey());

        $this->assertNull($this->member->refresh()->person_id);
        $this->assertFalse($this->dean->appointments()->where('type', 'faculty_member')->where('is_enabled', true)->exists());

        Cache::flush();
        $this->get('/en/about/profile/test-dean-leadership')->assertOk()->assertSee('Short old text.');
        $this->get('/en/about/profile/zayd-testdean')->assertOk()->assertSee('A full biography sent through the intake form.');
    }

    public function test_only_users_who_may_edit_people_and_faculty_members_can_link(): void
    {
        $facultyEditor = User::factory()->create(['role_slug' => 'faculty_editor', 'faculty_scopes' => ['artificial-intelligence'], 'is_locked' => false]);

        $this->expectException(AuthorizationException::class);
        app(PeopleMergeServiceInterface::class)->linkFacultyMember((int) $this->member->getKey(), (int) $this->dean->getKey(), (int) $facultyEditor->getKey());
    }

    public function test_an_admin_links_from_the_faculty_member_editor(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(EditFacultyMember::class, ['record' => $this->member->getKey()])
            ->assertActionVisible('linkLeadership')
            ->assertActionHidden('unlinkLeadership')
            ->callAction('linkLeadership', ['person_id' => $this->dean->getKey()])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame((int) $this->dean->getKey(), (int) $this->member->refresh()->person_id);

        Livewire::test(EditFacultyMember::class, ['record' => $this->member->getKey()])
            ->assertActionHidden('linkLeadership')
            ->callAction('unlinkLeadership');

        $this->assertNull($this->member->refresh()->person_id);
    }

    private function paper(string $title): ResearchPublication
    {
        $paper = ResearchPublication::query()->create([
            'slug' => str($title)->slug()->toString(),
            'faculty_id' => $this->faculty->getKey(),
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
