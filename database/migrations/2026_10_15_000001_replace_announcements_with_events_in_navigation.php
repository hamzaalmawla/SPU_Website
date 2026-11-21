<?php

declare(strict_types=1);

use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Navigation\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Announcements no longer have a page of their own (they are listed with the
 * news at /news/articles), so the menu item that pointed at them becomes
 * Events, the event cards page. Only items still carrying the default label
 * are changed: an item an editor renamed keeps its label and its link, which
 * redirects to the announcements filter and so stays correct.
 */
return new class extends Migration
{
    /** @var array<string, array{old_label: string, new_label: string}> */
    private const LABELS = [
        'ar' => ['old_label' => 'الإعلانات', 'new_label' => 'الفعاليات'],
        'en' => ['old_label' => 'Announcements', 'new_label' => 'Events'],
    ];

    public function up(): void
    {
        foreach (self::LABELS as $locale => $labels) {
            $items = MenuItem::query()
                ->where('locale', $locale)
                ->where('url', '/'.$locale.'/news/announcements')
                ->where('label', $labels['old_label'])
                ->get();

            foreach ($items as $item) {
                $eventsAlreadyListed = MenuItem::query()
                    ->where('locale', $locale)
                    ->where('parent_id', $item->parent_id)
                    ->where('url', '/'.$locale.'/news/events-list')
                    ->exists();

                $item->forceFill($eventsAlreadyListed
                    ? ['is_enabled' => false]
                    : ['label' => $labels['new_label'], 'url' => '/'.$locale.'/news/events-list'])
                    ->save();
            }
        }

        $this->forgetNavigationCaches();
    }

    public function down(): void
    {
        foreach (self::LABELS as $locale => $labels) {
            MenuItem::query()
                ->where('locale', $locale)
                ->where('url', '/'.$locale.'/news/events-list')
                ->where('label', $labels['new_label'])
                ->update(['label' => $labels['old_label'], 'url' => '/'.$locale.'/news/announcements']);
        }

        $this->forgetNavigationCaches();
    }

    private function forgetNavigationCaches(): void
    {
        $cache = app(CacheServiceInterface::class);

        foreach (['ar', 'en'] as $locale) {
            foreach (MenuServiceInterface::TREE_TYPES as $tree) {
                $cache->forget('menu.tree.'.$tree.'.'.$locale);
            }

            $cache->forget('navigation.payload.'.$locale);
        }

        $cache->flushTags(['public-pages', 'public-shell', 'navigation']);
    }
};
