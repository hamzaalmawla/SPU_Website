<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Navigation\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the menu migration that turns the retired Announcements item into Events.
 */
class NavigationAnnouncementsToEventsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_announcements_items_become_events_and_renamed_ones_are_left_alone(): void
    {
        $parent = $this->item(['label' => 'News', 'url' => null]);
        $default = $this->item(['label' => 'Announcements', 'url' => '/en/news/announcements', 'parent_id' => $parent->id, 'depth' => 1]);
        $arabic = $this->item(['locale' => 'ar', 'label' => 'الإعلانات', 'url' => '/ar/news/announcements', 'parent_id' => $parent->id, 'depth' => 1]);
        $renamed = $this->item(['label' => 'Notice Board', 'url' => '/en/news/announcements', 'parent_id' => $parent->id, 'depth' => 1]);

        $this->migration()->up();

        $this->assertSame(['Events', '/en/news/events-list'], [$default->fresh()->label, $default->fresh()->url]);
        $this->assertSame(['الفعاليات', '/ar/news/events-list'], [$arabic->fresh()->label, $arabic->fresh()->url]);
        $this->assertSame(['Notice Board', '/en/news/announcements'], [$renamed->fresh()->label, $renamed->fresh()->url]);

        // Running it again changes nothing.
        $this->migration()->up();
        $this->assertSame(1, MenuItem::query()->where('url', '/en/news/events-list')->count());
    }

    public function test_an_existing_events_item_is_kept_and_the_announcements_item_is_hidden(): void
    {
        $parent = $this->item(['label' => 'News', 'url' => null]);
        $this->item(['label' => 'Events', 'url' => '/en/news/events-list', 'parent_id' => $parent->id, 'depth' => 1]);
        $announcements = $this->item(['label' => 'Announcements', 'url' => '/en/news/announcements', 'parent_id' => $parent->id, 'depth' => 1]);

        $this->migration()->up();

        $this->assertFalse((bool) $announcements->fresh()->is_enabled);
        $this->assertSame(1, MenuItem::query()->where('url', '/en/news/events-list')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_15_000001_replace_announcements_with_events_in_navigation.php');
    }

    /** @param array<string, mixed> $attributes */
    private function item(array $attributes): MenuItem
    {
        $item = new MenuItem;
        $item->forceFill(array_merge([
            'type' => 'header',
            'group_key' => 'header',
            'locale' => 'en',
            'parent_id' => null,
            'target_kind' => 'url',
            'is_enabled' => true,
            'is_utility' => false,
            'open_in_new_tab' => false,
            'sort_order' => 1,
            'depth' => 0,
        ], $attributes))->save();

        return $item;
    }
}
