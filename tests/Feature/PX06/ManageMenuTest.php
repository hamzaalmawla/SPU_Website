<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageMenu;
use App\Models\Navigation\MenuItem;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_existing_menu_item_can_be_edited(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $item = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'Old label',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => 'https://old.example.com',
            'group_key' => 'header',
            'is_enabled' => true,
            'open_in_new_tab' => false,
            'sort_order' => 1,
            'depth' => 0,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('editItem', $item->id)
            ->assertSet('isEditing', true)
            ->assertSet('editingItemId', $item->id)
            ->set('editForm.label', 'Updated label')
            ->set('editForm.target_type', 'url')
            ->set('editForm.url', '/en/new-menu-path')
            ->set('editForm.is_enabled', false)
            ->set('editForm.open_in_new_tab', true)
            ->call('updateEditingItem')
            ->assertSet('isEditing', false);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'label' => 'Updated label',
            'url' => '/en/new-menu-path',
            'is_enabled' => false,
            'open_in_new_tab' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'menu.updated',
            'entity_id' => $item->id,
        ]);
    }

    public function test_route_menu_item_keeps_route_when_edited(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $item = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'Route item',
            'locale' => 'en',
            'target_kind' => 'route',
            'route_name' => 'filament.admin.pages.manage-menu',
            'group_key' => 'header',
            'is_enabled' => true,
            'open_in_new_tab' => false,
            'sort_order' => 1,
            'depth' => 0,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('editItem', $item->id)
            ->set('editForm.label', 'Updated route item')
            ->set('editForm.route_name', 'filament.admin.pages.manage-menu')
            ->call('updateEditingItem');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'label' => 'Updated route item',
            'target_kind' => 'route',
            'route_name' => 'filament.admin.pages.manage-menu',
        ]);
    }

    public function test_nested_menu_item_can_be_edited_without_changing_parent(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $parent = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'Facilities',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => '/en/facilities',
            'group_key' => 'header',
            'is_enabled' => true,
            'sort_order' => 1,
            'depth' => 0,
        ]);
        $item = MenuItem::query()->create([
            'parent_id' => $parent->id,
            'type' => 'header',
            'label' => 'Old child label',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => '/en/facilities/child',
            'group_key' => 'header',
            'is_enabled' => true,
            'sort_order' => 1,
            'depth' => 1,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('editItem', $item->id)
            ->set('editForm.label', 'Updated child label')
            ->call('updateEditingItem');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'label' => 'Updated child label',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_menu_reorder_is_limited_to_submitted_locale(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $englishFirst = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'First',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => 'https://first.example.com',
            'group_key' => 'header',
            'is_enabled' => true,
            'sort_order' => 1,
            'depth' => 0,
        ]);
        $englishSecond = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'Second',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => 'https://second.example.com',
            'group_key' => 'header',
            'is_enabled' => true,
            'sort_order' => 2,
            'depth' => 0,
        ]);
        $arabicItem = MenuItem::query()->create([
            'type' => 'header',
            'label' => 'Arabic',
            'locale' => 'ar',
            'target_kind' => 'url',
            'url' => 'https://arabic.example.com',
            'group_key' => 'header',
            'is_enabled' => true,
            'sort_order' => 1,
            'depth' => 0,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('reorderItems', [
                ['id' => $englishSecond->id],
                ['id' => $englishFirst->id],
            ], 'en');

        $this->assertDatabaseHas('menu_items', ['id' => $englishSecond->id, 'sort_order' => 1, 'locale' => 'en']);
        $this->assertDatabaseHas('menu_items', ['id' => $englishFirst->id, 'sort_order' => 2, 'locale' => 'en']);
        $this->assertDatabaseHas('menu_items', ['id' => $arabicItem->id, 'sort_order' => 1, 'locale' => 'ar']);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
