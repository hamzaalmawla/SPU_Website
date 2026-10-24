<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Filament\Pages\ManageAbout;
use App\Filament\Pages\ManageAdmissions;
use App\Filament\Pages\ManageContactPage;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\ManageJobBoard;
use App\Filament\Pages\ManageMedicineFaculty;
use App\Filament\Pages\ManageMenu;
use App\Filament\Pages\ManagePharmacyFaculty;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\DirectorateResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
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
 * Roles are editable sets of permissions; a user holds roles, optional extra
 * permissions and optional faculty / page limits.
 */
final class PermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    private const DRAFT = ['translations' => ['ar' => [], 'en' => []]];

    public function test_the_default_roles_keep_the_access_they_had(): void
    {
        $editor = $this->user(['editor']);
        $faculty = $this->user(['faculty_editor']);
        $hr = $this->user(['hr']);

        $this->assertTrue(Gate::forUser($editor)->allows('about.publish'));
        $this->assertTrue(Gate::forUser($editor)->allows('settings.update'));
        $this->assertFalse(Gate::forUser($editor)->allows('audit_log.view'));

        $this->assertTrue(Gate::forUser($faculty)->allows('faculty_medicine.update'));
        $this->assertTrue(Gate::forUser($faculty)->allows('faculty_medicine.publish'));
        $this->assertFalse(Gate::forUser($faculty)->allows('about.view'));

        $this->assertTrue(Gate::forUser($hr)->allows('job_board.publish'));
        $this->assertTrue(Gate::forUser($hr)->allows('job_applications.view'));
        $this->assertFalse(Gate::forUser($hr)->allows('form_submissions.view'));

        $this->assertTrue(Gate::forUser($this->user(['super_admin']))->allows('audit_log.view'));
    }

    public function test_a_super_admin_builds_a_role_and_gives_it_to_a_new_user(): void
    {
        $this->actingAs($this->user(['super_admin']));

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Admissions office',
                'slug' => 'admissions_office',
                'permissions_site_pages' => ['admissions.view', 'admissions.update', 'admissions.publish'],
                'permissions_forms_inbox' => ['contact_messages.view'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('slug', 'admissions_office')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['admissions.view', 'admissions.update', 'admissions.publish', 'contact_messages.view'],
            app(RoleAdminServiceInterface::class)->permissionsForRole((int) $role->getKey()),
        );

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Admissions Clerk',
                'email' => 'clerk@spu.edu.sy',
                'role_slugs' => ['admissions_office'],
                'extra_permissions' => ['menu.view'],
                'password' => 'strong-password-123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $clerk = User::query()->where('email', 'clerk@spu.edu.sy')->firstOrFail();
        $this->actingAs($clerk);

        $this->assertTrue(ManageAdmissions::canAccess());
        $this->assertTrue(ManageMenu::canAccess());
        $this->assertFalse(Gate::allows('menu.update'));
        $this->assertFalse(ManageAbout::canAccess());
        $this->assertFalse(ManageSettings::canAccess());
        $this->assertFalse(RoleResource::canAccess());
    }

    public function test_the_role_screen_shows_and_saves_the_role_permissions(): void
    {
        $this->actingAs($this->user(['super_admin']));
        $hr = Role::query()->where('slug', 'hr')->firstOrFail();

        Livewire::test(EditRole::class, ['record' => $hr->getKey()])
            ->assertFormSet(['permissions_jobs' => ['job_board.view', 'job_board.update', 'job_board.publish']])
            ->fillForm(['name' => 'HR team', 'permissions_jobs' => ['job_board.view'], 'permissions_forms_inbox' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('HR team', $hr->refresh()->name);
        $this->assertSame(['job_board.view'], app(RoleAdminServiceInterface::class)->permissionsForRole((int) $hr->getKey()));
    }

    public function test_one_user_can_hold_several_roles(): void
    {
        $user = $this->user(['hr', 'faculty_editor']);

        $this->actingAs($user);
        $this->assertTrue(ManageJobBoard::canAccess());
        $this->assertTrue(ManageMedicineFaculty::canAccess());
        $this->assertFalse(ManageAbout::canAccess());
    }

    public function test_extra_permissions_add_to_the_roles(): void
    {
        $this->actingAs($this->user(['hr'], extra: ['contact_page.view', 'contact_page.update']));

        $this->assertTrue(ManageContactPage::canAccess());
        $this->assertTrue(Gate::allows('contact_page.publish'));
    }

    public function test_changing_a_role_changes_its_users_at_once(): void
    {
        $user = $this->user(['editor']);
        $roles = app(RoleAdminServiceInterface::class);
        $editorRole = Role::query()->where('slug', 'editor')->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('menu.update'));

        $kept = array_values(array_diff($roles->permissionsForRole((int) $editorRole->getKey()), ['menu.update']));
        $roles->saveRole((int) $editorRole->getKey(), 'Editor', 'editor', $kept, (int) $this->user(['super_admin'])->getKey());

        $this->assertFalse(Gate::forUser($user)->allows('menu.update'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated']);
    }

    public function test_publishing_needs_the_publish_permission(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user([], extra: ['research_pages.view']);

        $this->expectException(AuthorizationException::class);
        $workflow->publish('research.centers', (int) $user->getKey());
    }

    public function test_edit_permission_includes_publish(): void
    {
        $user = $this->user([], extra: ['research_pages.view', 'research_pages.update', 'news_articles.update']);
        $access = app(AdminAccessServiceInterface::class);

        $this->assertTrue(Gate::forUser($user)->allows('research_pages.publish'));
        $this->assertTrue(Gate::forUser($user)->allows('news_articles.publish'));
        $this->assertFalse(Gate::forUser($user)->allows('about.publish'));
        $this->assertContains((int) $user->getKey(), $access->userIdsWithPermission('research_pages.publish'));
        $this->assertNotContains((int) $user->getKey(), $access->userIdsWithPermission('about.publish'));
    }

    public function test_a_faculty_editor_publishes_only_within_their_faculties(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user(['faculty_editor'], faculties: ['medicine']);
        $access = app(AdminAccessServiceInterface::class);
        $target = fn (string $key) => app(CmsTargetRegistryInterface::class)->find($key);

        $this->assertTrue($access->canManageTarget($user, $target('facilities.medicine.overview'), 'publish'));
        $this->assertFalse($access->canManageTarget($user, $target('facilities.dentistry.overview'), 'publish'));

        $this->expectException(AuthorizationException::class);
        $workflow->publish('facilities.dentistry.overview', (int) $user->getKey());
    }

    public function test_a_faculty_limit_keeps_faculty_content_to_the_chosen_faculties(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user(['faculty_editor'], faculties: ['medicine', 'pharmacy']);

        $workflow->saveDraft('facilities.medicine.overview', self::DRAFT, (int) $user->getKey());
        $workflow->saveDraft('facilities.pharmacy.overview', self::DRAFT, (int) $user->getKey());

        $this->actingAs($user);
        $this->assertTrue(ManageMedicineFaculty::canAccess());
        $this->assertTrue(ManagePharmacyFaculty::canAccess());

        $this->expectException(AuthorizationException::class);
        $workflow->saveDraft('facilities.dentistry.overview', self::DRAFT, (int) $user->getKey());
    }

    public function test_a_page_limit_keeps_a_screen_to_the_chosen_pages(): void
    {
        $this->seed(AboutSectionSeeder::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $user = $this->user([], extra: ['about.view', 'about.update'], pages: ['about.history']);

        $workflow->saveDraft('about.history', self::DRAFT, (int) $user->getKey());

        try {
            $workflow->saveDraft('about.vision-mission', self::DRAFT, (int) $user->getKey());
            $this->fail('A page outside the page limit was saved.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('cms_drafts', ['target_key' => 'about.vision-mission']);
        }

        $this->actingAs($user);
        $this->assertFalse(ManageAdmissions::canAccess());

        Livewire::test(ManageAbout::class)
            ->assertOk()
            ->assertSet('data.target_key', 'about.history')
            ->call('loadTarget', 'about.landing')
            ->assertForbidden();
    }

    public function test_screens_without_a_policy_follow_each_action_permission(): void
    {
        $this->actingAs($this->user([], extra: ['directorates.view']));

        $this->assertTrue(DirectorateResource::canAccess());
        $this->assertFalse(DirectorateResource::canCreate());
        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_the_homepage_screen_and_its_publish_button_follow_their_permissions(): void
    {
        $user = $this->user([], extra: ['homepage.view', 'homepage.update']);

        $this->actingAs($user);
        $this->assertTrue(ManageHomepage::canAccess());
        $this->assertTrue(Gate::allows('homepage.update'));
        $this->assertTrue(Gate::allows('homepage.publish'));
    }

    public function test_the_super_admin_role_cannot_be_changed_or_deleted(): void
    {
        $actor = $this->user(['super_admin']);
        $roles = app(RoleAdminServiceInterface::class);
        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();

        $this->actingAs($actor);
        $this->assertFalse(RoleResource::canEdit($superAdmin));
        $this->assertFalse(RoleResource::canDelete($superAdmin));

        $this->expectException(ValidationException::class);
        $roles->deleteRole((int) $superAdmin->getKey(), (int) $actor->getKey());
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $actor = $this->user(['super_admin']);
        $this->user(['hr']);

        $this->expectException(ValidationException::class);
        app(RoleAdminServiceInterface::class)->deleteRole((int) Role::query()->where('slug', 'hr')->value('id'), (int) $actor->getKey());
    }

    public function test_only_a_super_admin_manages_roles(): void
    {
        $editor = $this->user(['editor']);

        $this->actingAs($editor);
        $this->assertFalse(RoleResource::canAccess());

        $this->expectException(AuthorizationException::class);
        app(RoleAdminServiceInterface::class)->saveRole(null, 'Sneaky', 'sneaky', ['settings.update'], (int) $editor->getKey());
    }

    public function test_the_user_form_loads_and_saves_roles_extras_and_limits(): void
    {
        $this->actingAs($this->user(['super_admin']));
        $user = $this->user(['faculty_editor'], faculties: ['medicine']);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertFormSet(['role_slugs' => ['faculty_editor'], 'faculty_scopes' => ['medicine']])
            ->fillForm([
                'role_slugs' => ['faculty_editor', 'hr'],
                'extra_permissions' => ['contact_page.view'],
                'faculty_scopes' => ['medicine', 'pharmacy'],
                'page_scopes' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame(['medicine', 'pharmacy'], $user->facultyScopes());
        $this->assertSame(['contact_page.view', 'faculty_medicine.view'], array_values(array_intersect(
            ['contact_page.view', 'faculty_medicine.view'],
            app(AdminAccessServiceInterface::class)->permissionsFor($user),
        )));
        $this->assertTrue(Gate::forUser($user)->allows('job_board.view'));
    }

    public function test_making_someone_super_admin_clears_their_extras_and_limits(): void
    {
        $actor = $this->user(['super_admin']);
        $user = $this->user(['editor'], extra: ['audit_log.view'], faculties: ['medicine']);

        app(AuthServiceInterface::class)->updateUser((int) $user->getKey(), ['role_slugs' => ['editor', 'super_admin']], (int) $actor->getKey());

        $user->refresh();
        $this->assertSame('super_admin', $user->role_slug);
        $this->assertSame([], $user->facultyScopes());
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $user->getKey()]);
    }

    public function test_unknown_permissions_and_roles_are_refused(): void
    {
        $actor = $this->user(['super_admin']);
        $auth = app(AuthServiceInterface::class);

        foreach ([['role_slugs' => ['nope']], ['role_slugs' => ['editor'], 'extra_permissions' => ['users.delete']]] as $payload) {
            try {
                $auth->createUser(['name' => 'X', 'email' => 'x@spu.edu.sy', 'password' => 'strong-password-123', ...$payload], (int) $actor->getKey());
                $this->fail('Accepted '.json_encode($payload));
            } catch (ValidationException) {
                $this->assertDatabaseMissing('users', ['email' => 'x@spu.edu.sy']);
            }
        }
    }

    public function test_every_catalog_permission_has_labels_in_both_languages(): void
    {
        $catalog = app(PermissionCatalogInterface::class);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach ($catalog->all() as $permission) {
                $this->assertStringNotContainsString('admin_permissions.', $catalog->label($permission), $locale.' '.$permission);
            }
        }
    }

    public function test_a_user_without_any_permission_cannot_sign_in_to_the_admin(): void
    {
        $user = $this->user([]);

        $this->assertFalse(app(AdminAccessServiceInterface::class)->hasAdminAccess($user));
        $this->assertTrue(app(AdminAccessServiceInterface::class)->hasAdminAccess($this->user([], extra: ['menu.view'])));
    }

    public function test_page_grants_from_before_become_extra_permissions(): void
    {
        $migration = require database_path('migrations/2026_10_21_000001_create_admin_permission_tables.php');
        $migration->down();

        $id = DB::table('users')->insertGetId([
            'name' => 'Old', 'email' => 'old@spu.edu.sy', 'password' => 'x', 'role_slug' => 'faculty_editor',
            'page_scopes' => json_encode(['about.history', 'contact']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles')->delete();

        $migration->up();

        $this->assertEqualsCanonicalizing(
            ['about.view', 'about.update', 'contact_page.view', 'contact_page.update'],
            DB::table('user_permissions')->where('user_id', $id)->pluck('permission')->all(),
        );
        $this->assertDatabaseHas('role_user', ['user_id' => $id]);
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

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $extra
     * @param  list<string>  $faculties
     * @param  list<string>  $pages
     */
    private function user(array $roles, array $extra = [], array $faculties = [], array $pages = []): User
    {
        $user = User::factory()->create([
            'role_slug' => in_array('super_admin', $roles, true) ? 'super_admin' : ($roles[0] ?? null),
            'faculty_scopes' => $faculties === [] ? null : $faculties,
            'page_scopes' => $pages === [] ? null : $pages,
            'is_locked' => false,
        ]);

        $user->roles()->sync(Role::query()->whereIn('slug', $roles)->pluck('id')->all());

        foreach ($extra as $permission) {
            $user->extraPermissions()->create(['permission' => $permission]);
        }

        app(AdminAccessServiceInterface::class)->flushPermissions();

        return $user;
    }
}
