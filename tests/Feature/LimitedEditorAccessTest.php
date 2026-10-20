<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Filament\Pages\ManageAbout;
use App\Filament\Pages\ManageAdmissions;
use App\Filament\Pages\ManageCampusLife;
use App\Filament\Pages\ManageContactPage;
use App\Filament\Pages\ManageEvents;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Filament\Pages\ManagePharmacyFaculty;
use App\Filament\Pages\ManageResearch;
use App\Filament\Resources\FacultyMemberResource;
use App\Filament\Resources\NewsArticleResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User\Role;
use App\Models\User\User;
use Database\Seeders\AboutSectionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A limited editor (role `faculty_editor`) edits exactly the faculties and
 * pages a super administrator picked on the user form, and nothing else.
 */
final class LimitedEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    private const DRAFT = ['translations' => ['ar' => [], 'en' => []]];

    public function test_super_admin_grants_several_faculties_and_pages_from_the_user_form(): void
    {
        $this->roles();
        $this->actingAs($this->user('super_admin'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Pharmacy and Medicine Editor',
                'email' => 'limited@spu.edu.sy',
                'role_slug' => 'faculty_editor',
                'faculty_scopes' => ['medicine', 'pharmacy'],
                'page_scopes' => ['about.history', 'admissions.tuition'],
                'password' => 'strong-password-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'limited@spu.edu.sy')->firstOrFail();

        $this->assertSame(['medicine', 'pharmacy'], $user->facultyScopes());
        $this->assertSame(['about.history', 'admissions.tuition'], $user->pageScopes());
    }

    public function test_the_user_form_requires_a_faculty_or_page_for_a_limited_editor(): void
    {
        $this->roles();
        $this->actingAs($this->user('super_admin'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Empty Editor',
                'email' => 'empty@spu.edu.sy',
                'role_slug' => 'faculty_editor',
                'faculty_scopes' => [],
                'page_scopes' => [],
                'password' => 'strong-password-123',
            ])
            ->call('create')
            ->assertHasFormErrors(['faculty_scopes']);

        $this->assertDatabaseMissing('users', ['email' => 'empty@spu.edu.sy']);
    }

    public function test_the_service_stores_public_slugs_and_rejects_unknown_grants(): void
    {
        $this->roles();
        $actor = $this->user('super_admin');
        $auth = app(AuthServiceInterface::class);

        $id = $auth->createUser([
            'name' => 'Business Editor',
            'email' => 'business@spu.edu.sy',
            'password' => 'strong-password-123',
            'role_slug' => 'faculty_editor',
            'faculty_scopes' => ['business', 'business-administration'],
        ], (int) $actor->getKey());

        $this->assertSame(['business-administration'], User::query()->findOrFail($id)->facultyScopes());

        foreach ([['faculty_scopes' => ['not-a-faculty']], ['page_scopes' => ['facilities.medicine']], []] as $grants) {
            try {
                $auth->createUser([
                    'name' => 'Rejected',
                    'email' => 'rejected@spu.edu.sy',
                    'password' => 'strong-password-123',
                    'role_slug' => 'faculty_editor',
                    ...$grants,
                ], (int) $actor->getKey());
                $this->fail('A limited editor was created with grants '.json_encode($grants));
            } catch (ValidationException) {
                $this->assertDatabaseMissing('users', ['email' => 'rejected@spu.edu.sy']);
            }
        }
    }

    public function test_changing_the_role_clears_the_grants(): void
    {
        $this->roles();
        $this->actingAs($this->user('super_admin'));
        $user = $this->user('faculty_editor', ['medicine'], ['contact']);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role_slug' => 'editor'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('editor', $user->role_slug);
        $this->assertSame([], $user->facultyScopes());
        $this->assertSame([], $user->pageScopes());
    }

    public function test_a_page_grant_allows_drafts_of_that_page_only(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user('faculty_editor', [], ['research.centers']);

        $workflow->saveDraft('research.centers', self::DRAFT, (int) $user->getKey());
        $this->assertDatabaseHas('cms_drafts', ['target_key' => 'research.centers']);

        foreach (['research.projects', 'about.history', 'facilities.medicine'] as $targetKey) {
            try {
                $workflow->saveDraft($targetKey, self::DRAFT, (int) $user->getKey());
                $this->fail('A page-only editor saved '.$targetKey);
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('cms_drafts', ['target_key' => $targetKey]);
            }
        }

        $this->expectException(AuthorizationException::class);
        $workflow->publish('research.centers', (int) $user->getKey());
    }

    public function test_faculty_grants_cover_every_granted_faculty_and_no_other(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user('faculty_editor', ['medicine', 'pharmacy']);

        $workflow->saveDraft('facilities.medicine.overview', self::DRAFT, (int) $user->getKey());
        $workflow->saveDraft('facilities.pharmacy.overview', self::DRAFT, (int) $user->getKey());

        $this->expectException(AuthorizationException::class);
        $workflow->saveDraft('facilities.dentistry.overview', self::DRAFT, (int) $user->getKey());
    }

    public function test_admin_screens_open_only_for_granted_areas(): void
    {
        $this->actingAs($this->user('faculty_editor', ['medicine', 'pharmacy'], ['about.history', 'news.events']));

        $this->assertTrue(ManageAbout::canAccess());
        $this->assertTrue(ManageEvents::canAccess());
        $this->assertTrue(ManageMedicineFaculty::canAccess());
        $this->assertTrue(ManagePharmacyFaculty::canAccess());
        $this->assertFalse(ManageAdmissions::canAccess());
        $this->assertFalse(ManageResearch::canAccess());
        $this->assertFalse(ManageContactPage::canAccess());
        // Faculty editors used to see Campus Life through the faculties gate
        // and then fail on every page of it.
        $this->assertFalse(ManageCampusLife::canAccess());
        $this->assertFalse(Gate::allows('manage-homepage'));
    }

    public function test_an_area_screen_opens_on_the_granted_page(): void
    {
        $this->seed(AboutSectionSeeder::class);
        $this->actingAs($this->user('faculty_editor', [], ['about.history']));

        Livewire::test(ManageAbout::class)
            ->assertOk()
            ->assertSet('data.target_key', 'about.history')
            ->call('loadTarget', 'about.landing')
            ->assertForbidden();
    }

    public function test_a_page_only_editor_does_not_see_faculty_screens(): void
    {
        $this->actingAs($this->user('faculty_editor', [], ['contact']));

        $this->assertTrue(ManageContactPage::canAccess());
        $this->assertFalse(FacultyMemberResource::canAccess());
        $this->assertFalse(NewsArticleResource::canAccess());
        $this->assertFalse(ManageMedicineFaculty::canAccess());
    }

    public function test_the_homepage_can_be_granted_as_a_page(): void
    {
        $user = $this->user('faculty_editor', [], ['homepage']);

        $this->assertTrue(Gate::forUser($user)->allows('manage-homepage'));
        $this->assertFalse(Gate::forUser($user)->allows('publish-content'));
    }

    public function test_full_editors_are_unaffected(): void
    {
        $this->actingAs($this->user('editor'));

        $this->assertTrue(ManageAbout::canAccess());
        $this->assertTrue(ManageCampusLife::canAccess());
        $this->assertTrue(ManageContactPage::canAccess());
    }

    public function test_the_migration_carries_an_existing_faculty_over_as_its_public_slug(): void
    {
        $migration = require database_path('migrations/2026_10_20_000001_replace_user_faculty_scope_with_access_scopes.php');
        $migration->down();

        $now = now();
        foreach (['business' => 'old-business@spu.edu.sy', 'medicine' => 'old-medicine@spu.edu.sy'] as $scope => $email) {
            DB::table('users')->insert([
                'name' => $email, 'email' => $email, 'password' => 'x', 'role_slug' => 'faculty_editor',
                'faculty_scope_slug' => $scope, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'faculty_scope_slug'));
        $this->assertSame(['business-administration'], User::query()->where('email', 'old-business@spu.edu.sy')->firstOrFail()->facultyScopes());
        $this->assertSame(['medicine'], User::query()->where('email', 'old-medicine@spu.edu.sy')->firstOrFail()->facultyScopes());
    }

    private function roles(): void
    {
        foreach (['super_admin' => 'Super Admin', 'editor' => 'Editor', 'faculty_editor' => 'Limited Editor'] as $slug => $name) {
            Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }

    /**
     * @param  list<string>  $faculties
     * @param  list<string>  $pages
     */
    private function user(string $role, array $faculties = [], array $pages = []): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'faculty_scopes' => $faculties === [] ? null : $faculties,
            'page_scopes' => $pages === [] ? null : $pages,
            'is_locked' => false,
        ]);
    }
}
