<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AboutPage;
use App\Models\Directorate;
use App\Models\Partnership;
use App\Models\Person;
use Illuminate\Database\Seeder;

class AboutSectionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAboutPages();
        $this->seedPeople();
        $this->seedDirectorates();
        $this->seedPartnerships();
    }

    private function seedAboutPages(): void
    {
        $landing = AboutPage::query()->updateOrCreate(['slug' => 'about'], [
            'template' => 'landing',
            'hero_image' => '/images/about-hero-1.webp',
            'payload_json' => [
                'images' => ['primary' => '/images/about-hero-1.webp', 'secondary' => '/images/about-hero-2.webp'],
                'ar' => [
                    'badge' => 'نبذة عن SPU',
                    'quote' => 'نربط التعليم الأكاديمي باحتياجات المجتمع وسوق العمل من خلال برامج معتمدة وتجربة جامعية تطبيقية.',
                    'description' => 'أُحدثت الجامعة السورية الخاصة بالمرسوم الجمهوري رقم 339 لعام 2005، وتعمل ضمن أنظمة ومناهج معتمدة من وزارة التعليم العالي والبحث العلمي في الجمهورية العربية السورية.',
                ],
                'en' => [
                    'badge' => 'ABOUT SPU',
                    'quote' => 'SPU connects academic learning with community and labor-market needs through accredited programs and applied campus experience.',
                    'description' => 'Syrian Private University was established by Republican Decree No. 339 in 2005 and operates with regulations and curricula approved by the Syrian Ministry of Higher Education and Scientific Research.',
                ],
                'stats' => [
                    ['value' => '2005', 'label_ar' => 'سنة التأسيس', 'label_en' => 'Established', 'icon' => '/images/icons/history.svg'],
                    ['value' => '7', 'label_ar' => 'الكليات', 'label_en' => 'Faculties', 'icon' => '/images/icon-university-outline.svg'],
                    ['value' => '2009', 'label_ar' => 'أول دفعة خريجين', 'label_en' => 'First graduates', 'icon' => '/images/icon-user-graduate-outline.svg'],
                    ['value' => '2012', 'label_ar' => 'اعتماد اسم الجامعة', 'label_en' => 'SPU name adopted', 'icon' => '/images/icon-award-outline.svg'],
                ],
                'story_items' => [
                    ['title_ar' => 'رؤيتنا', 'title_en' => 'Our Vision', 'summary_ar' => 'أن نكون جامعة رائدة تنهض بالتعليم الحديث وتستجيب للاحتياجات المتطورة للمجتمع وسوق العمل.', 'summary_en' => 'To be a leading university that advances modern education and responds to the evolving needs of society and the job market.'],
                    ['title_ar' => 'رسالتنا', 'title_en' => 'Our Mission', 'summary_ar' => 'تقديم برامج أكاديمية حديثة عالية الجودة تزود الطلاب بالمعرفة والمهارات والقيم اللازمة للنجاح المهني والابتكار والتأثير المجتمعي.', 'summary_en' => 'To provide high-quality, modern academic programs that equip students with the knowledge, skills, and values needed for professional success, innovation, and community impact.'],
                ],
                'highlights' => [
                    ['title_ar' => 'بيئة تعلم تطبيقية', 'title_en' => 'Applied Learning Environment'],
                    ['title_ar' => 'تجربة طلابية ثنائية اللغة', 'title_en' => 'Bilingual Student Experience'],
                ],
                'sub_pages' => [
                    ['title_ar' => 'تاريخ الجامعة', 'title_en' => 'History', 'link' => '/about/history'],
                    ['title_ar' => 'مجلس الجامعة', 'title_en' => 'Leadership', 'link' => '/about/leadership'],
                    ['title_ar' => 'المديريات', 'title_en' => 'Directorates', 'link' => '/about/directorates'],
                    ['title_ar' => 'الشراكات', 'title_en' => 'Partnerships', 'link' => '/about/partnerships'],
                    ['title_ar' => 'الرؤية والرسالة', 'title_en' => 'Vision & Mission', 'link' => '/about/vision-mission'],
                ],
            ],
            'status' => 'published',
            'published_at' => now(),
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $this->upsertPageTranslation($landing, 'ar', 'عن الجامعة', 'مشروع علمي وثقافي وتنموي يدعم التعليم العالي في سوريا.', 'تعرف إلى نشأة الجامعة السورية الخاصة ورسالتها ودورها الأكاديمي والمجتمعي.');
        $this->upsertPageTranslation($landing, 'en', 'About SPU', 'A scientific, cultural, and developmental project supporting higher education in Syria.', 'Learn about Syrian Private University, its founding story, mission, and academic role.');

        $pages = [
            'history' => [
                'ar' => ['التاريخ والتأسيس', 'التاريخ والتأسيس', 'جامعة تأسست لتعزيز التميز الأكاديمي والإعداد المهني وخدمة المجتمع.', [
                    ['eyebrow' => 'رؤية التأسيس', 'title' => 'بداية المشروع الأكاديمي', 'body' => 'تأسست الجامعة السورية الخاصة انطلاقا من التزام بتطوير التعليم العالي ورفع معايير التجربة الأكاديمية في سوريا.'],
                    ['eyebrow' => 'المسار المؤسسي', 'title' => 'محطات رئيسية', 'items' => ['2005: تأسيس الجامعة السورية الخاصة', '2009: تخريج أول دفعة', '2012: اعتماد اسم الجامعة السورية الخاصة', '2026: تعزيز التحول الرقمي للموقع والخدمات.']],
                ]],
                'en' => ['History & Founding', 'History & Founding', 'A university founded to advance academic excellence, professional preparation, and service to society.', [
                    ['eyebrow' => 'Founding Vision', 'title' => 'The beginning of the academic project', 'body' => 'Syrian Private University was established through a commitment to developing higher education and strengthening academic experience in Syria.'],
                    ['eyebrow' => 'Institutional Timeline', 'title' => 'Key milestones', 'items' => ['2005: Founding of Syrian Private University', '2009: First graduates', '2012: Syrian Private University name adopted', '2026: Digital transformation of the website and services.']],
                ]],
            ],
            'vision-mission' => [
                'ar' => ['الرؤية والرسالة', 'الرؤية والرسالة', 'تعكس الرؤية والرسالة اتجاه الجامعة في التعليم والبحث وخدمة المجتمع.', [
                    ['title' => 'الرؤية', 'body' => 'أن تكون الجامعة مركزا علميا متميزا محليا وإقليميا وعالميا يواكب تطور المعرفة ويستجيب لاحتياجات المجتمع.'],
                    ['title' => 'الرسالة', 'body' => 'تقديم تعليم جامعي معتمد وعالي الجودة من خلال مناهج وبرامج تدريبية وكفاءات أكاديمية متخصصة.'],
                    ['title' => 'القيم', 'body' => 'الجودة، المسؤولية، الانفتاح العلمي، خدمة المجتمع، والنزاهة الأكاديمية.'],
                ]],
                'en' => ['Vision & Mission', 'Vision and Mission', 'SPU’s vision and mission guide education, research, and community service.', [
                    ['title' => 'Vision', 'body' => 'To be a distinguished scientific center locally, regionally, and globally while responding to community needs.'],
                    ['title' => 'Mission', 'body' => 'To provide accredited, high-quality university education through modern curricula, training programs, and specialized academic expertise.'],
                    ['title' => 'Values', 'body' => 'Quality, responsibility, scientific openness, community service, and academic integrity.'],
                ]],
            ],
            'leadership' => [
                'ar' => ['مجلس الجامعة', 'دليل قيادة الجامعة السورية الخاصة', 'تعرف إلى رئاسة الجامعة والعمداء والقيادات الأكاديمية.'],
                'en' => ['Leadership', 'SPU leadership directory', 'Meet the university rector, deans, and academic leadership.'],
            ],
            'directorates' => [
                'ar' => ['المديريات', 'مديريات متخصصة تضمن التميز التشغيلي ونجاح الطلاب.', 'تدعم المديريات المركزية العملية التعليمية والبحثية والإدارية في الجامعة.'],
                'en' => ['Directorates', 'Specialized directorates ensuring operational excellence and student success.', 'Central directorates support academic, research, and administrative operations.'],
            ],
            'partnerships' => [
                'ar' => ['الشراكات', 'شراكات توسع أثر الجامعة', 'تهدف اتفاقيات التعاون العلمي إلى تبادل الخبرات وتحسين جودة التعليم ودعم فرص الدراسات العليا.'],
                'en' => ['Partnerships', 'Partnerships that extend SPU’s impact', 'Scientific cooperation agreements exchange expertise, improve quality, and support postgraduate opportunities.'],
            ],
        ];

        foreach ($pages as $slug => $translations) {
            $page = AboutPage::query()->updateOrCreate(['slug' => $slug], [
                'template' => $slug,
                'hero_image' => '/images/about-hero-2.webp',
                'status' => 'published',
                'published_at' => now(),
                'is_enabled' => true,
                'sort_order' => 10,
            ]);

            foreach ($translations as $locale => $data) {
                $this->upsertPageTranslation($page, $locale, $data[0], $data[1], $data[2], $data[3] ?? []);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $sections */
    private function upsertPageTranslation(AboutPage $page, string $locale, string $title, string $headline, string $summary, array $sections = []): void
    {
        $page->translations()->updateOrCreate(['locale' => $locale], [
            'title' => $title,
            'headline' => $headline,
            'summary' => $summary,
            'sections_json' => $sections,
        ]);
    }

    private function seedPeople(): void
    {
        $people = [
            ['rector', 'rector', null, '/images/medicine-dean.jpg', 'رئيس الجامعة السورية الخاصة', 'SPU Rector', 'رئيس الجامعة', 'University Rector'],
            ['academic-affairs', 'vice_president', null, '/images/medicine-dean.jpg', 'نائب رئيس الجامعة للشؤون العلمية', 'Vice President for Academic Affairs', 'الشؤون العلمية', 'Academic Affairs'],
            ['administrative-affairs', 'vice_president', null, '/images/ai-dean.jpeg', 'نائب رئيس الجامعة للشؤون الإدارية', 'Vice President for Administrative Affairs', 'الشؤون الإدارية', 'Administrative Affairs'],
            ['medicine-dean', 'dean', 'medicine', '/images/medicine-dean.jpg', 'عميد كلية الطب', 'Dean of Medicine', 'كلية الطب', 'Faculty of Medicine'],
            ['dentistry-dean', 'dean', 'dentistry', '/images/dental-dean.jpg', 'عميد كلية طب الأسنان', 'Dean of Dentistry', 'كلية طب الأسنان', 'Faculty of Dentistry'],
            ['pharmacy-dean', 'dean', 'pharmacy', '/images/pharmacy-dean.jpg', 'عميد كلية الصيدلة', 'Dean of Pharmacy', 'كلية الصيدلة', 'Faculty of Pharmacy'],
            ['business-dean', 'dean', 'business', '/images/business-dean.jpg', 'عميد كلية إدارة الأعمال', 'Dean of Business Administration', 'كلية إدارة الأعمال', 'Faculty of Business Administration'],
            ['ai-dean', 'dean', 'ai', '/images/ai-dean.jpeg', 'عميد كلية هندسة الذكاء الاصطناعي', 'Dean of Artificial Intelligence Engineering', 'كلية هندسة الذكاء الاصطناعي', 'Faculty of Artificial Intelligence Engineering'],
        ];

        foreach ($people as $index => $row) {
            $person = Person::query()->updateOrCreate(['slug' => $row[0]], [
                'category' => $row[1],
                'faculty_scope_slug' => $row[2],
                'image' => $row[3],
                'sort_order' => $index + 1,
                'is_enabled' => true,
            ]);
            $person->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row[4], 'role' => $row[6], 'bio' => 'يقود هذا الدور العمل الأكاديمي والمؤسسي ضمن منظومة الجامعة السورية الخاصة.']);
            $person->translations()->updateOrCreate(['locale' => 'en'], ['name' => $row[5], 'role' => $row[7], 'bio' => 'This role supports academic and institutional work within Syrian Private University.']);
        }
    }

    private function seedDirectorates(): void
    {
        $items = [
            ['scientific-research', '/images/icons/research.svg', 'مديرية البحث العلمي', 'Scientific Research Directorate', 'إدارة مبادرات البحث ودعم النشر والشراكات الأكاديمية.', 'Managing research initiatives, publication support, and academic partnerships.', ['إدارة منح البحث العلمي', 'دعم النشر العلمي', 'الإشراف على لجنة الأخلاقيات'], ['Research grant management', 'Publication support', 'Ethics committee oversight']],
            ['student-affairs', '/images/icon-user-graduate-outline.svg', 'مديرية شؤون الطلاب', 'Student Affairs Directorate', 'الإشراف على سجلات الطلاب والأنشطة وتجربة الحياة الجامعية.', 'Overseeing student records, activities, and campus life experience.', ['التسجيل والقبول', 'الأنشطة الطلابية', 'الإرشاد الوظيفي'], ['Enrollment and registration', 'Student activities', 'Career counseling']],
            ['it-services', '/images/icons/software.svg', 'مديرية تقانة المعلومات', 'Information Technology Directorate', 'صيانة البنية التحتية الرقمية وخدمات البوابة والاتصال داخل الحرم.', 'Maintaining digital infrastructure, portal services, and campus connectivity.', ['شبكة الحرم الجامعي', 'بوابات الطلاب والموظفين', 'دعم التعلم الإلكتروني'], ['Campus network', 'Student and staff portals', 'E-learning support']],
            ['public-relations', '/images/icon-envelope-outline.svg', 'مديرية العلاقات العامة', 'Public Relations Directorate', 'إدارة اتصالات الجامعة والفعاليات والتواجد الإعلامي.', 'Managing university communications, events, and media presence.', ['العلاقات الإعلامية', 'إدارة الفعاليات', 'التواصل المجتمعي'], ['Media relations', 'Event management', 'Community outreach']],
        ];

        foreach ($items as $index => $row) {
            $directorate = Directorate::query()->updateOrCreate(['slug' => $row[0]], [
                'icon' => $row[1],
                'email' => 'info@spu.edu.sy',
                'location' => 'Main Building',
                'sort_order' => $index + 1,
                'is_enabled' => true,
            ]);
            $directorate->translations()->updateOrCreate(['locale' => 'ar'], ['title' => $row[2], 'summary' => $row[4], 'description' => $row[4], 'services_json' => $row[6]]);
            $directorate->translations()->updateOrCreate(['locale' => 'en'], ['title' => $row[3], 'summary' => $row[5], 'description' => $row[5], 'services_json' => $row[7]]);
        }
    }

    private function seedPartnerships(): void
    {
        $items = [
            ['association-of-arab-universities', '/images/arab-uni.png', 'https://www.aaru.edu.jo/member-universities/syria/', 'اتحاد الجامعات العربية', 'Association of Arab Universities', 'عضوية أكاديمية', 'Academic Membership'],
            ['cooperating-universities', '/images/icon-university-outline.svg', 'https://www.spu.edu.sy', 'الجامعات المتعاونة', 'Cooperating Universities', 'اتفاقيات تعاون', 'Cooperation Agreements'],
            ['coursera', '/images/corsera.png', 'https://www.coursera.org', 'منصة كورسيرا العالمية', 'Coursera', 'تعلم رقمي', 'Digital Learning'],
            ['world-health-organization', '/images/world-health.png', 'https://www.who.int', 'منظمة الصحة العالمية', 'World Health Organization', 'صحة عامة', 'Public Health'],
        ];

        foreach ($items as $index => $row) {
            $partnership = Partnership::query()->updateOrCreate(['slug' => $row[0]], [
                'logo' => $row[1],
                'website_url' => $row[2],
                'sort_order' => $index + 1,
                'is_enabled' => true,
            ]);
            $partnership->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row[3], 'category' => $row[5], 'status' => 'نشط', 'established_label' => 'مستمر', 'description' => 'شراكة تدعم التعاون العلمي والأكاديمي وتبادل الخبرات.']);
            $partnership->translations()->updateOrCreate(['locale' => 'en'], ['name' => $row[4], 'category' => $row[6], 'status' => 'Active', 'established_label' => 'Ongoing', 'description' => 'A partnership that supports scientific and academic cooperation and expertise exchange.']);
        }
    }
}
