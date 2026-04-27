<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageHomepage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for ManageHomepage Filament page.
 *
 * Requirements: 19.1–19.5
 */
class ManageHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_manage_homepage(): void
    {
        $user = $this->createUser('super_admin');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_editor_can_access_manage_homepage(): void
    {
        $user = $this->createUser('editor');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_faculty_editor_cannot_access_manage_homepage(): void
    {
        $user = $this->createUser('faculty_editor');

        $this->actingAs($user);

        $this->assertFalse(ManageHomepage::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_manage_homepage(): void
    {
        $this->assertFalse(ManageHomepage::canAccess());
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
