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
        $items = $this->items();
        $this->disableExistingSeededParents($items);

        foreach ($items as $item) {
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

            $this->syncChildren($parent, $item['children'] ?? []);
        }

        Cache::forget('menu.tree.header.ar');
        Cache::forget('menu.tree.header.en');
        Cache::forget('navigation.payload.ar');
        Cache::forget('navigation.payload.en');
        Cache::flush();
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     */
    private function syncChildren(MenuItem $parent, array $children): void
    {
        $desiredChildLabels = array_values(array_filter(array_column($children, 'label'), 'is_string'));

        $staleChildren = MenuItem::query()
            ->where('type', $parent->type)
            ->where('group_key', $parent->group_key)
            ->where('locale', $parent->locale)
            ->where('parent_id', $parent->getKey());

        if ($desiredChildLabels === []) {
            $staleChildren->update(['is_enabled' => false]);
        } else {
            $staleChildren->whereNotIn('label', $desiredChildLabels)->update(['is_enabled' => false]);
        }

        foreach ($children as $childIndex => $child) {
            $childTargetId = null;

            if (($child['target_kind'] ?? 'url') === 'page' && ! empty($child['page_slug'])) {
                $childTargetId = Page::query()->where('slug', $child['page_slug'])->value('id');
            }

            $childItem = MenuItem::query()->updateOrCreate(
                [
                    'type' => $parent->type,
                    'group_key' => $parent->group_key,
                    'locale' => $parent->locale,
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
                    'depth' => ((int) $parent->depth) + 1,
                ]
            );

            $this->syncChildren($childItem, $child['children'] ?? []);
        }
    }

    /**
     * Disable stale top-level CMS rows before seeding the frontend source-of-truth menu.
     * This prevents old labels like "Faculties" from surviving beside "Facilities".
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function disableExistingSeededParents(array $items): void
    {
        collect($items)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'group_key' => $item['group_key'],
                'locale' => $item['locale'],
            ])
            ->unique(fn (array $item): string => $item['type'].'|'.$item['group_key'].'|'.($item['locale'] ?? ''))
            ->each(function (array $item): void {
                MenuItem::query()
                    ->where('type', $item['type'])
                    ->where('group_key', $item['group_key'])
                    ->where('locale', $item['locale'])
                    ->whereNull('parent_id')
                    ->update(['is_enabled' => false]);
            });

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
            ['label' => 'الرؤية والرسالة', 'target_kind' => 'url', 'url' => '/ar/about/vision-mission'],
            ['label' => 'التاريخ والتأسيس', 'target_kind' => 'url', 'url' => '/ar/about/history'],
            ['label' => 'القيادة', 'target_kind' => 'url', 'url' => '/ar/about/leadership'],
            ['label' => 'المديريات المركزية', 'target_kind' => 'url', 'url' => '/ar/about/directorates'],
            ['label' => 'دليل الهيئة الأكاديمية', 'target_kind' => 'url', 'url' => '/ar/about/directorates/staff'],
            ['label' => 'الشراكات', 'target_kind' => 'url', 'url' => '/ar/about/partnerships'],
            ['label' => 'سياسة الجودة', 'target_kind' => 'url', 'url' => '/ar/about/quality-policy'],
            ['label' => 'الميثاق الأخلاقي', 'target_kind' => 'url', 'url' => '/ar/about/ethical-charter'],
            ['label' => 'الهيكل التنظيمي', 'target_kind' => 'url', 'url' => '/ar/about/organizational-structure'],
            ['label' => 'الاعتمادية', 'target_kind' => 'url', 'url' => '/ar/about/accreditation'],
            ['label' => 'لماذا SPU', 'target_kind' => 'url', 'url' => '/ar/about/why-spu'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'About', 'target_kind' => 'page', 'page_slug' => 'about', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => [
            ['label' => 'Vision and Mission', 'target_kind' => 'url', 'url' => '/en/about/vision-mission'],
            ['label' => 'History & Founding', 'target_kind' => 'url', 'url' => '/en/about/history'],
            ['label' => 'Leadership', 'target_kind' => 'url', 'url' => '/en/about/leadership'],
            ['label' => 'Central Directorates', 'target_kind' => 'url', 'url' => '/en/about/directorates'],
            ['label' => 'Academic Staff Directory', 'target_kind' => 'url', 'url' => '/en/about/directorates/staff'],
            ['label' => 'Partnerships', 'target_kind' => 'url', 'url' => '/en/about/partnerships'],
            ['label' => 'Quality Policy', 'target_kind' => 'url', 'url' => '/en/about/quality-policy'],
            ['label' => 'Ethical Charter', 'target_kind' => 'url', 'url' => '/en/about/ethical-charter'],
            ['label' => 'Organizational Structure', 'target_kind' => 'url', 'url' => '/en/about/organizational-structure'],
            ['label' => 'Accreditation', 'target_kind' => 'url', 'url' => '/en/about/accreditation'],
            ['label' => 'Why SPU', 'target_kind' => 'url', 'url' => '/en/about/why-spu'],
        ]];

        // ── Faculties (sort 2) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'المرافق', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/facilities', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => [
            ['label' => 'كلية الطب البشري', 'target_kind' => 'url', 'url' => '/ar/facilities/medicine'],
            ['label' => 'كلية طب الأسنان', 'target_kind' => 'url', 'url' => '/ar/facilities/dentistry'],
            ['label' => 'كلية الصيدلة', 'target_kind' => 'url', 'url' => '/ar/facilities/pharmacy'],
            ['label' => 'كلية هندسة الذكاء الاصطناعي', 'target_kind' => 'url', 'url' => '/ar/facilities/artificial-intelligence'],
            ['label' => 'كلية هندسة البناء', 'target_kind' => 'url', 'url' => '/ar/facilities/building-construction-engineering'],
            ['label' => 'كلية هندسة البترول', 'target_kind' => 'url', 'url' => '/ar/facilities/petroleum'],
            ['label' => 'كلية إدارة الأعمال', 'target_kind' => 'url', 'url' => '/ar/facilities/business-administration'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Facilities', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/facilities', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => [
            ['label' => 'Medicine', 'target_kind' => 'url', 'url' => '/en/facilities/medicine'],
            ['label' => 'Dentistry', 'target_kind' => 'url', 'url' => '/en/facilities/dentistry'],
            ['label' => 'Pharmacy', 'target_kind' => 'url', 'url' => '/en/facilities/pharmacy'],
            ['label' => 'AI Engineering', 'target_kind' => 'url', 'url' => '/en/facilities/artificial-intelligence'],
            ['label' => 'Construction Engineering', 'target_kind' => 'url', 'url' => '/en/facilities/building-construction-engineering'],
            ['label' => 'Petroleum Engineering', 'target_kind' => 'url', 'url' => '/en/facilities/petroleum'],
            ['label' => 'Business Administration', 'target_kind' => 'url', 'url' => '/en/facilities/business-administration'],
        ]];

        // ── Admissions (sort 3) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'القبول والتسجيل', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3, 'children' => [
            ['label' => 'شروط القبول', 'target_kind' => 'url', 'url' => '/ar/admissions/requirements'],
            ['label' => 'الرسوم الدراسية', 'target_kind' => 'url', 'url' => '/ar/admissions/tuition'],
            ['label' => 'كيفية التقديم', 'target_kind' => 'url', 'url' => '/ar/admissions/how-to-apply'],
            ['label' => 'التحويل والطلاب الدوليون', 'target_kind' => 'url', 'url' => '/ar/admissions/transfer'],
            ['label' => 'التقويم الأكاديمي', 'target_kind' => 'url', 'url' => '/ar/admissions/calendar'],
            ['label' => 'الوثائق وقوائم التحقق', 'target_kind' => 'url', 'url' => '/ar/admissions/documents'],
            ['label' => 'ملء الشواغر', 'target_kind' => 'url', 'url' => '/ar/admissions/filling-vacancies'],
            ['label' => 'التخرج والامتحانات الوطنية', 'target_kind' => 'url', 'url' => '/ar/admissions/graduation-exams'],
            ['label' => 'الأسئلة الشائعة', 'target_kind' => 'url', 'url' => '/ar/admissions/faq'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Admissions', 'target_kind' => 'page', 'page_slug' => 'admissions', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 3, 'children' => [
            ['label' => 'Admission Requirements', 'target_kind' => 'url', 'url' => '/en/admissions/requirements'],
            ['label' => 'Tuition & Fees', 'target_kind' => 'url', 'url' => '/en/admissions/tuition'],
            ['label' => 'How to Apply', 'target_kind' => 'url', 'url' => '/en/admissions/how-to-apply'],
            ['label' => 'Transfer & International', 'target_kind' => 'url', 'url' => '/en/admissions/transfer'],
            ['label' => 'Academic Calendar', 'target_kind' => 'url', 'url' => '/en/admissions/calendar'],
            ['label' => 'Documents & Checklists', 'target_kind' => 'url', 'url' => '/en/admissions/documents'],
            ['label' => 'Filling Vacancies', 'target_kind' => 'url', 'url' => '/en/admissions/filling-vacancies'],
            ['label' => 'Graduation & National Exams', 'target_kind' => 'url', 'url' => '/en/admissions/graduation-exams'],
            ['label' => 'FAQs', 'target_kind' => 'url', 'url' => '/en/admissions/faq'],
        ]];

        // ── Research (sort 4) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'البحث العلمي', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4, 'children' => $this->researchChildren('ar')];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Research', 'target_kind' => 'page', 'page_slug' => 'research', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 4, 'children' => $this->researchChildren('en')];

        // ── Campus Life (sort 5) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الحياة الجامعية', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/campus-life', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 5, 'children' => [
            ['label' => 'خدمات الحرم الجامعي', 'target_kind' => 'url', 'url' => '/ar/campus-life/services'],
            ['label' => 'النقل', 'target_kind' => 'url', 'url' => '/ar/campus-life/transport'],
            ['label' => 'الصحة والتأمين', 'target_kind' => 'url', 'url' => '/ar/campus-life/health-insurance'],
            ['label' => 'النوادي والأنشطة', 'target_kind' => 'url', 'url' => '/ar/campus-life/clubs-activities'],
            ['label' => 'التطوير المهني', 'target_kind' => 'url', 'url' => '/ar/campus-life/career-development'],
            ['label' => 'لوحة الوظائف', 'target_kind' => 'url', 'url' => '/ar/campus-life/career-development/jobs'],
            ['label' => 'المستشفى الجامعي', 'target_kind' => 'url', 'url' => '/ar/campus-life/hospital'],
            ['label' => 'عيادات الأسنان', 'target_kind' => 'url', 'url' => '/ar/campus-life/dental'],
            ['label' => 'منشورات مركز دمشق للأبحاث', 'target_kind' => 'url', 'url' => '/ar/campus-life/damascus-research-pub'],
            ['label' => 'أنظمة وتعليمات', 'target_kind' => 'url', 'url' => '/ar/campus-life/rules-regulations'],
            ['label' => 'قواعد وتعليمات عامة', 'target_kind' => 'url', 'url' => '/ar/campus-life/general-rules'],
            ['label' => 'التعليمات الامتحانية', 'target_kind' => 'url', 'url' => '/ar/campus-life/exam-instructions'],
            ['label' => 'العقوبات الامتحانية', 'target_kind' => 'url', 'url' => '/ar/campus-life/exam-penalties'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Campus Life', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/campus-life', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 5, 'children' => [
            ['label' => 'Campus Services', 'target_kind' => 'url', 'url' => '/en/campus-life/services'],
            ['label' => 'Transport', 'target_kind' => 'url', 'url' => '/en/campus-life/transport'],
            ['label' => 'Health & Insurance', 'target_kind' => 'url', 'url' => '/en/campus-life/health-insurance'],
            ['label' => 'Clubs & Activities', 'target_kind' => 'url', 'url' => '/en/campus-life/clubs-activities'],
            ['label' => 'Career Development', 'target_kind' => 'url', 'url' => '/en/campus-life/career-development'],
            ['label' => 'Job Board', 'target_kind' => 'url', 'url' => '/en/campus-life/career-development/jobs'],
            ['label' => 'University Hospital', 'target_kind' => 'url', 'url' => '/en/campus-life/hospital'],
            ['label' => 'Dental Clinics', 'target_kind' => 'url', 'url' => '/en/campus-life/dental'],
            ['label' => 'Damascus Research Center', 'target_kind' => 'url', 'url' => '/en/campus-life/damascus-research-pub'],
            ['label' => 'Rules & Regulations', 'target_kind' => 'url', 'url' => '/en/campus-life/rules-regulations'],
            ['label' => 'General Rules', 'target_kind' => 'url', 'url' => '/en/campus-life/general-rules'],
            ['label' => 'Exam Instructions', 'target_kind' => 'url', 'url' => '/en/campus-life/exam-instructions'],
            ['label' => 'Exam Penalties', 'target_kind' => 'url', 'url' => '/en/campus-life/exam-penalties'],
        ]];

        // ── E-Services (sort 6) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الخدمات الإلكترونية', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/e-services', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 6, 'children' => [
            ['label' => 'بوابة الخدمات', 'target_kind' => 'url', 'url' => '/ar/e-services#portal-access'],
            ['label' => 'مساعدة وصول الطلاب', 'target_kind' => 'url', 'url' => '/ar/e-services/it-support'],
            ['label' => 'المكتبة الإلكترونية', 'target_kind' => 'url', 'url' => '/ar/e-services/library'],
            ['label' => 'دعم تكنولوجيا المعلومات', 'target_kind' => 'url', 'url' => '/ar/e-services/it-support'],
            ['label' => 'إرشادات الخدمات', 'target_kind' => 'url', 'url' => '/ar/e-services#appeals-forms'],
            ['label' => 'البريد الإلكتروني للموظفين', 'target_kind' => 'url', 'url' => '/ar/e-services/staff-email'],
            ['label' => 'الاقتراحات والشكاوى', 'target_kind' => 'url', 'url' => '/ar/e-services/suggestions-complaints'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'E-Services', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/e-services', 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 6, 'children' => [
            ['label' => 'Service Portal', 'target_kind' => 'url', 'url' => '/en/e-services#portal-access'],
            ['label' => 'Student Access Help', 'target_kind' => 'url', 'url' => '/en/e-services/it-support'],
            ['label' => 'E-Library', 'target_kind' => 'url', 'url' => '/en/e-services/library'],
            ['label' => 'IT Support', 'target_kind' => 'url', 'url' => '/en/e-services/it-support'],
            ['label' => 'Service Guidance', 'target_kind' => 'url', 'url' => '/en/e-services#appeals-forms'],
            ['label' => 'Staff Email', 'target_kind' => 'url', 'url' => '/en/e-services/staff-email'],
            ['label' => 'Suggestions & Complaints', 'target_kind' => 'url', 'url' => '/en/e-services/suggestions-complaints'],
        ]];

        // ── News (sort 7) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'الأخبار', 'target_kind' => 'page', 'page_slug' => 'news', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 7, 'children' => [
            ['label' => 'الأخبار', 'target_kind' => 'url', 'url' => '/ar/news/articles'],
            ['label' => 'الإعلانات', 'target_kind' => 'url', 'url' => '/ar/news/announcements'],
            ['label' => 'تقويم الفعاليات', 'target_kind' => 'url', 'url' => '/ar/news/events'],
            ['label' => 'معرض الوسائط', 'target_kind' => 'url', 'url' => '/ar/news/gallery'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'News', 'target_kind' => 'page', 'page_slug' => 'news', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 7, 'children' => [
            ['label' => 'News', 'target_kind' => 'url', 'url' => '/en/news/articles'],
            ['label' => 'Announcements', 'target_kind' => 'url', 'url' => '/en/news/announcements'],
            ['label' => 'Events Calendar', 'target_kind' => 'url', 'url' => '/en/news/events'],
            ['label' => 'Media Gallery', 'target_kind' => 'url', 'url' => '/en/news/gallery'],
        ]];

        // ── Contact (sort 8) ──
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'ar', 'label' => 'تواصل معنا', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 8, 'children' => [
            ['label' => 'نموذج التواصل', 'target_kind' => 'url', 'url' => '/ar/contact#contact-form'],
            ['label' => 'معلومات التواصل', 'target_kind' => 'url', 'url' => '/ar/contact#contact-info'],
            ['label' => 'خريطة الحرم الجامعي', 'target_kind' => 'url', 'url' => '/ar/contact#campus-map'],
        ]];
        $items[] = ['type' => 'header', 'group_key' => 'header', 'locale' => 'en', 'label' => 'Contact', 'target_kind' => 'page', 'page_slug' => 'contact', 'url' => null, 'target' => null, 'icon' => null, 'is_utility' => false, 'open_in_new_tab' => false, 'sort_order' => 8, 'children' => [
            ['label' => 'Contact Form', 'target_kind' => 'url', 'url' => '/en/contact#contact-form'],
            ['label' => 'Contact Information', 'target_kind' => 'url', 'url' => '/en/contact#contact-info'],
            ['label' => 'Campus Map', 'target_kind' => 'url', 'url' => '/en/contact#campus-map'],
        ]];

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function researchChildren(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                ['label' => 'المنشورات البحثية', 'target_kind' => 'url', 'url' => '/ar/research/publications', 'children' => [
                    ['label' => 'تطبيقات تعلم الآلة في مراقبة جودة الأدوية', 'target_kind' => 'url', 'url' => '/ar/research/publications/machine-learning-pharmaceutical-quality-control'],
                    ['label' => 'نماذج الذكاء الاصطناعي للكشف المبكر عن تسوس الأسنان', 'target_kind' => 'url', 'url' => '/ar/research/publications/ai-dental-diagnostics'],
                    ['label' => 'إطار التعلم العميق للتنبؤ بنفاذية المكامن', 'target_kind' => 'url', 'url' => '/ar/research/publications/deep-learning-reservoir-permeability'],
                ]],
                ['label' => 'الباحثون', 'target_kind' => 'url', 'url' => '/ar/research/researchers', 'children' => [
                    ['label' => 'د. مهيب النقري - الذكاء الاصطناعي', 'target_kind' => 'url', 'url' => '/ar/research/researchers/mouhib-alnoukari'],
                    ['label' => 'د. أيمن علي - الطب', 'target_kind' => 'url', 'url' => '/ar/research/researchers/ayman-ali'],
                    ['label' => 'د. محمود حديد - هندسة البترول', 'target_kind' => 'url', 'url' => '/ar/research/researchers/mahmoud-hadid'],
                ]],
                ['label' => 'مجالات البحث', 'target_kind' => 'url', 'url' => '/ar/research/themes', 'children' => [
                    ['label' => 'الذكاء الاصطناعي وتعلم الآلة', 'target_kind' => 'url', 'url' => '/ar/research/themes/ai-ml'],
                    ['label' => 'العلوم الصيدلانية', 'target_kind' => 'url', 'url' => '/ar/research/themes/pharmaceutical-sciences'],
                    ['label' => 'الطب السريري', 'target_kind' => 'url', 'url' => '/ar/research/themes/clinical-medicine'],
                ]],
                ['label' => 'مشاريع البحث', 'target_kind' => 'url', 'url' => '/ar/research/projects', 'children' => [
                    ['label' => 'نظام الكشف عن تسوس الأسنان بالذكاء الاصطناعي', 'target_kind' => 'url', 'url' => '/ar/research/projects/ai-dental-diagnostics-system'],
                    ['label' => 'إطار NLP العربي للسجلات الطبية', 'target_kind' => 'url', 'url' => '/ar/research/projects/arabic-clinical-nlp-system'],
                ]],
                ['label' => 'مراكز البحث', 'target_kind' => 'url', 'url' => '/ar/research/centers', 'children' => [
                    ['label' => 'مركز الذكاء الاصطناعي والابتكار الرقمي', 'target_kind' => 'url', 'url' => '/ar/research/centers/ai-digital-innovation'],
                    ['label' => 'مختبر البحث السريري والمحاكاة', 'target_kind' => 'url', 'url' => '/ar/research/centers/clinical-research-simulation'],
                ]],
                ['label' => 'الباحث عن الخبراء', 'target_kind' => 'url', 'url' => '/ar/research/expert-finder'],
                ['label' => 'المؤتمرات والندوات', 'target_kind' => 'url', 'url' => '/ar/research/conferences'],
                ['label' => 'مكتبة البحث', 'target_kind' => 'url', 'url' => '/ar/research/library'],
                ['label' => 'السياسات والأخلاقيات', 'target_kind' => 'url', 'url' => '/ar/research/policies'],
                ['label' => 'مكتب البحث', 'target_kind' => 'url', 'url' => '/ar/research/office'],
            ];
        }

        return [
            ['label' => 'Publications', 'target_kind' => 'url', 'url' => '/en/research/publications', 'children' => [
                ['label' => 'Machine Learning in Pharmaceutical Quality Control', 'target_kind' => 'url', 'url' => '/en/research/publications/machine-learning-pharmaceutical-quality-control'],
                ['label' => 'AI for Early Dental Caries Detection', 'target_kind' => 'url', 'url' => '/en/research/publications/ai-dental-diagnostics'],
                ['label' => 'Deep Learning for Reservoir Permeability', 'target_kind' => 'url', 'url' => '/en/research/publications/deep-learning-reservoir-permeability'],
            ]],
            ['label' => 'Researchers', 'target_kind' => 'url', 'url' => '/en/research/researchers', 'children' => [
                ['label' => 'Dr. Mouhib Alnoukari - AI', 'target_kind' => 'url', 'url' => '/en/research/researchers/mouhib-alnoukari'],
                ['label' => 'Dr. Ayman Ali - Medicine', 'target_kind' => 'url', 'url' => '/en/research/researchers/ayman-ali'],
                ['label' => 'Dr. Mahmoud Hadid - Petroleum', 'target_kind' => 'url', 'url' => '/en/research/researchers/mahmoud-hadid'],
            ]],
            ['label' => 'Research Themes', 'target_kind' => 'url', 'url' => '/en/research/themes', 'children' => [
                ['label' => 'Artificial Intelligence & Machine Learning', 'target_kind' => 'url', 'url' => '/en/research/themes/ai-ml'],
                ['label' => 'Pharmaceutical Sciences', 'target_kind' => 'url', 'url' => '/en/research/themes/pharmaceutical-sciences'],
                ['label' => 'Clinical Medicine', 'target_kind' => 'url', 'url' => '/en/research/themes/clinical-medicine'],
            ]],
            ['label' => 'Research Projects', 'target_kind' => 'url', 'url' => '/en/research/projects', 'children' => [
                ['label' => 'AI Dental Caries Detection System', 'target_kind' => 'url', 'url' => '/en/research/projects/ai-dental-diagnostics-system'],
                ['label' => 'Arabic Clinical NLP System', 'target_kind' => 'url', 'url' => '/en/research/projects/arabic-clinical-nlp-system'],
            ]],
            ['label' => 'Research Centers', 'target_kind' => 'url', 'url' => '/en/research/centers', 'children' => [
                ['label' => 'Center for AI & Digital Innovation', 'target_kind' => 'url', 'url' => '/en/research/centers/ai-digital-innovation'],
                ['label' => 'Clinical Research & Simulation Lab', 'target_kind' => 'url', 'url' => '/en/research/centers/clinical-research-simulation'],
            ]],
            ['label' => 'Expert Finder', 'target_kind' => 'url', 'url' => '/en/research/expert-finder'],
            ['label' => 'Conferences & Seminars', 'target_kind' => 'url', 'url' => '/en/research/conferences'],
            ['label' => 'Research Library', 'target_kind' => 'url', 'url' => '/en/research/library'],
            ['label' => 'Policies & Ethics', 'target_kind' => 'url', 'url' => '/en/research/policies'],
            ['label' => 'Research Office', 'target_kind' => 'url', 'url' => '/en/research/office'],
        ];
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
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'مساعدة وصول الطلاب', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/e-services/it-support', 'target' => null, 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'ar', 'label' => 'بوابة الموظفين', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/ar/e-services/staff-email', 'target' => null, 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Student Access Help', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/e-services/it-support', 'target' => null, 'icon' => 'heroicon-o-academic-cap', 'is_utility' => true, 'open_in_new_tab' => false, 'sort_order' => 1, 'children' => []],
            ['type' => 'utility', 'group_key' => 'utility', 'locale' => 'en', 'label' => 'Staff Access', 'target_kind' => 'url', 'page_slug' => null, 'url' => '/en/e-services/staff-email', 'target' => null, 'icon' => 'heroicon-o-briefcase', 'is_utility' => true, 'open_in_new_tab' => false, 'sort_order' => 2, 'children' => []],
        ];
    }
}
