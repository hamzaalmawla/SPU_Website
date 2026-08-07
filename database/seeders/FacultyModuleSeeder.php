<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FacultyModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->faculties() as $index => $faculty) {
            $facultyId = $this->seedFaculty($faculty, $index + 1);
            $this->seedPages($facultyId, $faculty);
            $this->seedSubpageCards($faculty, $facultyId);
            $this->seedDepartments($facultyId, $faculty);
            $this->seedHighlights($facultyId, $faculty);
            $this->seedLabs($facultyId, $faculty);
            $this->seedProjects($facultyId, $faculty);
            $this->seedAlumni($facultyId, $faculty);
            $this->seedHonorStudents($facultyId, $faculty);
        }

        Cache::flush();
    }

    /** @param array<string, mixed> $faculty */
    private function seedFaculty(array $faculty, int $sortOrder): int
    {
        $now = now();
        $pages = ['overview', 'departments', 'study-plan', 'study-plan-course', 'labs', 'projects', 'research', 'alumni', 'valedictorians'];

        if ($faculty['public_slug'] === 'pharmacy') {
            $pages[] = 'training';
        }

        $facultyId = (int) DB::table('faculties')->updateOrInsert(
            ['slug' => $faculty['slug']],
            [
                'public_slug' => $faculty['public_slug'],
                'faculty_scope_slug' => $faculty['public_slug'],
                'accent_color' => $faculty['accent'],
                'hero_image' => $faculty['hero_image'],
                'logo_image' => $faculty['logo'],
                'gallery_json' => json_encode($faculty['gallery'], JSON_THROW_ON_ERROR),
                'subpages_json' => json_encode($pages, JSON_THROW_ON_ERROR),
                'sort_order' => $sortOrder,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $facultyId = (int) DB::table('faculties')->where('slug', $faculty['slug'])->value('id');

        foreach (['ar', 'en'] as $locale) {
            DB::table('faculty_translations')->updateOrInsert(
                ['faculty_id' => $facultyId, 'locale' => $locale],
                [
                    'name' => $faculty[$locale]['name'],
                    'catalog_title' => $faculty[$locale]['catalog_title'],
                    'short_description' => $faculty[$locale]['summary'],
                    'description' => $faculty[$locale]['description'],
                    'years_label' => $faculty[$locale]['years'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return $facultyId;
    }

    /** @param array<string, mixed> $faculty */
    private function seedPages(int $facultyId, array $faculty): void
    {
        $pages = ['overview', 'departments', 'study-plan', 'study-plan-course', 'labs', 'projects', 'research', 'alumni', 'valedictorians'];

        if ($faculty['public_slug'] === 'pharmacy') {
            $pages[] = 'training';
        }

        DB::table('faculty_pages')
            ->where('faculty_id', $facultyId)
            ->whereNotIn('slug', $pages)
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ($pages as $sort => $slug) {
            $pageId = $this->upsertPage($facultyId, $faculty, $slug, $sort + 1);
            $this->upsertPageTranslations($pageId, $faculty, $slug);
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedSubpageCards(array $faculty, int $facultyId): void
    {
        $facultySlug = $faculty['public_slug'];
        $pages = ['overview', 'departments', 'study-plan', 'study-plan-course', 'labs', 'projects', 'research', 'alumni', 'valedictorians'];

        if ($facultySlug === 'pharmacy') {
            $pages[] = 'training';
        }

        DB::table('faculty_subpage_cards')
            ->where('faculty_slug', $facultySlug)
            ->whereNotIn('subpage_slug', $pages)
            ->delete();

        foreach ($pages as $sort => $slug) {
            $now = now();
            $inserted = DB::table('faculty_subpage_cards')->insertOrIgnore([
                'faculty_slug' => $facultySlug,
                'subpage_slug' => $slug,
                'sort_order' => $sort + 1,
                'is_visible' => true,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                DB::table('faculty_subpage_cards')
                    ->where('faculty_slug', $facultySlug)
                    ->where('subpage_slug', $slug)
                    ->update(['sort_order' => $sort + 1, 'updated_at' => $now]);
            }
        }
    }

    /** @param array<string, mixed> $faculty */
    private function upsertPage(int $facultyId, array $faculty, string $slug, int $sortOrder): int
    {
        $now = now();
        $payload = $slug === 'overview' ? [
            'stats' => $faculty['stats'],
            'dean' => $faculty['dean'],
        ] : [];

        if ($slug === 'departments' && is_array($faculty['department_page'] ?? null)) {
            $payload = $faculty['department_page'];
        }

        if ($slug === 'training') {
            $payload = $this->pharmacyTrainingPayload();
        }

        if (in_array($slug, ['study-plan', 'study-plan-course'], true)) {
            $payload = $this->studyPlanPayload($faculty, $slug);
        }

        DB::table('faculty_pages')->updateOrInsert(
            ['faculty_id' => $facultyId, 'slug' => $slug],
            [
                'kind' => $slug,
                'hero_image' => $slug === 'departments'
                    ? ($faculty['department_page']['heroImage'] ?? $faculty['subpage_image'] ?? $faculty['hero_image'])
                    : ($slug === 'overview' ? $faculty['hero_image'] : ($faculty['subpage_image'] ?? $faculty['hero_image'])),
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
                'sort_order' => $sortOrder,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return (int) DB::table('faculty_pages')->where('faculty_id', $facultyId)->where('slug', $slug)->value('id');
    }

    /** @param array<string, mixed> $faculty */
    private function upsertPageTranslations(int $pageId, array $faculty, string $slug): void
    {
        $now = now();
        $labels = $this->subpageLabels($slug);
        $meta = [];

        foreach (['ar', 'en'] as $locale) {
            $summary = $slug === 'overview'
                ? $faculty[$locale]['summary']
                : $this->subpageSummary($slug, $faculty[$locale]['name'], $locale);

            if ($slug === 'departments' && is_array($faculty['department_page'][$locale] ?? null)) {
                $summary = (string) ($faculty['department_page'][$locale]['summary'] ?? $summary);
            }

            if ($slug === 'training') {
                $summary = $locale === 'ar'
                    ? 'استكشف مسار التدريب العملي الذي يربط طلبة الصيدلة بخبرة مهنية منظمة ضمن بيئات تدريبية معتمدة.'
                    : 'Explore the practical training pathway that connects pharmacy students with supervised professional experience in approved teaching environments.';
            }

            if (in_array($slug, ['study-plan', 'study-plan-course'], true)) {
                $meta = $this->studyPlanRouteMeta($faculty, $slug);
                $summary = $locale === 'en'
                    ? (string) ($meta['description'] ?? $this->subpageSummary($slug, $faculty[$locale]['name'], $locale))
                    : $this->subpageSummary($slug, $faculty[$locale]['name'], $locale);
            }

            DB::table('faculty_page_translations')->updateOrInsert(
                ['faculty_page_id' => $pageId, 'locale' => $locale],
                [
                    'title' => $slug === 'overview' ? $faculty[$locale]['catalog_title'] : ($locale === 'en' ? ($meta['title'] ?? $labels[$locale]) : $labels[$locale]),
                    'summary' => $summary,
                    'body' => $slug === 'overview' ? $faculty[$locale]['description'] : null,
                    'sections_json' => json_encode($slug === 'overview' ? $faculty[$locale]['sections'] : [], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedDepartments(int $facultyId, array $faculty): void
    {
        DB::table('departments')
            ->where('faculty_id', $facultyId)
            ->whereNotIn('slug', collect($faculty['departments'])->pluck('slug')->all())
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ($faculty['departments'] as $index => $department) {
            $now = now();
            DB::table('departments')->updateOrInsert(
                ['faculty_id' => $facultyId, 'slug' => $department['slug']],
                ['sort_order' => $index + 1, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $departmentId = (int) DB::table('departments')->where('faculty_id', $facultyId)->where('slug', $department['slug'])->value('id');

            foreach (['ar', 'en'] as $locale) {
                DB::table('department_translations')->updateOrInsert(
                    ['department_id' => $departmentId, 'locale' => $locale],
                    ['name' => $department[$locale]['name'], 'description' => $department[$locale]['summary'], 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedHighlights(int $facultyId, array $faculty): void
    {
        DB::table('faculty_highlights')
            ->where('faculty_id', $facultyId)
            ->whereNotIn('key', collect($faculty['highlights'])->pluck('key')->all())
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ($faculty['highlights'] as $index => $highlight) {
            $now = now();
            DB::table('faculty_highlights')->updateOrInsert(
                ['faculty_id' => $facultyId, 'key' => $highlight['key']],
                [
                    'value' => $highlight['value'],
                    'icon' => $highlight['icon'],
                    'url' => $highlight['url'],
                    'sort_order' => $index + 1,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $highlightId = (int) DB::table('faculty_highlights')->where('faculty_id', $facultyId)->where('key', $highlight['key'])->value('id');

            foreach (['ar', 'en'] as $locale) {
                DB::table('faculty_highlight_translations')->updateOrInsert(
                    ['faculty_highlight_id' => $highlightId, 'locale' => $locale],
                    ['title' => $highlight[$locale], 'summary' => null, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedLabs(int $facultyId, array $faculty): void
    {
        DB::table('faculty_labs')
            ->where('faculty_id', $facultyId)
            ->whereNotIn('slug', collect($faculty['labs'])->pluck('slug')->all())
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ($faculty['labs'] as $index => $lab) {
            $now = now();
            DB::table('faculty_labs')->updateOrInsert(
                ['faculty_id' => $facultyId, 'slug' => $lab['slug']],
                ['image' => $lab['image'], 'sort_order' => $index + 1, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $labId = (int) DB::table('faculty_labs')->where('faculty_id', $facultyId)->where('slug', $lab['slug'])->value('id');

            foreach (['ar', 'en'] as $locale) {
                DB::table('faculty_lab_translations')->updateOrInsert(
                    ['faculty_lab_id' => $labId, 'locale' => $locale],
                    [
                        'title' => $lab[$locale]['title'],
                        'department' => $lab[$locale]['department'],
                        'instructor' => $lab[$locale]['instructor'],
                        'description' => $lab[$locale]['summary'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedProjects(int $facultyId, array $faculty): void
    {
        $templates = $this->researchProjects($faculty);

        DB::table('faculty_student_projects')
            ->where('faculty_id', $facultyId)
            ->whereNotIn('slug', collect($templates)->pluck('slug')->all())
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        foreach ($templates as $index => $project) {
            $now = now();
            DB::table('faculty_student_projects')->updateOrInsert(
                ['faculty_id' => $facultyId, 'slug' => $project['slug']],
                ['image' => $project['image'], 'sort_order' => $index + 1, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $projectId = (int) DB::table('faculty_student_projects')->where('faculty_id', $facultyId)->where('slug', $project['slug'])->value('id');

            foreach (['ar', 'en'] as $locale) {
                DB::table('faculty_student_project_translations')->updateOrInsert(
                    ['faculty_student_project_id' => $projectId, 'locale' => $locale],
                    [
                        'title' => $project[$locale]['title'],
                        'summary' => $project[$locale]['summary'],
                        'tag' => $project[$locale]['tag'],
                        'team' => $project[$locale]['team'],
                        'supervisor' => $project[$locale]['supervisor'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    /** @param array<string, mixed> $faculty @return array<int, array<string, mixed>> */
    private function researchProjects(array $faculty): array
    {
        $templates = [
            ['titleEn' => 'AI Diagnosis Support for Rural Health Centers', 'titleAr' => 'دعم التشخيص الذكي للمراكز الصحية الريفية', 'summaryEn' => 'A mobile-first platform providing primary diagnosis tools using computer vision.', 'summaryAr' => 'منصة مهيأة للهاتف توفر أدوات تشخيص أولية باستخدام الرؤية الحاسوبية.', 'tagEn' => 'Health Tech', 'tagAr' => 'تقنيات صحية'],
            ['titleEn' => 'AI Diagnosis Support for Rural Health Centers', 'titleAr' => 'دعم التشخيص الذكي للمراكز الصحية الريفية', 'summaryEn' => 'A mobile-first platform providing primary diagnosis tools using computer vision.', 'summaryAr' => 'منصة مهيأة للهاتف توفر أدوات تشخيص أولية باستخدام الرؤية الحاسوبية.', 'tagEn' => 'Software Systems', 'tagAr' => 'أنظمة برمجية'],
            ['titleEn' => 'Predictive Analytics for Local Economic Trends', 'titleAr' => 'تحليلات تنبؤية للاتجاهات الاقتصادية المحلية', 'summaryEn' => 'Analyzing market data to provide actionable insights for Syrian SMEs.', 'summaryAr' => 'تحليل بيانات السوق لتقديم مؤشرات قابلة للتطبيق للمشاريع المحلية.', 'tagEn' => 'Software Systems', 'tagAr' => 'أنظمة برمجية'],
            ['titleEn' => 'Clinical Appointment Flow Optimizer', 'titleAr' => 'تحسين تدفق المواعيد السريرية', 'summaryEn' => 'A scheduling prototype that reduces clinic wait time through demand forecasting.', 'summaryAr' => 'نموذج جدولة يقلل وقت الانتظار في العيادات عبر التنبؤ بالطلب.', 'tagEn' => 'Health Tech', 'tagAr' => 'تقنيات صحية'],
            ['titleEn' => 'Smart Campus Services Dashboard', 'titleAr' => 'لوحة خدمات جامعية ذكية', 'summaryEn' => 'A service dashboard that tracks requests, response times, and student support patterns.', 'summaryAr' => 'لوحة خدمات تتابع الطلبات وأوقات الاستجابة وأنماط دعم الطلاب.', 'tagEn' => 'Software Systems', 'tagAr' => 'أنظمة برمجية'],
            ['titleEn' => 'Evidence-Based Learning Repository', 'titleAr' => 'مستودع تعلم قائم على الدليل', 'summaryEn' => 'A searchable media archive for supervised projects, case notes, and learning resources.', 'summaryAr' => 'أرشيف وسائط قابل للبحث للمشاريع المشرفة وملاحظات الحالات وموارد التعلم.', 'tagEn' => 'Research', 'tagAr' => 'بحث علمي'],
        ];

        return collect(range(0, 11))->map(function (int $index) use ($faculty, $templates): array {
            $template = $templates[$index % count($templates)];
            $number = $index + 1;

            return [
                'slug' => $faculty['public_slug'].'-project-'.$number,
                'image' => '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp',
                'en' => ['title' => $template['titleEn'], 'summary' => $template['summaryEn'], 'tag' => $template['tagEn'], 'team' => 'Student team & student name, '.$faculty['en']['name'], 'supervisor' => $index % 2 === 0 ? 'Prof. Mays Hassan' : 'Dr. Ahmad Nassar'],
                'ar' => ['title' => $template['titleAr'], 'summary' => $template['summaryAr'], 'tag' => $template['tagAr'], 'team' => 'فريق طلابي واسم الطالب، '.$faculty['ar']['name'], 'supervisor' => $index % 2 === 0 ? 'أ. ميس حسن' : 'د. أحمد نصار'],
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function pharmacyTrainingPayload(): array
    {
        return [
            'breadcrumb' => [
                'homeEn' => 'Home', 'homeAr' => 'الرئيسية',
                'facilitiesEn' => 'Facilities', 'facilitiesAr' => 'الكليات',
                'pharmacyEn' => 'Pharmacy', 'pharmacyAr' => 'الصيدلة',
            ],
            'hero' => [
                'eyebrowEn' => 'Faculty of Pharmacy', 'eyebrowAr' => 'كلية الصيدلة',
                'titleEn' => 'Training & Apprenticeship', 'titleAr' => 'التدريب والتلمذة المهنية',
                'summaryEn' => 'Explore the practical training pathway that connects pharmacy students with supervised professional experience in approved teaching environments.',
                'summaryAr' => 'استكشف مسار التدريب العملي الذي يربط طلبة الصيدلة بخبرة مهنية منظمة ضمن بيئات تدريبية معتمدة.',
                'image' => '/images/pharmacy-place.jpg',
            ],
            'introCards' => [
                ['id' => 'applied-learning', 'icon' => '/images/icons/training.svg', 'titleEn' => 'Applied Learning', 'titleAr' => 'تعلم تطبيقي', 'descriptionEn' => 'Apply classroom pharmacology and clinical preparation through structured practice supervised by experienced professionals.', 'descriptionAr' => 'تطبيق المعرفة الدوائية والتحضير السريري من خلال تدريب منظم بإشراف مختصين.'],
                ['id' => 'expert-supervision', 'icon' => '/images/icons/users.svg', 'titleEn' => 'Expert Supervision', 'titleAr' => 'إشراف متخصص', 'descriptionEn' => 'Work directly with pharmacy preceptors who guide daily learning, evaluation, and professional expectations.', 'descriptionAr' => 'العمل مع مشرفين مهنيين يوجهون التعلم اليومي والتقييم ومتطلبات المهنة.'],
                ['id' => 'career-readiness', 'icon' => '/images/icons/award.svg', 'titleEn' => 'Career Readiness', 'titleAr' => 'جاهزية مهنية', 'descriptionEn' => 'Develop essential communication, patient-service, and workflow skills required for pharmacy practice.', 'descriptionAr' => 'تنمية مهارات التواصل وخدمة المرضى وسير العمل اللازمة لممارسة الصيدلة.'],
            ],
            'programme' => [
                'titleEn' => 'Programme Structure', 'titleAr' => 'هيكل البرنامج',
                'steps' => [
                    ['number' => '1', 'titleEn' => 'Orientation', 'titleAr' => 'التهيئة', 'descriptionEn' => 'Program introduction and training safety review for students before placement.', 'descriptionAr' => 'تعريف بالبرنامج ومراجعة إجراءات السلامة قبل بدء التدريب.'],
                    ['number' => '2', 'titleEn' => 'Supervised Training', 'titleAr' => 'تدريب بإشراف', 'descriptionEn' => 'Direct hands-on experience in approved practice settings with structured logs.', 'descriptionAr' => 'خبرة عملية مباشرة في مواقع معتمدة مع سجلات تدريب منظمة.'],
                    ['number' => '3', 'titleEn' => 'Partner Placement', 'titleAr' => 'توزيع على الشركاء', 'descriptionEn' => 'Students rotate through community and hospital pharmacies for pharmacy-care exposure.', 'descriptionAr' => 'دورات تدريبية في الصيدليات المجتمعية والمشافي لاكتساب خبرة مهنية.'],
                    ['number' => '4', 'titleEn' => 'Certification', 'titleAr' => 'اعتماد التدريب', 'descriptionEn' => 'Final assessment, performance review, and official training certification.', 'descriptionAr' => 'تقييم نهائي ومراجعة أداء ومنح وثيقة اعتماد التدريب.'],
                ],
            ],
            'partners' => [
                'titleEn' => 'Partner Pharmacies & Hospitals', 'titleAr' => 'الصيدليات والمشافي الشريكة',
                'ctaEn' => 'Full Directory', 'ctaAr' => 'الدليل الكامل', 'ctaUrl' => '/facilities/?id=pharmacy',
                'items' => [
                    ['id' => 'approved-location-1', 'titleEn' => 'Approved Training Location', 'titleAr' => 'موقع تدريب معتمد', 'categoryEn' => 'Pharmacy', 'categoryAr' => 'صيدلية', 'descriptionEn' => 'Clinical practice and pharmaceutical care.', 'descriptionAr' => 'تدريب سريري ورعاية صيدلانية.', 'image' => '/images/pharmacy-place.jpg', 'href' => '/facilities/pharmacy/labs/'],
                    ['id' => 'approved-location-2', 'titleEn' => 'Approved Training Location', 'titleAr' => 'موقع تدريب معتمد', 'categoryEn' => 'Pharmacy', 'categoryAr' => 'صيدلية', 'descriptionEn' => 'Inventory management and patient counseling.', 'descriptionAr' => 'إدارة المخزون والإرشاد الدوائي للمرضى.', 'image' => '/images/news/pharmacy.jpg', 'href' => '/facilities/pharmacy/labs/'],
                    ['id' => 'spu-hospital-1', 'titleEn' => 'SPU University Hospital', 'titleAr' => 'مشفى الجامعة السورية الخاصة', 'categoryEn' => 'Hospital', 'categoryAr' => 'مشفى', 'descriptionEn' => 'Specialized clinical pharmacy training.', 'descriptionAr' => 'تدريب صيدلة سريرية تخصصية.', 'image' => '/images/campus-hospital.webp', 'href' => '/campus-life/hospital/'],
                    ['id' => 'approved-location-3', 'titleEn' => 'Approved Training Location', 'titleAr' => 'موقع تدريب معتمد', 'categoryEn' => 'Pharmacy', 'categoryAr' => 'صيدلية', 'descriptionEn' => 'Clinical practice and pharmaceutical care.', 'descriptionAr' => 'تدريب سريري ورعاية صيدلانية.', 'image' => '/images/pharmacy-place.jpg', 'href' => '/facilities/pharmacy/labs/'],
                    ['id' => 'approved-location-4', 'titleEn' => 'Approved Training Location', 'titleAr' => 'موقع تدريب معتمد', 'categoryEn' => 'Pharmacy', 'categoryAr' => 'صيدلية', 'descriptionEn' => 'Inventory management and patient counseling.', 'descriptionAr' => 'إدارة المخزون والإرشاد الدوائي للمرضى.', 'image' => '/images/news/pharmacy.jpg', 'href' => '/facilities/pharmacy/labs/'],
                    ['id' => 'spu-hospital-2', 'titleEn' => 'SPU University Hospital', 'titleAr' => 'مشفى الجامعة السورية الخاصة', 'categoryEn' => 'Hospital', 'categoryAr' => 'مشفى', 'descriptionEn' => 'Specialized clinical pharmacy training.', 'descriptionAr' => 'تدريب صيدلة سريرية تخصصية.', 'image' => '/images/campus-hospital.webp', 'href' => '/campus-life/hospital/'],
                ],
            ],
            'facts' => [
                ['id' => 'duration', 'valueEn' => '12-16 Weeks', 'valueAr' => '12-16 أسبوعا', 'labelEn' => 'Total track', 'labelAr' => 'مدة المسار'],
                ['id' => 'hours', 'valueEn' => '200 Hours', 'valueAr' => '200 ساعة', 'labelEn' => 'Clinical requirement', 'labelAr' => 'متطلب تدريبي'],
                ['id' => 'intakes', 'valueEn' => 'Spring / Fall Year', 'valueAr' => 'الربيع / الخريف', 'labelEn' => 'Available intake', 'labelAr' => 'فترات متاحة'],
                ['id' => 'reports', 'valueEn' => 'Weekly progress reports', 'valueAr' => 'تقارير تقدم أسبوعية', 'labelEn' => 'Assessment', 'labelAr' => 'التقييم'],
            ],
        ];
    }

    /** @param array<string, mixed> $faculty @return array<string, mixed> */
    private function studyPlanPayload(array $faculty, string $slug): array
    {
        $source = $this->studyPlanFrontendData();
        $facultyKey = $this->studyPlanFacultyKey((string) $faculty['public_slug']);
        $plan = $source['studyPlansContent']['faculties'][$facultyKey] ?? null;

        if (! is_array($plan)) {
            return [];
        }

        return [
            'kind' => $slug,
            'labels' => $source['studyPlansContent']['labels'] ?? [],
            'legend' => $source['studyPlansContent']['legend'] ?? [],
            'courseLabels' => $source['courseLessonsContent']['labels'] ?? [],
            'lessonTypes' => $source['courseLessonsContent']['lessonTypes'] ?? [],
            'routeMeta' => $this->studyPlanRouteMeta($faculty, $slug),
            'plan' => $plan,
        ];
    }

    /** @return array<string, mixed> */
    private function studyPlanFrontendData(): array
    {
        $json = file_get_contents(database_path('seeders/Data/study_plan_frontend_data.json'));

        if ($json === false) {
            return [];
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /** @param array<string, mixed> $faculty @return array<string, mixed> */
    private function studyPlanRouteMeta(array $faculty, string $slug): array
    {
        $source = $this->studyPlanFrontendData();
        $route = '/facilities/'.$faculty['public_slug'].'/study-plan/'.($slug === 'study-plan-course' ? 'course/' : '');
        $meta = collect($source['routeMeta'] ?? [])->firstWhere('route', $route);

        return is_array($meta) ? $meta : [];
    }

    private function studyPlanFacultyKey(string $publicSlug): string
    {
        return match ($publicSlug) {
            'artificial-intelligence' => 'ai-engineering',
            'business-administration' => 'business',
            'building-construction-engineering' => 'Construction',
            default => $publicSlug,
        };
    }

    /** @param array<string, mixed> $faculty */
    private function seedAlumni(int $facultyId, array $faculty): void
    {
        foreach ([2023, 2022] as $index => $year) {
            $now = now();
            $identifier = $faculty['public_slug'].'-alumni-'.$year;
            DB::table('alumni')->updateOrInsert(
                ['student_identifier' => $identifier],
                ['faculty_id' => $facultyId, 'degree' => 'Bachelor', 'graduation_year' => $year, 'is_featured' => $index === 0, 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $alumniId = (int) DB::table('alumni')->where('student_identifier', $identifier)->value('id');
            DB::table('alumni_translations')->updateOrInsert(['alumni_id' => $alumniId, 'locale' => 'en'], ['full_name' => $index === 0 ? 'Rama Haddad' : 'Yazan Darwish', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('alumni_translations')->updateOrInsert(['alumni_id' => $alumniId, 'locale' => 'ar'], ['full_name' => $index === 0 ? 'راما حداد' : 'يزن درويش', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** @param array<string, mixed> $faculty */
    private function seedHonorStudents(int $facultyId, array $faculty): void
    {
        $placeholderIdentifiers = collect([1, 2, 3])
            ->map(fn (int $index): string => $faculty['public_slug'].'-honor-'.$index)
            ->all();

        DB::table('honor_students')
            ->where('faculty_id', $facultyId)
            ->whereIn('student_identifier', $placeholderIdentifiers)
            ->update(['is_enabled' => false, 'updated_at' => now()]);
    }

    /** @return array<int, array<string, mixed>> */
    private function faculties(): array
    {
        $json = file_get_contents(database_path('seeders/Data/faculty_frontend_data.json'));

        if ($json === false) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $source */
        $source = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $order = array_flip(['medicine', 'dentistry', 'pharmacy', 'business', 'petroleum', 'ai-engineering', 'Construction']);

        usort($source, fn (array $first, array $second): int => ($order[$first['id']] ?? 99) <=> ($order[$second['id']] ?? 99));

        return array_map(fn (array $faculty): array => $this->normalizeFrontendFaculty($faculty), $source);
    }

    /** @param array<string, mixed> $faculty @return array<string, mixed> */
    private function normalizeFrontendFaculty(array $faculty): array
    {
        $departmentPage = is_array($faculty['departmentPage'] ?? null) ? $this->departmentPage($faculty['departmentPage']) : null;

        return [
            'slug' => (string) $faculty['internalSlug'],
            'public_slug' => (string) $faculty['publicSlug'],
            'accent' => (string) $faculty['color'],
            'hero_image' => (string) $faculty['heroImage'],
            'subpage_image' => (string) ($departmentPage['heroImage'] ?? $faculty['heroImage']),
            'logo' => $faculty['logo'],
            'gallery' => array_values(array_filter($faculty['gallery'] ?? [])),
            'en' => [
                'name' => (string) $faculty['nameEn'],
                'catalog_title' => (string) $faculty['catalogTitleEn'],
                'summary' => (string) $faculty['summaryEn'],
                'description' => (string) $faculty['descriptionEn'],
                'years' => (string) $faculty['yearsEn'],
                'sections' => $this->sections($faculty['tabs'] ?? [], 'en'),
            ],
            'ar' => [
                'name' => (string) $faculty['nameAr'],
                'catalog_title' => (string) $faculty['catalogTitleAr'],
                'summary' => (string) $faculty['summaryAr'],
                'description' => (string) $faculty['descriptionAr'],
                'years' => (string) $faculty['yearsAr'],
                'sections' => $this->sections($faculty['tabs'] ?? [], 'ar'),
            ],
            'stats' => $faculty['stats'] ?? [],
            'dean' => $faculty['dean'] ?? [],
            'departments' => $this->normalizedDepartments($faculty, $departmentPage),
            'department_page' => $departmentPage,
            'highlights' => $this->normalizedHighlights($faculty['highlights'] ?? []),
            'labs' => $this->normalizedLabs($faculty['labs'] ?? []),
        ];
    }

    /** @param array<int, array<string, mixed>> $tabs @return array<int, array{title: string, body: string}> */
    private function sections(array $tabs, string $locale): array
    {
        return collect($tabs)
            ->reject(fn (array $tab): bool => ($tab['id'] ?? null) === 'overview')
            ->map(fn (array $tab): array => [
                'title' => (string) ($tab['label'.ucfirst($locale)] ?? ''),
                'body' => (string) ($tab['content'.ucfirst($locale)] ?? ''),
            ])
            ->filter(fn (array $section): bool => $section['body'] !== '')
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $page @return array<string, mixed> */
    private function departmentPage(array $page): array
    {
        return [
            'heroImage' => (string) ($page['heroImage'] ?? '/images/uni-main-place.JPG'),
            'accent' => (string) ($page['accent'] ?? '#202759'),
            'stats' => $page['stats'] ?? [],
            'departments' => $page['departments'] ?? [],
            'en' => ['faculty' => (string) ($page['facultyEn'] ?? ''), 'summary' => (string) ($page['summaryEn'] ?? '')],
            'ar' => ['faculty' => (string) ($page['facultyAr'] ?? ''), 'summary' => (string) ($page['summaryAr'] ?? '')],
        ];
    }

    /** @param array<string, mixed> $faculty @param array<string, mixed>|null $departmentPage @return array<int, array<string, mixed>> */
    private function normalizedDepartments(array $faculty, ?array $departmentPage): array
    {
        if (is_array($departmentPage) && $departmentPage['departments'] !== []) {
            return collect($departmentPage['departments'])->map(fn (array $department): array => [
                'slug' => (string) $department['slug'],
                'en' => ['name' => (string) $department['nameEn'], 'summary' => (string) $department['summaryEn']],
                'ar' => ['name' => (string) $department['nameAr'], 'summary' => (string) $department['summaryAr']],
            ])->values()->all();
        }

        $fallback = match ($faculty['publicSlug']) {
            'dentistry' => ['Restorative Dentistry', 'Prosthodontics', 'Oral Surgery', 'Periodontology'],
            default => [],
        };

        return $this->departments($fallback);
    }

    /** @param array<int, array<string, mixed>> $highlights @return array<int, array<string, mixed>> */
    private function normalizedHighlights(array $highlights): array
    {
        return collect($highlights)->map(fn (array $highlight): array => [
            'key' => (string) $highlight['key'],
            'value' => (string) $highlight['value'],
            'icon' => $highlight['icon'],
            'url' => $highlight['url'],
            'en' => (string) $highlight['titleEn'],
            'ar' => (string) $highlight['titleAr'],
        ])->values()->all();
    }

    /** @param array<int, array<string, mixed>> $labs @return array<int, array<string, mixed>> */
    private function normalizedLabs(array $labs): array
    {
        return collect($labs)->map(fn (array $lab): array => [
            'slug' => (string) $lab['slug'],
            'image' => (string) $lab['image'],
            'en' => ['title' => (string) $lab['titleEn'], 'department' => (string) $lab['departmentEn'], 'instructor' => (string) $lab['instructorEn'], 'summary' => (string) $lab['summaryEn']],
            'ar' => ['title' => (string) $lab['titleAr'], 'department' => (string) $lab['departmentAr'], 'instructor' => (string) $lab['instructorAr'], 'summary' => (string) $lab['summaryAr']],
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function departments(array $departments): array
    {
        return array_map(fn (string $name): array => [
            'slug' => str($name)->slug()->toString(),
            'en' => ['name' => 'Department of '.$name, 'summary' => 'Academic department supporting specialized learning, applied practice, and student progression.'],
            'ar' => ['name' => 'قسم '.$this->arabicLabel($name), 'summary' => 'قسم أكاديمي يدعم التعلم التخصصي والممارسة التطبيقية وتقدم الطلاب.'],
        ], $departments);
    }

    /** @return array{ar: string, en: string} */
    private function subpageLabels(string $slug): array
    {
        return match ($slug) {
            'overview' => ['ar' => 'لمحة عامة', 'en' => 'Overview'],
            'departments' => ['ar' => 'الأقسام الأكاديمية', 'en' => 'Academic Departments'],
            'labs' => ['ar' => 'المخابر', 'en' => 'Laboratories'],
            'projects' => ['ar' => 'مشاريع الطلاب', 'en' => 'Student Projects'],
            'alumni' => ['ar' => 'الخريجون', 'en' => 'Alumni'],
            'valedictorians' => ['ar' => 'قائمة الشرف', 'en' => 'Honor List'],
            'training' => ['ar' => 'التدريب والتلمذة المهنية', 'en' => 'Training & Apprenticeship'],
            'research' => ['ar' => 'أحدث الأبحاث', 'en' => 'Latest Research'],
            'study-plan' => ['ar' => 'الخطة الدراسية', 'en' => 'Study Plan'],
            'study-plan-course' => ['ar' => 'محاضرات المقرر', 'en' => 'Course Lessons'],
            default => ['ar' => $slug, 'en' => $slug],
        };
    }

    private function subpageSummary(string $slug, string $facultyName, string $locale): string
    {
        if ($locale === 'ar') {
            return match ($slug) {
                'departments' => 'استكشف الأقسام الأكاديمية في '.$facultyName.'.',
                'labs' => 'استكشف بيئات التدريب العملي والمخابر في '.$facultyName.'.',
                'projects' => 'اطلع على مشاريع الطلاب التطبيقية في '.$facultyName.'.',
                'alumni' => 'استعرض سجلات خريجي '.$facultyName.'.',
                'valedictorians' => 'قائمة الشرف والأوائل في '.$facultyName.'.',
                'training' => 'مسار التدريب العملي لطلبة '.$facultyName.'.',
                'research' => 'استكشف أحدث المنشورات والأبحاث العلمية في '.$facultyName.'.',
                'study-plan' => 'الخطة الدراسية وتسلسل المقررات في '.$facultyName.'.',
                'study-plan-course' => 'محاضرات ومواد المقررات في '.$facultyName.'.',
                default => $facultyName,
            };
        }

        return match ($slug) {
            'departments' => 'Explore academic departments in '.$facultyName.'.',
            'labs' => 'Explore laboratories and practical training environments in '.$facultyName.'.',
            'projects' => 'Browse applied student projects in '.$facultyName.'.',
            'alumni' => 'Browse alumni records for '.$facultyName.'.',
            'valedictorians' => 'Honor list and valedictorians for '.$facultyName.'.',
            'training' => 'Practical training pathway for '.$facultyName.' students.',
            'research' => 'Explore the latest scholarly publications and research from '.$facultyName.'.',
            'study-plan' => 'Study plan and course sequence for '.$facultyName.'.',
            'study-plan-course' => 'Course lessons and materials for '.$facultyName.'.',
            default => $facultyName,
        };
    }

    private function arabicLabel(string $label): string
    {
        return match ($label) {
            'Credit Hours' => 'ساعة معتمدة',
            'Departments' => 'أقسام',
            'Training Hospitals' => 'مشافي تدريبية',
            'Training Clinics' => 'عيادات تدريبية',
            'Dental Chairs' => 'كراسي طبية',
            'Specialized Labs' => 'مخابر تخصصية',
            'Training' => 'تدريب',
            'Software Labs' => 'مخابر برمجية',
            'AI Projects' => 'مشاريع ذكاء اصطناعي',
            'Engineering Labs' => 'مخابر هندسية',
            'Training Hours' => 'ساعات تدريبية',
            'Energy Labs' => 'مخابر طاقة',
            'Field Training' => 'تدريب ميداني',
            'Study Years' => 'سنوات الدراسة',
            'Internal Medicine' => 'الأمراض الباطنة',
            'General Surgery' => 'الجراحة العامة',
            'Pediatrics' => 'طب الأطفال',
            'Anatomy and Histology' => 'التشريح والنسج',
            'Restorative Dentistry' => 'المعالجة والترميم',
            'Prosthodontics' => 'التعويضات السنية',
            'Oral Surgery' => 'جراحة الفم',
            'Periodontology' => 'أمراض اللثة',
            'Pharmaceutics' => 'الصيدلانيات',
            'Pharmaceutical Chemistry' => 'الكيمياء الصيدلية',
            'Clinical Pharmacy' => 'الصيدلة السريرية',
            'Pharmacology and Toxicology' => 'علم الأدوية والسموم',
            'Computer Science' => 'علوم الحاسوب',
            'Artificial Intelligence' => 'الذكاء الاصطناعي',
            'Information Systems' => 'نظم المعلومات',
            'Software Engineering' => 'هندسة البرمجيات',
            'Structural Engineering' => 'الهندسة الإنشائية',
            'Construction Management' => 'إدارة التشييد',
            'Building Materials' => 'مواد البناء',
            'Surveying and Infrastructure' => 'المساحة والبنى التحتية',
            'Drilling Engineering' => 'هندسة الحفر',
            'Reservoir Engineering' => 'هندسة المكامن',
            'Petroleum Production' => 'إنتاج النفط والغاز',
            'Petroleum Geology' => 'الجيولوجيا البترولية',
            'Management' => 'الإدارة',
            'Accounting' => 'المحاسبة',
            'Finance and Banking' => 'التمويل والمصارف',
            'Marketing' => 'التسويق',
            default => $label,
        };
    }
}
