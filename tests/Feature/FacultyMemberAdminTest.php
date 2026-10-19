<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Enums\PublicationStatus;
use App\Filament\Resources\FacultyMemberResource\Pages\CreateFacultyMember;
use App\Filament\Resources\FacultyMemberResource\Pages\ListFacultyMembers;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class FacultyMemberAdminTest extends TestCase
{
    use RefreshDatabase;

    private Faculty $dentistry;

    private Faculty $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->dentistry = Faculty::query()->where('public_slug', 'dentistry')->firstOrFail();
        $this->pharmacy = Faculty::query()->where('public_slug', 'pharmacy')->firstOrFail();
    }

    public function test_member_added_from_a_faculty_page_joins_that_faculty_from_the_simple_fields(): void
    {
        $this->actingAs($this->user('editor'), 'web');

        Livewire::withQueryParams(['faculty_scope' => 'dentistry'])
            ->test(CreateFacultyMember::class)
            ->assertSet('data.faculty_id', (int) $this->dentistry->getKey())
            ->fillForm([
                // A tampered faculty must not move the member out of the page's faculty.
                'faculty_id' => (int) $this->pharmacy->getKey(),
                'translations.ar.full_name' => 'د. عضو جديد',
                'translations.en.full_name' => 'Dr. New Member',
                'email' => 'new.member@spu.edu.sy',
                'social_links.orcid' => 'https://orcid.org/0000-0001-2345-6789',
                'social_links.scopus' => 'https://www.scopus.com/authid/detail.uri?authorId=1',
                'social_links.researchgate' => 'https://www.researchgate.net/profile/New-Member',
                'social_links.scholar' => 'https://scholar.google.com/citations?user=abc',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = FacultyMember::query()->where('slug', 'dr-new-member')->firstOrFail();
        $this->assertSame((int) $this->dentistry->getKey(), (int) $member->faculty_id);

        $payload = CmsDraft::query()->where('target_key', 'entity.faculty-member.'.$member->getKey())->latest('id')->firstOrFail()->payload_json;
        $this->assertSame('https://www.scopus.com/authid/detail.uri?authorId=1', $payload['social_links']['scopus'] ?? null);
        $this->assertSame('new.member@spu.edu.sy', $payload['email'] ?? null);
    }

    public function test_faculty_members_list_keeps_its_faculty_and_bulk_deletes_the_selection(): void
    {
        $editor = $this->user('editor');
        $this->actingAs($editor, 'web');
        $first = $this->member('outdated-one', $this->dentistry);
        $second = $this->member('outdated-two', $this->dentistry);
        $kept = $this->member('still-teaching', $this->dentistry);
        $otherFaculty = $this->member('pharmacy-member', $this->pharmacy);
        $draft = CmsDraft::query()->create([
            'target_key' => 'entity.faculty-member.'.$first->getKey(),
            'payload_json' => ['slug' => 'outdated-one'],
            'status' => PublicationStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
            'version' => 1,
            'created_by' => $editor->getKey(),
            'updated_by' => $editor->getKey(),
        ]);

        Livewire::withQueryParams(['faculty_scope' => 'dentistry'])
            ->test(ListFacultyMembers::class)
            ->assertCanSeeTableRecords([$first, $second, $kept])
            ->assertCanNotSeeTableRecords([$otherFaculty])
            ->callTableBulkAction('deleteSelected', [$first, $second])
            ->assertNotified()
            ->assertCanSeeTableRecords([$kept])
            ->assertCanNotSeeTableRecords([$first, $second, $otherFaculty]);

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($kept);
        $this->assertSame(PublicationStatus::Superseded->value, $draft->fresh()?->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faculty_member.deleted', 'entity_id' => $first->getKey()]);
    }

    public function test_faculty_editor_cannot_delete_members_of_another_faculty(): void
    {
        $editor = $this->user('faculty_editor', 'dentistry');
        $own = $this->member('own-member', $this->dentistry);
        $foreign = $this->member('foreign-member', $this->pharmacy);

        try {
            app(ProfileAdminServiceInterface::class)->deleteFacultyMembers([(int) $own->getKey(), (int) $foreign->getKey()], (int) $editor->getKey());
            $this->fail('A faculty editor deleted a member of another faculty.');
        } catch (AuthorizationException) {
        }

        $this->assertNotSoftDeleted($own);
        $this->assertNotSoftDeleted($foreign);
        $this->assertSame(1, app(ProfileAdminServiceInterface::class)->deleteFacultyMembers([(int) $own->getKey()], (int) $editor->getKey()));
        $this->assertSoftDeleted($own);
    }

    private function member(string $slug, Faculty $faculty): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'faculty_id' => $faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->create(['locale' => 'en', 'full_name' => $slug]);
        $member->translations()->create(['locale' => 'ar', 'full_name' => $slug]);

        return $member;
    }

    private function user(string $role, ?string $scope = null): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'faculty_scope_slug' => $scope,
            'is_locked' => false,
        ]);
    }
}
