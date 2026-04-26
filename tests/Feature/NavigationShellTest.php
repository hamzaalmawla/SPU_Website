<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\MenuServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\MenuTreeNodeDTO;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NavigationShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_primary_navigation_payload_resolves_for_ar_and_en(): void
    {
        $ar = $this->navigationService()->getFullNavigationPayload('ar', 'ar');
        $en = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $this->assertSame('عن الجامعة', $ar->header->items[0]->label);
        $this->assertSame('/ar/about', $ar->header->items[0]->resolvedUrl);
        $this->assertSame('About', $en->header->items[0]->label);
        $this->assertSame('/en/about', $en->header->items[0]->resolvedUrl);
    }

    public function test_utility_navigation_payload_resolves_for_ar_and_en(): void
    {
        $ar = $this->navigationService()->getFullNavigationPayload('ar', 'ar/about');
        $en = $this->navigationService()->getFullNavigationPayload('en', 'en/about');

        $this->assertSame('بوابة الطالب', $ar->utility->items[0]->label);
        $this->assertSame('Student Portal', $en->utility->items[0]->label);
        $this->assertSame('https://students.spu.edu.sy', $en->studentPortalUrl);
        $this->assertSame('https://staff.spu.edu.sy', $en->staffAccessUrl);
        $this->assertSame('Apply now', $en->applyCta?->label);
    }

    public function test_footer_payload_resolves_for_ar_and_en(): void
    {
        $ar = $this->navigationService()->getFullNavigationPayload('ar', 'ar');
        $en = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $this->assertSame('الجامعة الخاصة السورية', $ar->footerSettings->brandTitle);
        $this->assertSame('Syrian Private University', $en->footerSettings->brandTitle);
        $this->assertCount(2, $en->footerSettings->legalLinks);
        $this->assertNotEmpty($en->socialContact->socialLinks);
        $this->assertNotEmpty($en->socialContact->contactLinks);
    }

    public function test_menu_depth_greater_than_two_is_rejected(): void
    {
        $root = $this->menuService()->createItem($this->menuPayload('Root', null, '/en/root'));
        $child = $this->menuService()->createItem($this->menuPayload('Child', $root->id, '/en/root/child'));
        $grandchild = $this->menuService()->createItem($this->menuPayload('Grandchild', $child->id, '/en/root/child/grandchild'));

        $this->assertSame(2, $grandchild->depth);

        $this->expectException(InvalidArgumentException::class);

        $this->menuService()->createItem($this->menuPayload('Too Deep', $grandchild->id, '/en/root/child/grandchild/deep'));
    }

    public function test_active_state_marks_matching_item_and_parent_consistently(): void
    {
        $parent = $this->menuService()->createItem($this->menuPayload('Shell Parent', null, '/en/shell-parent'));
        $this->menuService()->createItem($this->menuPayload('Shell Child', $parent->id, '/en/shell-parent/child'));

        $tree = $this->menuService()->getHeaderTree('en', 'en/shell-parent/child');
        $parentItem = $this->findMenuItem($tree->items, 'Shell Parent');
        $childItem = $this->findMenuItem($tree->items, 'Shell Child');

        $this->assertInstanceOf(MenuItemDTO::class, $parentItem);
        $this->assertInstanceOf(MenuItemDTO::class, $childItem);
        $this->assertTrue($parentItem->isActive);
        $this->assertTrue($childItem->isActive);
    }

    public function test_menu_write_actions_invalidate_navigation_cache_and_create_audit_rows(): void
    {
        $initialHeader = $this->menuService()->getHeaderTree('en');
        $this->assertNull($this->findMenuItem($initialHeader->items, 'Library'));

        $created = $this->menuService()->createItem($this->menuPayload('Library', null, '/en/library'));
        $afterCreate = $this->menuService()->getHeaderTree('en');

        $this->assertNotNull($this->findMenuItem($afterCreate->items, 'Library'));

        $this->assertTrue($this->menuService()->updateItem(
            $created->id,
            $this->menuPayload('Library Center', null, '/en/library-center'),
        ));
        $afterUpdate = $this->menuService()->getHeaderTree('en');
        $updatedItem = $this->findMenuItem($afterUpdate->items, 'Library Center');
        $this->assertInstanceOf(MenuItemDTO::class, $updatedItem);

        $secondary = $this->menuService()->createItem($this->menuPayload('Directory', null, '/en/directory'));
        $this->assertTrue($this->menuService()->reorderTree('header', [
            new MenuTreeNodeDTO(itemId: $secondary->id, sortOrder: 1, depth: 0),
            new MenuTreeNodeDTO(itemId: $updatedItem->id, sortOrder: 2, depth: 0),
        ]));
        $afterReorder = $this->menuService()->getHeaderTree('en');
        $labels = array_map(static fn (MenuItemDTO $item): string => $item->label, $afterReorder->items);
        $this->assertLessThan(array_search('Library Center', $labels, true), array_search('Directory', $labels, true));

        $this->assertTrue($this->menuService()->toggleItemState($updatedItem->id, false));
        $afterToggle = $this->menuService()->getHeaderTree('en');
        $this->assertNull($this->findMenuItem($afterToggle->items, 'Library Center'));

        $this->assertTrue($this->menuService()->deleteItem($secondary->id));
        $afterDelete = $this->menuService()->getHeaderTree('en');
        $this->assertNull($this->findMenuItem($afterDelete->items, 'Directory'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.reordered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.toggled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu.deleted']);
    }

    private function navigationService(): NavigationServiceInterface
    {
        return app(NavigationServiceInterface::class);
    }

    private function menuService(): MenuServiceInterface
    {
        return app(MenuServiceInterface::class);
    }

    private function menuPayload(string $label, ?int $parentId, string $url): MenuItemDataDTO
    {
        return new MenuItemDataDTO(
            label: $label,
            itemType: 'header',
            groupKey: 'header',
            locale: 'en',
            targetType: 'url',
            parentId: $parentId,
            targetId: null,
            url: $url,
            target: null,
            routeName: null,
            cssToken: null,
            icon: null,
            isEnabled: true,
            isUtility: false,
            openInNewTab: false,
            sortOrder: 0,
        );
    }

    /**
     * @param  array<int, MenuItemDTO>  $items
     */
    private function findMenuItem(array $items, string $label): ?MenuItemDTO
    {
        foreach ($items as $item) {
            if ($item->label === $label) {
                return $item;
            }

            $match = $this->findMenuItem($item->children, $label);

            if ($match instanceof MenuItemDTO) {
                return $match;
            }
        }

        return null;
    }
}
