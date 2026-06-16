<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\PageResource;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for PageResource Filament resource.
 *
 * Requirements: 20.1–20.5
 */
class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_page_resource(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(PageResource::canAccess());
    }

    public function test_editor_can_access_page_resource(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertTrue(PageResource::canAccess());
    }

    public function test_faculty_editor_can_access_page_resource(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertTrue(PageResource::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_page_resource(): void
    {
        $this->assertFalse(PageResource::canAccess());
    }

    public function test_page_resource_has_list_create_edit_view_pages(): void
    {
        $pages = PageResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
        $this->assertArrayHasKey('view', $pages);
    }

    public function test_parent_selector_excludes_current_page_and_descendants(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $parent = Page::factory()->create(['slug' => 'parent']);
        $child = Page::factory()->create(['slug' => 'child', 'parent_id' => $parent->id]);
        $grandchild = Page::factory()->create(['slug' => 'grandchild', 'parent_id' => $child->id]);
        $sibling = Page::factory()->create(['slug' => 'sibling']);

        $method = new \ReflectionMethod(PageResource::class, 'scopeParentQueryToCurrentUser');
        $method->setAccessible(true);

        $ids = $method->invoke(null, Page::query(), $parent)->pluck('id')->all();

        $this->assertNotContains($parent->id, $ids);
        $this->assertNotContains($child->id, $ids);
        $this->assertNotContains($grandchild->id, $ids);
        $this->assertContains($sibling->id, $ids);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
