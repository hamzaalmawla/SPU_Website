<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            $targetId = null;

            if ($item['target_kind'] === 'page' && $item['page_slug'] !== null) {
                $targetId = Page::query()->where('slug', $item['page_slug'])->value('id');
            }

            MenuItem::query()->updateOrCreate(
                [
                    'type' => $item['type'],
                    'locale' => $item['locale'],
                    'label' => $item['label'],
                ],
                [
                    'parent_id' => null,
                    'target_kind' => $item['target_kind'],
                    'target_id' => $targetId,
                    'url' => $item['url'],
                    'target' => $item['target'],
                    'route_name' => null,
                    'css_token' => null,
                    'icon' => $item['icon'],
                    'group_key' => $item['group_key'],
                    'is_enabled' => true,
                    'is_utility' => $item['is_utility'],
                    'open_in_new_tab' => $item['open_in_new_tab'],
                    'sort_order' => $item['sort_order'],
                    'depth' => 0,
                ]
            );
        }
    }

    /**
     * @return array<int, array{type: string, group_key: string, locale: string, label: string, target_kind: string, page_slug: ?string, url: ?string, target: ?string, icon: ?string, is_utility: bool, open_in_new_tab: bool, sort_order: int}>
     */
    private function items(): array
    {
        return [
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'عن الجامعة', 'target_kind' => 'page', 'page_slug' => 'about', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الكليات', 'target_kind' => 'page', 'page_slug' => 'faculties', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الأبحاث', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الفعاليات', 'target_kind' => 'page', 'page_slug' => 'events', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'About', 'target_kind' => 'page', 'page_slug' => 'about', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Faculties', 'target_kind' => 'page', 'page_slug' => 'faculties', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Research', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3],
            ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Events', 'target_kind' => 'page', 'page_slug' => 'events', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'ar', 'label' => 'القبول', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'ar', 'label' => 'اتصل بنا', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'en', 'label' => 'Admissions', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'en', 'label' => 'Contact', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'بوابة الطالب', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://students.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 1],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'بوابة الموظفين', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://staff.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 2],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Student Portal', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://students.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 1],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Staff Access', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://staff.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 2],
        ];
    }
}
