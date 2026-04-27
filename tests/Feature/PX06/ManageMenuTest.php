<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ManageMenu Filament page.
 *
 * Requirements: 21.1–21.5
 */
class ManageMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_manage_menu(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(ManageMenu::canAccess());
    }

    public function test_editor_can_access_manage_menu(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertTrue(ManageMenu::canAccess());
    }

    public function test_faculty_editor_cannot_access_manage_menu(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertFalse(ManageMenu::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_manage_menu(): void
    {
        $this->assertFalse(ManageMenu::canAccess());
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
