<?php

declare(strict_types=1);

use App\Models\Navigation\MenuItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['locale' => 'ar', 'parent' => 'الأخبار', 'label' => 'الاتفاقيات ومذكرات التفاهم', 'url' => '/ar/news/agreements'],
            ['locale' => 'en', 'parent' => 'News', 'label' => 'Agreements and Memoranda of Understanding', 'url' => '/en/news/agreements'],
        ];

        foreach ($items as $item) {
            $parent = MenuItem::query()
                ->where('type', 'header')
                ->where('group_key', 'header')
                ->where('locale', $item['locale'])
                ->where('label', $item['parent'])
                ->whereNull('parent_id')
                ->first();

            if ($parent === null) {
                continue;
            }

            MenuItem::query()->updateOrCreate(
                [
                    'type' => 'header',
                    'group_key' => 'header',
                    'locale' => $item['locale'],
                    'label' => $item['label'],
                    'parent_id' => $parent->getKey(),
                ],
                [
                    'target_kind' => 'url',
                    'url' => $item['url'],
                    'is_enabled' => true,
                    'is_utility' => false,
                    'open_in_new_tab' => false,
                    'sort_order' => 2,
                    'depth' => 1,
                ],
            );
        }
    }

    public function down(): void
    {
        MenuItem::query()->whereIn('url', ['/ar/news/agreements', '/en/news/agreements'])->delete();
    }
};
