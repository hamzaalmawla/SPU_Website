<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ManageSettings Filament page.
 *
 * Requirements: 23.1–23.4
 */
class ManageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_manage_settings(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(ManageSettings::canAccess());
    }

    public function test_editor_can_access_manage_settings(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertTrue(ManageSettings::canAccess());
    }

    public function test_faculty_editor_cannot_access_manage_settings(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertFalse(ManageSettings::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_manage_settings(): void
    {
        $this->assertFalse(ManageSettings::canAccess());
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
