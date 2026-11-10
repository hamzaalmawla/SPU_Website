<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts Centers in the header menu of each language, right after Faculties,
 * as a plain link (the Centers page lists every center). The menu is edited in Menu Builder, so
 * this only adds: a language whose header already links to /centers is left
 * alone, and the items after Faculties move down one place to make room.
 */
return new class extends Migration
{
    private const ITEMS = [
        'ar' => ['label' => 'المراكز', 'faculties' => 'الكليات'],
        'en' => ['label' => 'Centers', 'faculties' => 'Faculties'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ITEMS as $locale => $item) {
            $url = '/'.$locale.'/centers';
            $topLevel = fn () => DB::table('menu_items')
                ->where('type', 'header')
                ->where('group_key', 'header')
                ->where('locale', $locale)
                ->whereNull('parent_id')
                ->whereNull('deleted_at');

            if ($topLevel()->whereIn('url', [$url, $url.'/'])->exists()) {
                continue;
            }

            // No header at all in this language (a fresh database before seeding).
            if (! $topLevel()->exists()) {
                continue;
            }

            $faculties = $topLevel()
                ->where('is_enabled', true)
                ->where(fn ($query) => $query->whereIn('url', ['/'.$locale.'/faculties', '/'.$locale.'/facilities'])->orWhere('label', $item['faculties']))
                ->orderBy('sort_order')
                ->first(['sort_order']);

            if ($faculties !== null) {
                $position = (int) $faculties->sort_order + 1;
                $topLevel()->where('sort_order', '>=', $position)->increment('sort_order');
            } else {
                $position = (int) $topLevel()->max('sort_order') + 1;
            }

            DB::table('menu_items')->insert([
                'parent_id' => null,
                'type' => 'header',
                'label' => $item['label'],
                'locale' => $locale,
                'target_kind' => 'url',
                'url' => $url,
                'group_key' => 'header',
                'is_enabled' => true,
                'is_utility' => false,
                'open_in_new_tab' => false,
                'sort_order' => $position,
                'depth' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        }
    }

    public function down(): void
    {
        // Keep the menu as editors have left it.
    }
};
