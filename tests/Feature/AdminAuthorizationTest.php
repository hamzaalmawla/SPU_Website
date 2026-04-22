<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Verifies gate-based RBAC behavior for the admin foundation.
 */
class AdminAuthorizationTest extends TestCase
{
    /**
     * It denies editors access to user-management routes.
     */
    public function test_editor_receives_forbidden_response_on_user_management(): void
    {
        $editor = new User;
        $editor->forceFill(['role_slug' => 'editor']);

        $this->actingAs($editor, 'web');

        $this->get('/admin/users')->assertForbidden();
    }

    /**
     * It allows super admins to access protected admin management routes.
     */
    public function test_super_admin_can_access_all_registered_admin_gate_routes(): void
    {
        $superAdmin = new User;
        $superAdmin->forceFill(['role_slug' => 'super_admin']);

        $this->actingAs($superAdmin, 'web');

        $this->get('/admin/content')->assertOk();
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/users')->assertOk();
    }

    /**
     * It resolves the required RBAC gates for each supported role slug.
     */
    public function test_required_rbac_gates_resolve_cleanly(): void
    {
        $superAdmin = new User;
        $superAdmin->forceFill(['role_slug' => 'super_admin']);

        $editor = new User;
        $editor->forceFill(['role_slug' => 'editor']);

        $facultyEditor = new User;
        $facultyEditor->forceFill([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
        ]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-homepage'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-pages'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-menu'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-media'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('publish-content'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('preview-content'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view-audit-log'));

        $this->assertFalse(Gate::forUser($editor)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($editor)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($editor)->allows('manage-homepage'));
        $this->assertTrue(Gate::forUser($editor)->allows('manage-pages'));
        $this->assertTrue(Gate::forUser($editor)->allows('manage-menu'));
        $this->assertTrue(Gate::forUser($editor)->allows('manage-media'));
        $this->assertTrue(Gate::forUser($editor)->allows('publish-content'));
        $this->assertTrue(Gate::forUser($editor)->allows('preview-content'));
        $this->assertFalse(Gate::forUser($editor)->allows('view-audit-log'));

        $this->assertFalse(Gate::forUser($facultyEditor)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('manage-settings'));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('manage-homepage'));
        $this->assertTrue(Gate::forUser($facultyEditor)->allows('manage-pages'));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('manage-menu'));
        $this->assertTrue(Gate::forUser($facultyEditor)->allows('manage-media'));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('publish-content'));
        $this->assertTrue(Gate::forUser($facultyEditor)->allows('preview-content'));
        $this->assertFalse(Gate::forUser($facultyEditor)->allows('view-audit-log'));
    }
}
