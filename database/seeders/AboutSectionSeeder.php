<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Models\Content\Directorate;
use App\Models\Content\Partnership;
use App\Models\Page\AboutPage;
use App\Models\Person\Person;
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
                'images' => ['primary' => '/images/about-hero-1.webp', 'secondary' => '/images/about-hero-2.jpg', 'overview' => '/images/about/hero-img.jpg'],
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
                    ['value' => '2005', 'label_ar' => 'سنة التأسيس', 'label_en' => 'Year Founded', 'icon' => '/images/icon-history-outline.svg'],
                    ['value' => '339', 'label_ar' => 'مرسوم الإحداث', 'label_en' => 'Establishing Decree', 'icon' => '/images/icon-sitemap-outline.svg'],
                    ['value' => '7', 'label_ar' => 'كليات أكاديمية', 'label_en' => 'Academic Faculties', 'icon' => '/images/icon-university-outline.svg'],
                ],
                'story_items' => [
                    ['title_ar' => 'رؤيتنا', 'title_en' => 'Our Vision', 'summary_ar' => 'أن نكون جامعة رائدة تنهض بالتعليم الحديث وتستجيب للاحتياجات المتطورة للمجتمع وسوق العمل.', 'summary_en' => 'To be a leading university that advances modern education and responds to the evolving needs of society and the job market.'],
                    ['title_ar' => 'رسالتنا', 'title_en' => 'Our Mission', 'summary_ar' => 'تقديم برامج أكاديمية حديثة عالية الجودة تزود الطلاب بالمعرفة والمهارات والقيم اللازمة للنجاح المهني والابتكار والتأثير المجتمعي.', 'summary_en' => 'To provide high-quality, modern academic programs that equip students with the knowledge, skills, and values needed for professional success, innovation, and community impact.'],
                ],
                'highlights' => [
                    ['title_ar' => 'برامج مرخصة', 'title_en' => 'Licensed Programs', 'summary_ar' => 'برامج وأنظمة معتمدة من وزارة التعليم العالي والبحث العلمي.', 'summary_en' => 'Programs and regulations approved by the Ministry of Higher Education and Scientific Research.'],
                    ['title_ar' => 'تعليم تطبيقي', 'title_en' => 'Applied Learning', 'summary_ar' => 'تجربة تعليمية تربط المعرفة الأكاديمية بالمهارات المهنية.', 'summary_en' => 'Learning that connects academic knowledge with professional skills.'],
                ],
                'sub_pages' => [
                    ['title_ar' => 'تاريخ الجامعة', 'title_en' => 'History', 'link' => '/about/history'],
                    ['title_ar' => 'مجلس الجامعة', 'title_en' => 'Leadership', 'link' => '/about/leadership'],
                    ['title_ar' => 'المديريات', 'title_en' => 'Directorates', 'link' => '/about/directorates'],
                    ['title_ar' => 'الشراكات', 'title_en' => 'Partnerships', 'link' => '/about/partnerships'],
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
            'vision-mission' => [
                'ar' => ['الرؤية والرسالة', 'الرؤية والرسالة', 'تدعم الجامعة السورية الخاصة التعليم العالي من خلال تعليم نوعي وتعاون علمي وثقافي وبرامج تستجيب لاحتياجات المجتمع.', [
                    'cardsTitle' => 'توجه الجامعة',
                    'cards' => [
                        ['icon' => '/images/icon-search-outline.svg', 'title' => 'الرؤية', 'body' => 'أن تكون الجامعة مركزاً علمياً متميزاً محلياً وإقليمياً وعالمياً، يواكب تطور المعرفة ويستجيب لاحتياجات المجتمع.'],
                        ['icon' => '/images/icon-award-outline.svg', 'title' => 'الرسالة', 'body' => 'تقديم تعليم جامعي معتمد وعالي الجودة من خلال مناهج وبرامج تدريبية وكفاءات أكاديمية متخصصة في المجالات الطبية والهندسية والإدارية.'],
                        ['icon' => '/images/icon-handshake-outline.svg', 'title' => 'القيم', 'body' => 'الجودة، والمسؤولية، والانفتاح العلمي، وخدمة المجتمع، والنزاهة الأكاديمية في التعليم والبحث والعمل المؤسسي.'],
                    ],
                    'pillarsTitle' => 'الأعمدة الاستراتيجية',
                    'pillars' => [
                        ['title' => 'تعليم معتمد', 'summary' => 'تعمل الجامعة وفق أنظمة ومناهج معتمدة من وزارة التعليم العالي والبحث العلمي.'],
                        ['title' => 'تعاون علمي', 'summary' => 'تدعم الاتفاقيات الأكاديمية تبادل الخبرات ورفع جودة التعليم وتسهيل فرص الدراسات العليا.'],
                        ['title' => 'خدمة المجتمع', 'summary' => 'ترتبط البرامج والأنشطة البحثية باحتياجات التنمية الاقتصادية والاجتماعية.'],
                        ['title' => 'خريجون مؤهلون', 'summary' => 'تركز التجربة التعليمية على تأهيل الخريجين للمنافسة والتميز في سوق العمل.'],
                    ],
                ]],
                'en' => ['Vision and Mission', 'Vision and Mission', 'SPU advances higher education through quality learning, scientific and cultural cooperation, and programs responsive to community needs.', [
                    'cardsTitle' => 'Our Direction',
                    'cards' => [
                        ['icon' => '/images/icon-search-outline.svg', 'title' => 'Vision', 'body' => 'To be a distinguished scientific center locally, regionally, and globally, keeping pace with knowledge development while responding to community needs.'],
                        ['icon' => '/images/icon-award-outline.svg', 'title' => 'Mission', 'body' => 'To provide accredited, high-quality university education through curricula, training programs, and specialized academic expertise across medical, engineering, and administrative disciplines.'],
                        ['icon' => '/images/icon-handshake-outline.svg', 'title' => 'Values', 'body' => 'Quality, responsibility, scientific openness, community service, and academic integrity in learning, research, and institutional work.'],
                    ],
                    'pillarsTitle' => 'Strategic Pillars',
                    'pillars' => [
                        ['title' => 'Accredited Education', 'summary' => 'SPU operates through regulations and curricula approved by the Ministry of Higher Education and Scientific Research.'],
                        ['title' => 'Scientific Cooperation', 'summary' => 'Academic agreements support experience exchange, educational quality, and postgraduate opportunities.'],
                        ['title' => 'Community Relevance', 'summary' => 'Programs and research activities are aligned with economic and social development needs.'],
                        ['title' => 'Qualified Graduates', 'summary' => 'The learning experience prepares graduates to compete and stand out in the labor market.'],
                    ],
                ]],
            ],
            'history' => [
                'ar' => ['التاريخ والتأسيس', 'التاريخ والتأسيس', 'جامعة تأسست لتعزيز التميز الأكاديمي والإعداد المهني وخدمة المجتمع.', [
                    ['eyebrow' => 'رؤية التأسيس', 'title' => 'بداية المشروع الأكاديمي', 'body' => 'تأسست الجامعة السورية الخاصة انطلاقا من التزام بتطوير التعليم العالي ورفع معايير التجربة الأكاديمية في سوريا.'],
                    ['eyebrow' => 'المسار المؤسسي', 'title' => 'محطات رئيسية', 'items' => ['2005: تأسيس الجامعة السورية الخاصة', '2009: تخريج أول دفعة', '2012: اعتماد اسم الجامعة السورية الخاصة']],
                ]],
                'en' => ['History & Founding', 'History & Founding', 'A university founded to advance academic excellence, professional preparation, and service to society.', [
                    ['eyebrow' => 'Founding Vision', 'title' => 'The beginning of the academic project', 'body' => 'Syrian Private University was established through a commitment to developing higher education and strengthening academic experience in Syria.'],
                    ['eyebrow' => 'Institutional Timeline', 'title' => 'Key milestones', 'items' => ['2005: Founding of Syrian Private University', '2009: First graduates', '2012: Syrian Private University name adopted']],
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
                'hero_image' => in_array($slug, ['vision-mission', 'history'], true) ? '/images/about/hero-img.jpg' : '/images/about-hero-2.webp',
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
        Person::query()->whereIn('slug', [
            'academic-affairs',
            'administrative-affairs',
            'medicine-dean',
            'dentistry-dean',
            'pharmacy-dean',
            'business-dean',
            'ai-dean',
        ])->update(['is_enabled' => false]);

        $people = [
            ['rector', 'rector', null, '/images/about/leadership/presedent.jpg', 'rector@spu.edu.sy', 'الأستاذ الدكتور عبد الرزاق الحسين', 'Prof. Dr. Abdul Razzaq Al-Hussein', 'رئيس الجامعة', 'University Rector', 'يقود الجامعة السورية الخاصة ويعمل على تعزيز جودة التعليم والبحث العلمي وخدمة المجتمع.', 'Leads Syrian Private University and advances educational quality, research, and community service.', 'تتمثل رؤيتنا في بناء بيئة أكاديمية تسعى إلى التميز في البحث والتعليم وتسهم بفاعلية في التنمية المستدامة للمجتمع.', 'Our vision is to foster an academic environment that pursues excellence in research and education while contributing to sustainable development.'],
            ['arwa-khair', 'vice_president', null, '/images/about/leadership/pre-presendent.jpg', 'arwa.khair@spu.edu.sy', 'د. أروى خير', 'Dr. Arwa Khair', 'نائب رئيس الجامعة للشؤون العلمية', 'Vice President for Academic Affairs', 'تشرف على تطوير المناهج والمعايير الأكاديمية وتميز الهيئة التدريسية في كليات الجامعة.', 'Oversees curriculum development, academic standards, and faculty excellence across the university.', null, null],
            ['mohammad-riad-alghazzi', 'vice_president', null, '/images/about/leadership/uni-pre.jpg', 'mohammad.alghazzi@spu.edu.sy', 'د. محمد رياض الغزي', 'Dr. Mohammad Riad Alghazzi', 'أمين الجامعة', 'University Secretary', 'يشرف على العمليات الإدارية والشؤون القانونية والحوكمة المؤسسية في الجامعة.', 'Oversees administrative operations, legal affairs, and institutional governance.', null, null],
            ['ayman-ali', 'dean', 'medicine', '/images/about/leadership/medicine-dean.jpg', 'ayman.ali@spu.edu.sy', 'د. أيمن علي', 'Dr. Ayman Ali', 'عميد كلية الطب', 'Dean of Medicine', 'يقود البرامج الأكاديمية والتدريب السريري في كلية الطب.', 'Leads academic programs and clinical training in the Faculty of Medicine.', null, null],
            ['talaat-abu-hatab', 'dean', 'dentistry', '/images/about/leadership/dental-dean.jpg', 'talaat.abuhatab@spu.edu.sy', 'د. طلعت أبو حطب', 'Dr. Talaat Abu Hatab', 'عميد كلية طب الأسنان', 'Dean of Dentistry', 'يشرف على البرامج الأكاديمية والتدريب العملي في كلية طب الأسنان.', 'Oversees academic programs and practical training in the Faculty of Dentistry.', null, null],
            ['hossam-shahrour', 'dean', 'pharmacy', '/images/about/leadership/pharmacy-dean.jpg', 'hossam.shahrour@spu.edu.sy', 'د. حسام شحرور', 'Dr. Hossam Shahrour', 'عميد كلية الصيدلة', 'Dean of Pharmacy', 'يقود التعليم والبحث العلمي في العلوم الصيدلانية.', 'Leads education and research in pharmaceutical sciences.', null, null],
            ['mouhib-alnoukari', 'dean', 'artificial-intelligence', '/images/about/leadership/Ai-dean.jpg', 'mouhib.alnoukari@spu.edu.sy', 'د. مهيب النقري', 'Dr. Mouhib Alnoukari', 'عميد كلية هندسة الذكاء الاصطناعي', 'Dean of Artificial Intelligence Engineering', 'يقود برامج الذكاء الاصطناعي وعلم البيانات وتطوير المهارات التقنية الحديثة.', 'Leads artificial intelligence, data science, and modern technical-skills programs.', null, null],
            ['mahmoud-hadid', 'dean', 'petroleum', '/images/about/leadership/petrol-dean.jpg', 'mahmoud.hadid@spu.edu.sy', 'د. محمود حديد', 'Dr. Mahmoud Hadid', 'عميد كلية هندسة البترول', 'Dean of Petroleum Engineering', 'يقود البرامج الأكاديمية والتعاون المهني في هندسة البترول.', 'Leads academic programs and professional cooperation in petroleum engineering.', null, null],
            ['samar-habib', 'dean', 'business-administration', '/images/about/leadership/busnins-dean.jpg', 'samar.habib@spu.edu.sy', 'د. سمر حبيب', 'Dr. Samar Habib', 'عميد كلية إدارة الأعمال', 'Dean of Business Administration', 'تقود تطوير القدرات الإدارية والقيادية وإعداد الطلاب لسوق العمل.', 'Leads the development of management and leadership capabilities for the labor market.', null, null],
            ['ammar-ghada', 'dean', 'building-construction-engineering', null, 'ammar.ghada@spu.edu.sy', 'د. عمار غضة', 'Dr. Ammar Ghada', 'عميد كلية هندسة التشييد والبناء', 'Dean of Building and Construction Engineering', 'يقود تطوير البرامج الهندسية لإعداد خريجين مؤهلين لمشاريع الإعمار والتنمية.', 'Leads engineering programs that prepare graduates for reconstruction and development projects.', null, null],
        ];

        foreach ($people as $index => $row) {
            $person = Person::query()->updateOrCreate(['slug' => $row[0]], [
                'category' => $row[1],
                'faculty_scope_slug' => $row[2],
                'image' => $row[3],
                'email' => null,
                'sort_order' => $index + 1,
                'is_enabled' => true,
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now(),
            ]);
            $person->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row[5], 'role' => $row[7], 'bio' => $row[9], 'quote' => $row[11]]);
            $person->translations()->updateOrCreate(['locale' => 'en'], ['name' => $row[6], 'role' => $row[8], 'bio' => $row[10], 'quote' => $row[12]]);
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
                'email' => null,
                'location' => null,
                'sort_order' => $index + 1,
                'is_enabled' => true,
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now(),
            ]);
            $directorate->translations()->updateOrCreate(['locale' => 'ar'], ['title' => $row[2], 'summary' => $row[4], 'description' => $row[4], 'services_json' => $row[6]]);
            $directorate->translations()->updateOrCreate(['locale' => 'en'], ['title' => $row[3], 'summary' => $row[5], 'description' => $row[5], 'services_json' => $row[7]]);
        }
    }

    private function seedPartnerships(): void
    {
        $items = [
            ['association-of-arab-universities', 'academic', '/images/arab-uni.png', 'https://www.aaru.edu.jo/member-universities/syria/', 'اتحاد الجامعات العربية', 'Association of Arab Universities', 'عضوية أكاديمية', 'Academic Membership', '', '', 'تظهر الجامعة السورية الخاصة ضمن الجامعات السورية الأعضاء في اتحاد الجامعات العربية.', 'Syrian Private University appears among Syrian member universities listed by the Association of Arab Universities.', true],
            ['cooperating-universities', 'academic', '/images/icon-university-outline.svg', 'https://www.spu.edu.sy', 'الجامعات المتعاونة', 'Cooperating Universities', 'اتفاقيات تعاون', 'Cooperation Agreements', '', '', 'سجل تجميعي يحتاج إلى توثيق الاتفاقيات الفردية قبل النشر.', 'Aggregate record pending verification of individual agreements.', false],
            ['coursera', 'academic', '/images/corsera.png', 'https://www.coursera.org', 'كورسيرا', 'Coursera', 'تعلم رقمي', 'Digital Learning', '', '', 'سجل غير موثق وغير منشور.', 'Unverified unpublished record.', false],
            ['world-health-organization', 'clinical', '/images/world-health.png', 'https://www.who.int', 'منظمة الصحة العالمية', 'World Health Organization', 'صحة عامة', 'Public Health', '', '', 'سجل غير موثق وغير منشور.', 'Unverified unpublished record.', false],
            ['github-education-microsoft', 'research', '/images/logo-spu.png', 'https://education.github.com', 'تعليم GitHub ومايكروسوفت', 'GitHub Education and Microsoft', 'التكنولوجيا والذكاء الاصطناعي', 'Technology & AI', '', '', 'سجل غير موثق وغير منشور.', 'Unverified unpublished record.', false],
        ];

        foreach ($items as $index => $row) {
            $partnership = Partnership::query()->updateOrCreate(['slug' => $row[0]], [
                'category_key' => $row[1],
                'status_key' => 'active',
                'logo' => $row[2],
                'website_url' => $row[3],
                'sort_order' => $index + 1,
                'is_enabled' => $row[12],
                'publication_status' => PublicationStatus::Published->value,
                'published_at' => now(),
            ]);
            $partnership->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $row[4], 'category' => $row[6], 'status' => 'نشط', 'established_label' => $row[8], 'description' => $row[10]]);
            $partnership->translations()->updateOrCreate(['locale' => 'en'], ['name' => $row[5], 'category' => $row[7], 'status' => 'Active', 'established_label' => $row[9], 'description' => $row[11]]);
        }
    }
}
