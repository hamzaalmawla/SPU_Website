<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Content\PeopleUnifyServiceInterface;
use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Resources\FacultyMemberResource\Pages\EditFacultyMember;
use App\Filament\Resources\PersonResource\Pages\EditPerson;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use App\Services\Content\PeopleUnifyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One profile per person: the faculty profile holds the name, photo and bio,
 * and a leadership row (dean, rector, ...) only adds the role. An edit to the
 * profile shows on the faculty, leadership and research pages alike.
 */
final class OnePersonProfileTest extends TestCase
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
        $this->dean->appointments()->create(['type' => 'faculty_member', 'faculty_id' => $this->faculty->getKey(), 'is_enabled' => true]);

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

    public function test_a_leader_is_shown_as_their_faculty_profile_with_the_role_on_top(): void
    {
        $this->dean->forceFill(['faculty_member_id' => $this->member->getKey()])->save();
        $paper = $this->paper('Mining Test Data at Scale');
        $researcher = Researcher::query()->create(['full_name' => 'Zayd TESTDEAN']);
        $researcher->publications()->attach($paper->getKey(), ['role' => 'lead_author', 'sort_order' => 1]);

        // The research register still suggests the profile from the name, and confirming it links the papers.
        $researchers = app(ResearcherAdminServiceInterface::class);
        $this->assertArrayHasKey((int) $this->member->getKey(), $researchers->staffSuggestions((int) $researcher->getKey()));
        $this->assertContains((int) $researcher->getKey(), $researchers->researchersWithSuggestions());
        $researchers->linkToStaff((int) $researcher->getKey(), (int) $this->member->getKey(), (int) $this->admin->getKey());

        Cache::flush();
        // The old leadership address goes to the one profile.
        $this->get('/en/about/profile/test-dean-leadership')->assertRedirect('/en/about/profile/zayd-testdean');
        $this->get('/en/about/profile/zayd-testdean')
            ->assertOk()
            ->assertSee('Prof. Zayd Testdean')
            ->assertSee('Dean of the Faculty')
            ->assertSee('A full biography sent through the intake form.')
            ->assertDontSee('Short old text.')
            ->assertSee('PhD in Computer Science')
            ->assertSee('Data Mining')
            ->assertSee('orcid.org/0000-0002-0000-0001', false)
            ->assertSee('Mining Test Data at Scale');
        $this->get('/ar/about/profile/zayd-testdean')
            ->assertOk()
            ->assertSee('نبذة كاملة أرسلت عبر النموذج.')
            ->assertSee('عميد الكلية');

        // The faculty lists them once, at the profile's address.
        $members = (string) $this->get('/en/faculties/artificial-intelligence/members')->assertOk()->getContent();
        $this->assertSame(1, substr_count($members, '/en/about/profile/zayd-testdean"'));
        $this->assertStringNotContainsString('/en/about/profile/test-dean-leadership', $members);
    }

    public function test_editing_the_profile_name_reaches_the_leadership_faculty_and_research_pages(): void
    {
        $this->dean->forceFill(['faculty_member_id' => $this->member->getKey()])->save();
        $paper = $this->paper('Graph Learning for Testing');
        $researcher = Researcher::query()->create(['full_name' => 'Zayd Testdean (as in the register)', 'faculty_member_id' => $this->member->getKey()]);
        $researcher->publications()->attach($paper->getKey(), ['role' => 'lead_author', 'sort_order' => 1]);

        $this->member->translations()->where('locale', 'en')->update(['full_name' => 'Prof. Zayd Renamed']);
        Cache::flush();

        $this->get('/en/about/leadership')->assertOk()->assertSee('Prof. Zayd Renamed')->assertDontSee('Dr. Zayd Testdean');
        $this->get('/en/faculties/artificial-intelligence/members')->assertOk()->assertSee('Prof. Zayd Renamed');
        $this->get('/en/research/publications/'.$paper->slug)->assertOk()->assertSee('Prof. Zayd Renamed')->assertDontSee('as in the register');
        $this->get('/en/research/researchers')->assertOk()->assertSee('Prof. Zayd Renamed')->assertDontSee('as in the register');
    }

    public function test_the_older_member_link_still_resolves_to_the_profile(): void
    {
        $this->member->forceFill(['person_id' => $this->dean->getKey()])->save();
        Cache::flush();

        $this->get('/en/about/profile/test-dean-leadership')->assertRedirect('/en/about/profile/zayd-testdean');
        $this->get('/en/about/profile/zayd-testdean')->assertOk()->assertSee('Dean of the Faculty')->assertSee('Prof. Zayd Testdean');
        $this->get('/en/about/leadership')->assertOk()->assertSee('Prof. Zayd Testdean');
    }

    public function test_a_leader_without_a_profile_still_shows_on_their_own(): void
    {
        Cache::flush();

        $this->get('/en/about/profile/test-dean-leadership')->assertOk()->assertSee('Dr. Zayd Testdean')->assertSee('Short old text.');
        $this->get('/en/about/profile/zayd-testdean')->assertOk()->assertSee('Prof. Zayd Testdean');
    }

    public function test_publishing_a_leadership_row_with_a_profile_keeps_only_the_role_and_clears_nothing(): void
    {
        $cms = app(AboutEntityCmsServiceInterface::class);
        $payload = $cms->getStoredData('entity.person.'.$this->dean->getKey())?->payload ?? [];
        $payload['faculty_member_id'] = $this->member->getKey();
        // The editor hides the name and biography once a profile is picked.
        unset($payload['translations']['en']['name'], $payload['translations']['en']['bio'], $payload['translations']['ar']['name'], $payload['translations']['ar']['bio']);
        $payload['translations']['en']['role'] = 'Dean of AI';
        unset($payload['photo_media_id'], $payload['email'], $payload['educations']);

        $this->assertSame([], $cms->publishErrors('entity.person.'.$this->dean->getKey(), $payload));
        $cms->publishTarget('entity.person.'.$this->dean->getKey(), $payload, now());

        $dean = $this->dean->refresh();
        $this->assertSame((int) $this->member->getKey(), (int) $dean->faculty_member_id);
        $english = $dean->translations()->where('locale', 'en')->firstOrFail();
        $this->assertSame('Dean of AI', $english->role);
        // The row's older copies are kept, not cleared.
        $this->assertSame('Dr. Zayd Testdean', $english->name);
        $this->assertSame('Short old text.', $english->bio);
        // The profile is untouched.
        $this->assertSame('Prof. Zayd Testdean', $this->member->translations()->where('locale', 'en')->value('full_name'));
    }

    public function test_the_leadership_editor_picks_a_profile_and_the_profile_editor_has_no_link_step(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(EditPerson::class, ['record' => $this->dean->getKey()])
            ->assertFormFieldExists('faculty_member_id')
            ->assertFormFieldIsVisible('translations.en.name')
            ->fillForm(['faculty_member_id' => $this->member->getKey()])
            ->assertFormFieldIsHidden('translations.en.name')
            ->assertFormFieldIsHidden('translations.en.bio')
            ->assertFormFieldIsVisible('translations.en.role');

        $this->dean->forceFill(['faculty_member_id' => $this->member->getKey()])->save();
        Livewire::test(EditFacultyMember::class, ['record' => $this->member->getKey()])
            ->assertActionDoesNotExist('linkLeadership')
            ->assertActionDoesNotExist('unlinkLeadership')
            ->assertActionVisible('leadershipRole');
    }

    public function test_people_unify_dry_run_changes_nothing(): void
    {
        $this->member->forceFill(['person_id' => $this->dean->getKey()])->save();
        $before = $this->member->translations()->orderBy('locale')->get(['locale', 'full_name', 'bio'])->toArray();

        $result = app(PeopleUnifyServiceInterface::class)->unify(false, null);

        $this->assertFalse($result->written);
        $this->assertSame(1, $result->linkedExisting);
        $this->assertNull($this->dean->refresh()->faculty_member_id);
        $this->assertSame($before, $this->member->translations()->orderBy('locale')->get(['locale', 'full_name', 'bio'])->toArray());
        $this->assertStringContainsString('differs (profile kept): en name', implode("\n", $result->lines));
    }

    public function test_people_unify_never_overwrites_faculty_member_data_and_only_fills_empty_fields(): void
    {
        Storage::fake('local');
        $this->member->forceFill(['person_id' => $this->dean->getKey(), 'phone' => null])->save();
        $this->member->translations()->where('locale', 'ar')->update(['bio' => null]);
        $this->dean->forceFill(['phone' => '+963 11 000 0000', 'email' => 'old-dean@spu.edu.sy'])->save();

        $result = app(PeopleUnifyServiceInterface::class)->unify(true, PeopleUnifyService::APPROVAL_TOKEN);

        $this->assertTrue($result->written);
        $this->assertSame((int) $this->member->getKey(), (int) $this->dean->refresh()->faculty_member_id);
        $member = $this->member->refresh();
        // Values the profile had are kept.
        $this->assertSame('zayd@spu.edu.sy', $member->email);
        $this->assertSame('Prof. Zayd Testdean', $member->translations()->where('locale', 'en')->value('full_name'));
        $this->assertSame('A full biography sent through the intake form.', $member->translations()->where('locale', 'en')->value('bio'));
        $this->assertSame(1, $member->educations()->count());
        // Empty ones are filled from the leadership row.
        $this->assertSame('+963 11 000 0000', $member->phone);
        $this->assertSame('نص قديم قصير.', $member->translations()->where('locale', 'ar')->value('bio'));
        // A snapshot of every row touched was saved first, and the run is audited.
        $this->assertNotNull($result->snapshotPath);
        $this->assertNotEmpty(Storage::disk('local')->files('private/people-unify'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'people.unified', 'entity_id' => $this->dean->getKey()]);
    }

    public function test_people_unify_leaves_drafts_and_probable_matches_for_an_editor(): void
    {
        // A pending draft on the profile: tied, but nothing copied into it.
        $this->member->forceFill(['person_id' => $this->dean->getKey(), 'phone' => null])->save();
        $this->dean->forceFill(['phone' => '+963 11 000 0000'])->save();
        CmsDraft::query()->create(['target_key' => 'entity.faculty-member.'.$this->member->getKey(), 'status' => PublicationStatus::Draft->value, 'payload_json' => [], 'created_by' => $this->admin->getKey(), 'updated_by' => $this->admin->getKey()]);

        // A leader whose name is probably an existing profile's: no second profile is made.
        $probable = Person::query()->create(['slug' => 'z-testdean', 'category' => 'council', 'is_enabled' => true, 'publication_status' => PublicationStatus::Published->value]);
        $probable->translations()->create(['locale' => 'en', 'name' => 'Zaid Testdeen', 'role' => 'Council member']);

        // A leader with no profile at all gets one made from the row.
        $rector = Person::query()->create(['slug' => 'test-rector', 'category' => 'rector', 'is_enabled' => true, 'publication_status' => PublicationStatus::Published->value, 'published_at' => now()->subDay()]);
        $rector->translations()->create(['locale' => 'en', 'name' => 'Prof. Rana Uniquename', 'role' => 'Rector', 'bio' => 'Rector biography.']);
        $rector->translations()->create(['locale' => 'ar', 'name' => 'أ. د. رنا اسم فريد', 'role' => 'رئيسة الجامعة']);

        Storage::fake('local');
        $result = app(PeopleUnifyServiceInterface::class)->unify(true, PeopleUnifyService::APPROVAL_TOKEN);

        $this->assertNull($this->member->refresh()->phone);
        $this->assertStringContainsString('unpublished draft', implode("\n", $result->lines));
        $this->assertNull($probable->refresh()->faculty_member_id);
        $this->assertStringContainsString('z-testdean', implode("\n", $result->skipped));
        $this->assertSame(1, FacultyMember::query()->whereHas('translations', fn ($query) => $query->where('full_name', 'like', '%Testdean%'))->count());

        $profile = FacultyMember::query()->findOrFail($rector->refresh()->faculty_member_id);
        $this->assertSame('test-rector', $profile->slug);
        $this->assertNull($profile->faculty_id);
        $this->assertSame('Rector biography.', $profile->translations()->where('locale', 'en')->value('bio'));
        Cache::flush();
        $this->get('/en/about/profile/test-rector')->assertOk()->assertSee('Prof. Rana Uniquename')->assertSee('Rector');
    }

    public function test_writing_requires_the_approval_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PeopleUnifyServiceInterface::class)->unify(true, 'wrong');
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
