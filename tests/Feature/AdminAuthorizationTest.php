<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Verifies gate-based RBAC behavior for the admin foundation.
 */
class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * It denies editors access to user-management routes.
     */
    public function test_editor_receives_forbidden_response_on_user_management(): void
    {
        $editor = new User;
        $editor->forceFill(['name' => 'Editor User', 'email' => 'editor@example.com', 'role_slug' => 'editor']);

        $this->actingAs($editor, 'web');

        $this->get('/admin/users')->assertForbidden();
    }

    /**
     * It allows super admins to access protected admin management routes.
     */
    public function test_super_admin_can_access_all_registered_admin_gate_routes(): void
    {
        $superAdmin = new User;
        $superAdmin->forceFill(['name' => 'Super Admin', 'email' => 'super-admin@example.com', 'role_slug' => 'super_admin']);

        $this->actingAs($superAdmin, 'web');

        $this->get('/admin/manage-settings')->assertOk();
        $this->get('/admin/users')->assertOk();
    }

    /**
     * It resolves the required RBAC gates for each supported role slug.
     */
    public function test_default_roles_resolve_to_their_permissions(): void
    {
        $superAdmin = new User;
        $superAdmin->forceFill(['name' => 'Super Admin', 'email' => 'super-admin@example.com', 'role_slug' => 'super_admin']);

        $editor = new User;
        $editor->forceFill(['name' => 'Editor User', 'email' => 'editor@example.com', 'role_slug' => 'editor']);

        $facultyEditor = new User;
        $facultyEditor->forceFill([
            'name' => 'Faculty Editor',
            'email' => 'faculty-editor@example.com',
            'role_slug' => 'faculty_editor',
            'faculty_scopes' => ['medicine'],
        ]);

        $hr = new User;
        $hr->forceFill(['name' => 'HR User', 'email' => 'hr@example.com', 'role_slug' => 'hr']);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-users'));
        foreach (['settings.update', 'homepage.update', 'about.update', 'menu.update', 'media.create', 'about.publish', 'audit_log.view'] as $permission) {
            $this->assertTrue(Gate::forUser($superAdmin)->allows($permission), $permission);
        }

        $this->assertFalse(Gate::forUser($editor)->allows('manage-users'));
        foreach (['settings.update', 'homepage.update', 'about.update', 'menu.update', 'media.create', 'about.publish', 'homepage.publish'] as $permission) {
            $this->assertTrue(Gate::forUser($editor)->allows($permission), $permission);
        }
        $this->assertFalse(Gate::forUser($editor)->allows('audit_log.view'));

        $this->assertFalse(Gate::forUser($facultyEditor)->allows('manage-users'));
        foreach (['settings.update', 'homepage.update', 'about.update', 'menu.update', 'faculty_medicine.publish', 'audit_log.view'] as $permission) {
            $this->assertFalse(Gate::forUser($facultyEditor)->allows($permission), $permission);
        }
        $this->assertTrue(Gate::forUser($facultyEditor)->allows('media.create'));
        $this->assertTrue(Gate::forUser($facultyEditor)->allows('faculty_medicine.update'));

        $this->assertTrue(Gate::forUser($hr)->allows('job_board.update'));
        $this->assertTrue(Gate::forUser($hr)->allows('job_board.publish'));
        foreach (['faculty_medicine.update', 'about.update', 'about.publish', 'settings.update'] as $permission) {
            $this->assertFalse(Gate::forUser($hr)->allows($permission), $permission);
        }
    }
}
