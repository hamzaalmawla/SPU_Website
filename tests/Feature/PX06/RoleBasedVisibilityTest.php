<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\ManageMenu;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\UserResource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for role-based visibility across Filament resources and pages.
 *
 * Requirements: 26.1, 26.2, 26.3, 26.4
 */
class RoleBasedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_all_resources_and_pages(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(ManageHomepage::canAccess());
        $this->assertTrue(PageResource::canAccess());
        $this->assertTrue(ManageMenu::canAccess());
        $this->assertTrue(MediaAssetResource::canAccess());
        $this->assertTrue(ManageSettings::canAccess());
        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(AuditLogResource::canAccess());
    }

    public function test_editor_sees_homepage_pages_menu_media_settings_only(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertTrue(ManageHomepage::canAccess());
        $this->assertTrue(PageResource::canAccess());
        $this->assertTrue(ManageMenu::canAccess());
        $this->assertTrue(MediaAssetResource::canAccess());
        $this->assertTrue(ManageSettings::canAccess());

        // Editor should NOT see admin-only resources
        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_faculty_editor_sees_only_scoped_pages_and_media(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertTrue(PageResource::canAccess());
        $this->assertTrue(MediaAssetResource::canAccess());

        // Faculty editor should NOT see homepage, menu, settings, users, audit
        $this->assertFalse(ManageHomepage::canAccess());
        $this->assertFalse(ManageMenu::canAccess());
        $this->assertFalse(ManageSettings::canAccess());
        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_unauthorized_user_gets_no_access(): void
    {
        // No user authenticated
        $this->assertFalse(ManageHomepage::canAccess());
        $this->assertFalse(PageResource::canAccess());
        $this->assertFalse(ManageMenu::canAccess());
        $this->assertFalse(MediaAssetResource::canAccess());
        $this->assertFalse(ManageSettings::canAccess());
        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_admin_routes_return_403_for_unauthorized_roles(): void
    {
        $user = $this->createUser('faculty_editor');

        $this->actingAs($user, 'web');

        // Faculty editor should be forbidden from homepage management
        $this->get('/admin/manage-homepage')->assertForbidden();
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
