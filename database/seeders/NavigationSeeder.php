<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Navigation\MenuItem;
use App\Models\Page\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds navigation trees matching the frontend's navigationMenuItems from layout-content.js.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $this->normalizeExistingLabels();

        foreach ($this->items() as $item) {
            $targetId = null;

            if ($item['target_kind'] === 'page' && $item['page_slug'] !== null) {
                $targetId = Page::query()->where('slug', $item['page_slug'])->value('id');
            }

            $parent = MenuItem::query()->updateOrCreate(
                [
                    'type' => $item['type'],
                    'group_key' => $item['group_key'],
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
                    'is_enabled' => true,
                    'is_utility' => $item['is_utility'],
                    'open_in_new_tab' => $item['open_in_new_tab'],
                    'sort_order' => $item['sort_order'],
                    'depth' => 0,
                ]
            );

            // Seed children if present
            if (! empty($item['children'])) {
                $desiredChildLabels = array_column($item['children'], 'label');

                MenuItem::query()
                    ->where('type', $item['type'])
                    ->where('group_key', $item['group_key'])
                    ->where('locale', $item['locale'])
                    ->where('parent_id', $parent->getKey())
                    ->whereNotIn('label', $desiredChildLabels)
                    ->update(['is_enabled' => false]);

                foreach ($item['children'] as $childIndex => $child) {
                    $childTargetId = null;
                    if (($child['target_kind'] ?? 'url') === 'page' && ! empty($child['page_slug'])) {
                        $childTargetId = Page::query()->where('slug', $child['page_slug'])->value('id');
                    }

                    MenuItem::query()->updateOrCreate(
                        [
                            'type' => $item['type'],
                            'group_key' => $item['group_key'],
                            'locale' => $item['locale'],
                            'label' => $child['label'],
                            'parent_id' => $parent->getKey(),
                        ],
                        [
                            'target_kind' => $child['target_kind'] ?? 'url',
                            'target_id' => $childTargetId,
                            'url' => $child['url'] ?? null,
                            'target' => $child['target'] ?? null,
                            'route_name' => null,
                            'css_token' => null,
                            'icon' => null,
                            'is_enabled' => true,
                            'is_utility' => false,
                            'open_in_new_tab' => $child['open_in_new_tab'] ?? false,
                            'sort_order' => $childIndex + 1,
                            'depth' => 1,
                        ]
                    );
                }
            }
        }

        Cache::forget('menu.tree.header.ar');
        Cache::forget('menu.tree.header.en');
        Cache::forget('navigation.payload.ar');
        Cache::forget('navigation.payload.en');
        Cache::flush();
    }

    private function normalizeExistingLabels(): void
    {
        MenuItem::query()
            ->where('type', 'header')
            ->where('group_key', 'header')
            ->where('locale', 'en')
            ->where('label', 'Faculties')
            ->update(['label' => 'Facilities']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return array_merge(
            $this->headerItems(),
            $this->footerItems(),
            $this->utilityItems(),
        );
    }

    /**
     * 8 header nav items matching frontend navigationMenuItems, for both AR and EN.
     *
     * @return array<int, array<string, mixed>>
     */
    private function headerItems(): array
    {
        $items = [];

        // ── About (sort 1) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'عن الجامعة', 'target_kind' => 'page', 'page_slug' => 'about', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => [
            ['label' => 'التاريخ والتأسيس', 'target_kind' => 'url', 'url' => '/ar/about/history'],
            ['label' => 'القيادة', 'target_kind' => 'url', 'url' => '/ar/about/leadership'],
            ['label' => 'المديريات المركزية', 'target_kind' => 'url', 'url' => '/ar/about/directorates'],
            ['label' => 'دليل الهيئة الأكاديمية', 'target_kind' => 'url', 'url' => '/ar/about/directorates/staff'],
            ['label' => 'الشراكات', 'target_kind' => 'url', 'url' => '/ar/about/partnerships'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'About', 'target_kind' => 'page', 'page_slug' => 'about', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => [
            ['label' => 'History & Founding', 'target_kind' => 'url', 'url' => '/en/about/history'],
            ['label' => 'Leadership', 'target_kind' => 'url', 'url' => '/en/about/leadership'],
            ['label' => 'Central Directorates', 'target_kind' => 'url', 'url' => '/en/about/directorates'],
            ['label' => 'Academic Staff Directory', 'target_kind' => 'url', 'url' => '/en/about/directorates/staff'],
            ['label' => 'Partnerships', 'target_kind' => 'url', 'url' => '/en/about/partnerships'],
        ]];

        // ── Facilities (sort 2) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الكليات', 'target_kind' => 'page', 'page_slug' => 'faculties', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => [
            ['label' => 'كلية الطب البشري', 'target_kind' => 'url', 'url' => '/ar/faculties/medicine'],
            ['label' => 'كلية طب الأسنان', 'target_kind' => 'url', 'url' => '/ar/faculties/dentistry'],
            ['label' => 'كلية الصيدلة', 'target_kind' => 'url', 'url' => '/ar/faculties/pharmacy'],
            ['label' => 'كلية هندسة الذكاء الاصطناعي', 'target_kind' => 'url', 'url' => '/ar/faculties/ai-engineering'],
            ['label' => 'كلية هندسة البناء', 'target_kind' => 'url', 'url' => '/ar/faculties/construction'],
            ['label' => 'كلية هندسة البترول', 'target_kind' => 'url', 'url' => '/ar/faculties/petroleum'],
            ['label' => 'كلية إدارة الأعمال', 'target_kind' => 'url', 'url' => '/ar/faculties/business'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Facilities', 'target_kind' => 'page', 'page_slug' => 'faculties', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => [
            ['label' => 'Medicine', 'target_kind' => 'url', 'url' => '/en/faculties/medicine'],
            ['label' => 'Dentistry', 'target_kind' => 'url', 'url' => '/en/faculties/dentistry'],
            ['label' => 'Pharmacy', 'target_kind' => 'url', 'url' => '/en/faculties/pharmacy'],
            ['label' => 'AI Engineering', 'target_kind' => 'url', 'url' => '/en/faculties/ai-engineering'],
            ['label' => 'Construction Engineering', 'target_kind' => 'url', 'url' => '/en/faculties/construction'],
            ['label' => 'Petroleum Engineering', 'target_kind' => 'url', 'url' => '/en/faculties/petroleum'],
            ['label' => 'Business Administration', 'target_kind' => 'url', 'url' => '/en/faculties/business'],
        ]];

        // ── Admissions (sort 3) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'القبول والتسجيل', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3, 'children' => [
            ['label' => 'شروط القبول', 'target_kind' => 'url', 'url' => '/ar/admissions#requirements'],
            ['label' => 'الرسوم الدراسية', 'target_kind' => 'url', 'url' => '/ar/admissions#fees'],
            ['label' => 'دعم القبول', 'target_kind' => 'url', 'url' => '/ar/contact'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Admissions', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3, 'children' => [
            ['label' => 'Admission Requirements', 'target_kind' => 'url', 'url' => '/en/admissions#requirements'],
            ['label' => 'Tuition Fees', 'target_kind' => 'url', 'url' => '/en/admissions#fees'],
            ['label' => 'Admissions Support', 'target_kind' => 'url', 'url' => '/en/contact'],
        ]];

        // ── Student Life (sort 4) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الحياة الجامعية', 'target_kind' => 'page', 'page_slug' => 'student-life', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4, 'children' => [
            ['label' => 'الخدمات الطلابية', 'target_kind' => 'url', 'url' => '/ar/student-life#services'],
            ['label' => 'الأنشطة والنوادي', 'target_kind' => 'url', 'url' => '/ar/student-life#activities'],
            ['label' => 'التقويم الأكاديمي', 'target_kind' => 'url', 'url' => '/ar/student-life#calendar'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Student Life', 'target_kind' => 'page', 'page_slug' => 'student-life', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4, 'children' => [
            ['label' => 'Student Services', 'target_kind' => 'url', 'url' => '/en/student-life#services'],
            ['label' => 'Activities & Clubs', 'target_kind' => 'url', 'url' => '/en/student-life#activities'],
            ['label' => 'Academic Calendar', 'target_kind' => 'url', 'url' => '/en/student-life#calendar'],
        ]];

        // ── E-Services (sort 5) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الخدمات الإلكترونية', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/e-services', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 5, 'children' => [
            ['label' => 'بوابة الطالب', 'target_kind' => 'url', 'url' => '/ar/e-services#portal-access'],
            ['label' => 'التسجيل', 'target_kind' => 'url', 'url' => '/ar/e-services#portal-access'],
            ['label' => 'وصول المكتبة', 'target_kind' => 'url', 'url' => '/ar/e-services#library'],
            ['label' => 'الاعتراضات والنماذج', 'target_kind' => 'url', 'url' => '/ar/e-services#appeals-forms'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'E-Services', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/e-services', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 5, 'children' => [
            ['label' => 'Student Portal', 'target_kind' => 'url', 'url' => '/en/e-services#portal-access'],
            ['label' => 'Registration', 'target_kind' => 'url', 'url' => '/en/e-services#portal-access'],
            ['label' => 'Library Access', 'target_kind' => 'url', 'url' => '/en/e-services#library'],
            ['label' => 'Appeals & Forms', 'target_kind' => 'url', 'url' => '/en/e-services#appeals-forms'],
        ]];

        // ── Research (sort 6) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'البحث العلمي', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 6, 'children' => []];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Research', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 6, 'children' => []];

        // ── News (sort 7) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الأخبار', 'target_kind' => 'page', 'page_slug' => 'news', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 7, 'children' => []];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'News', 'target_kind' => 'page', 'page_slug' => 'news', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 7, 'children' => []];

        // ── Contact (sort 8) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'تواصل معنا', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 8, 'children' => [
            ['label' => 'معلومات التواصل', 'target_kind' => 'url', 'url' => '/ar/contact'],
            ['label' => 'خريطة الحرم الجامعي', 'target_kind' => 'url', 'url' => '/ar/contact#campus-map'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Contact', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 8, 'children' => [
            ['label' => 'Contact Information', 'target_kind' => 'url', 'url' => '/en/contact'],
            ['label' => 'Campus Map', 'target_kind' => 'url', 'url' => '/en/contact#campus-map'],
        ]];

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function footerItems(): array
    {
        return [
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'ar', 'label' => 'القبول', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => []],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'ar', 'label' => 'اتصل بنا', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => []],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'en', 'label' => 'Admissions', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => []],
            ['type' => 'footer', 'group_key' => 'footer', 'locale' => 'en', 'label' => 'Contact', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => []],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function utilityItems(): array
    {
        return [
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'بوابة الطالب', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://students.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 1, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'بوابة الموظفين', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://staff.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 2, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Student Portal', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://students.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 1, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Staff Access', 'target_kind' => 'url', 'page_slug' => null, 'url' => 'https://staff.spu.edu.sy', 'target' => '_blank', 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => true, 'sort_order' => 2, 'children' => []],
        ];
    }
}
