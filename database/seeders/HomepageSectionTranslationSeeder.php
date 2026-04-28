<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\HomepageSectionServiceInterface;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use Illuminate\Database\Seeder;

/**
 * Seeds structured homepage content sourced from the frontend repo's
 * home-content.js, faculties-catalog.js, and layout-content.js.
 */
class HomepageSectionTranslationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $key) {
            $section = HomepageSection::query()->where('key', $key)->firstOrFail();

            foreach (['ar', 'en'] as $locale) {
                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $section->getKey(),
                        'locale' => $locale,
                    ],
                    [
                        'payload_json' => $this->payloadFor($key, $locale),
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $key, string $locale): array
    {
        return match ($key) {
            'hero' => $this->hero($locale),
            'hero_stats' => $this->heroStats($locale),
            'academic_faculties' => $this->academicFaculties($locale),
            'achievements_highlights' => $this->achievements($locale),
            'university_news' => $this->news($locale),
            'research_studies' => $this->research($locale),
            'events_activities' => $this->events($locale),
            'medical_facilities_services' => $this->medical($locale),
            'bottom_stats' => $this->bottomStats($locale),
            'footer' => $this->footer($locale),
            default => [],
        };
    }

    // ──────────────────────────────────────────────
    //  Hero – from heroContent in home-content.js
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function hero(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'eyebrow' => 'التميز الأكاديمي من قلب دمشق',
                'title' => 'الجامعة السورية الخاصة',
                'subtitle' => 'بيئة أكاديمية حديثة تربط الدراسة المعتمدة، والتدريب السريري، والبحث العلمي، وفرص الطلبة في دمشق.',
                'primaryAction' => $this->action('استكشف الكليات', '/ar/faculties'),
                'secondaryAction' => $this->action('زيارة الحرم', '/ar/contact'),
                'content' => [
                    'images' => [
                        '/images/slider-1.webp',
                        '/images/slider-2.webp',
                        '/images/slider-3.webp',
                        '/images/slider-4.webp',
                    ],
                ],
            ];
        }

        return [
            'eyebrow' => 'Academic excellence from the heart of Damascus',
            'title' => 'Syrian Private University',
            'subtitle' => 'A modern academic environment connecting accredited study, clinical training, research, and student opportunity in Damascus.',
            'primaryAction' => $this->action('Explore Faculties', '/en/faculties'),
            'secondaryAction' => $this->action('Visit Campus', '/en/contact'),
            'content' => [
                'images' => [
                    '/images/slider-1.webp',
                    '/images/slider-2.webp',
                    '/images/slider-3.webp',
                    '/images/slider-4.webp',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Hero Stats – from statsItems in home-content.js
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function heroStats(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'أرقام مؤسسية' : 'Institutional Facts',
            'stats' => [
                $this->stat(
                    '20',
                    $locale === 'ar' ? 'عاماً منذ التأسيس' : 'Years Since Founding',
                    suffix: '+',
                    icon: '/images/icons/years.svg',
                    helperText: $locale === 'ar'
                        ? 'مسار مؤسسي متواصل في تطوير التعليم العالي والتخصصات الحديثة.'
                        : 'A sustained institutional journey in higher education and modern academic development.',
                    sortOrder: 1,
                ),
                $this->stat(
                    '6500',
                    $locale === 'ar' ? 'الابحاث العلمية المنشورة' : 'Published Scientific Research',
                    suffix: '+',
                    icon: '/images/icons/file.svg',
                    helperText: $locale === 'ar'
                        ? 'عدد الأبحاث العلمية المنشورة من قبل أعضاء الكلية.'
                        : 'Number of scientific research papers published by faculty members.',
                    sortOrder: 2,
                ),
                $this->stat(
                    '8500',
                    $locale === 'ar' ? 'طالب وطالبة' : 'Enrolled Students',
                    suffix: '+',
                    icon: '/images/student.svg',
                    helperText: $locale === 'ar'
                        ? 'مجتمع طلابي نشط عبر البرامج الجامعية والتدريبية داخل الحرم الجامعي.'
                        : 'An active student body across university programs and applied training on campus.',
                    sortOrder: 3,
                ),
                $this->stat(
                    '450',
                    $locale === 'ar' ? 'خريجون وخريجات' : 'Graduates',
                    suffix: '+',
                    icon: '/images/icons/users.svg',
                    helperText: $locale === 'ar'
                        ? 'خريجون انتقلوا إلى الممارسة المهنية والدراسات المتقدمة في مجالاتهم.'
                        : 'Graduates progressing into professional practice and advanced study across their fields.',
                    sortOrder: 4,
                ),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Academic Faculties – from facultiesCatalog
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function academicFaculties(string $locale): array
    {
        $l = $locale; // shorthand

        return [
            'title' => $l === 'ar' ? 'كلياتنا الجامعية' : 'Our Faculties',
            'sectionAction' => $this->action(
                $l === 'ar' ? 'عرض الكل' : 'View All',
                '/'.$l.'/faculties',
            ),
            'items' => [
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية الطب البشري' : 'Faculty of Medicine',
                    imageUrl: '/images/faculty-medicine-logo.png',
                    accent: '#bc2428',
                    metric: $l === 'ar' ? '6 سنوات' : '6 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية طب الأسنان' : 'Faculty of Dentistry',
                    imageUrl: '/images/faculty-dentistry-logo.png',
                    accent: '#1f77b4',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية الصيدلة' : 'Faculty of Pharmacy',
                    imageUrl: '/images/faculty-pharmacy-logo.png',
                    accent: '#5ebe7b',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية هندسة الذكاء الاصطناعي' : 'Faculty of AI Engineering',
                    imageUrl: '/images/faculty-ai-engineering-logo.png',
                    accent: '#683695',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية هندسة التشييد والبناء' : 'Construction Engineering',
                    imageUrl: '/images/faculty-construction-engineering-logo.png',
                    accent: '#7f8c8d',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية هندسة البترول' : 'Petroleum Engineering',
                    imageUrl: '/images/faculty-petroleum-engineering-logo.png',
                    accent: '#0b5759',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
                $this->facultyItem(
                    title: $l === 'ar' ? 'كلية إدارة الأعمال' : 'Business Administration',
                    imageUrl: '/images/faculty-business-logo.png',
                    accent: '#caa949',
                    metric: $l === 'ar' ? '5 سنوات' : '5 Years',
                    actionLabel: $l === 'ar' ? 'اعرف المزيد' : 'LEARN MORE',
                    actionUrl: '/'.$l.'/faculties',
                ),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Achievements Highlights (Honor Panel)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function achievements(string $locale): array
    {
        $l = $locale;

        return [
            'title' => $l === 'ar' ? 'التكريم والتميز' : 'Honor & Excellence',
            'eyebrow' => $l === 'ar' ? 'سجل التميز' : 'Honor Board',
            'items' => [
                [
                    'title' => $l === 'ar'
                        ? 'تميّز المحاكاة السريرية ضمن عرض أكاديمي أكثر رسمية وأناقة.'
                        : 'Clinical simulation excellence presented through a more formal academic showcase.',
                    'typeTag' => $l === 'ar' ? 'المحور المكرّم' : 'Honor Spotlight',
                    'summary' => $l === 'ar'
                        ? 'يمكن إبراز كلية الطب هنا عبر صور ميدانية وملاحظات تكريم مختصرة وصياغة تحريرية أقوى تليق بواجهة جامعة رسمية.'
                        : 'The Faculty of Medicine can be highlighted here with field photography, concise recognition notes, and a stronger editorial presentation that feels worthy of an official university homepage.',
                    'image' => '/images/dsc-1060.webp',
                    'meta' => $l === 'ar' ? 'كلية الطب' : 'Faculty of Medicine',
                    'action' => $this->action(
                        $l === 'ar' ? 'اكتشف التفاصيل' : 'View Details',
                        '/'.$l.'/faculties',
                    ),
                ],
                [
                    'title' => $l === 'ar'
                        ? 'يمكن لقصص البحث التطبيقي أن تجمع بين الصورة القوية والسياق المؤسسي المختصر.'
                        : 'Applied research stories can combine strong imagery with concise institutional context.',
                    'typeTag' => $l === 'ar' ? 'تميّز بحثي' : 'Research Distinction',
                    'summary' => $l === 'ar'
                        ? 'يدعم هذا النمط المختبرات والمشاريع الطلابية وجوائز الابتكار وأي إعلان بحثي قائم على الصور من دون أن يبدو القسم عادياً أو مكرراً.'
                        : 'This card format supports laboratories, student projects, innovation awards, and any photo-led research announcement without making the section feel generic.',
                    'image' => '/images/dsc-1075.webp',
                    'meta' => $l === 'ar' ? 'الذكاء الاصطناعي والهندسة التطبيقية' : 'Applied AI and Engineering',
                    'action' => $this->action(
                        $l === 'ar' ? 'اكتشف التفاصيل' : 'View Details',
                        '/'.$l.'/research',
                    ),
                ],
                [
                    'title' => $l === 'ar'
                        ? 'يمكن أن تنتمي إنجازات المجتمع والحياة الجامعية إلى النظام البصري المميز نفسه.'
                        : 'Community and student-life highlights can sit in the same premium visual system.',
                    'typeTag' => $l === 'ar' ? 'قيادة مجتمعية' : 'Community Leadership',
                    'summary' => $l === 'ar'
                        ? 'يحافظ تخطيط البطاقة الأصغر على أناقة عناصر التكريم واحترافيتها مع ترك مساحة لصور الحرم والملخصات القصيرة والمسار المباشر إلى الصفحة ذات الصلة.'
                        : 'The smaller card layout keeps recognition items neat and professional while still leaving space for campus photography, short summaries, and a direct path to the related page.',
                    'image' => '/images/slider-3.webp',
                    'meta' => $l === 'ar' ? 'شؤون الطلاب والتواصل المجتمعي' : 'Student Affairs and Outreach',
                    'action' => $this->action(
                        $l === 'ar' ? 'اكتشف التفاصيل' : 'View Details',
                        '/'.$l.'/student-life',
                    ),
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  University News – from newsItems
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function news(string $locale): array
    {
        $l = $locale;

        return [
            'title' => $l === 'ar' ? 'أخبار الجامعة' : 'SPU News',
            'sectionAction' => $this->action(
                $l === 'ar' ? 'عرض الكل' : 'View All',
                '/'.$l.'/news',
            ),
            'articles' => [
                [
                    'id' => 1,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'توسيع المساحات الخضراء في الجامعة' : 'Expansion of University Green Spaces',
                    'slug' => 'expansion-of-university-green-spaces',
                    'excerpt' => $l === 'ar'
                        ? 'مبادرات جديدة لتنسيق الحدائق تحول الحرم الجامعي إلى بيئة أكثر خضرة.'
                        : 'New landscaping initiatives are transforming the campus into a greener environment.',
                    'imageUrl' => '/images/unsplash_s9CC2SKySJM.webp',
                    'publishedAt' => '2026-03-15',
                    'categoryLabel' => 'Campus',
                    'url' => '/'.$l.'/news',
                ],
                [
                    'id' => 2,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'فتح باب القبول المبكر الآن' : 'Early Admission cycle now open',
                    'slug' => 'early-admission-cycle-now-open',
                    'excerpt' => $l === 'ar'
                        ? 'يمكن لطلاب المدارس الثانوية الآن التقديم للفصل الدراسي الخريف القادم.'
                        : 'High school students can now apply for the upcoming fall semester.',
                    'imageUrl' => '/images/unsplash_VckdJzo7ig0.webp',
                    'publishedAt' => '2026-03-10',
                    'categoryLabel' => 'Admission',
                    'url' => '/'.$l.'/news',
                ],
                [
                    'id' => 3,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'المهرجان الثقافي السنوي' : 'Annual Cultural Festival',
                    'slug' => 'annual-cultural-festival',
                    'excerpt' => $l === 'ar'
                        ? 'عروض يقدمها الطلاب تعرض الفنون والتراث الإقليمي.'
                        : 'Student-led performances showcasing regional arts and heritage.',
                    'imageUrl' => '/images/slider-3.webp',
                    'publishedAt' => '2026-03-05',
                    'categoryLabel' => 'Events',
                    'url' => '/'.$l.'/news',
                ],
                [
                    'id' => 4,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'شراكة التواصل المجتمعي' : 'Community Outreach Partnership',
                    'slug' => 'community-outreach-partnership',
                    'excerpt' => $l === 'ar'
                        ? 'التعاون مع الشركاء الإقليميين لدعم النمو الاجتماعي المحلي.'
                        : 'Collaborating with regional partners to support local social growth.',
                    'imageUrl' => '/images/slider-4.webp',
                    'publishedAt' => '2026-03-01',
                    'categoryLabel' => 'Community',
                    'url' => '/'.$l.'/news',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Research Studies – from researchItems
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function research(string $locale): array
    {
        $l = $locale;

        return [
            'title' => $l === 'ar' ? 'أبحاث ودراسات' : 'SPU Research',
            'sectionAction' => $this->action(
                $l === 'ar' ? 'عرض الكل' : 'View All',
                '/'.$l.'/research',
            ),
            'researchItems' => [
                [
                    'id' => 1,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'أبحاث المحاكاة السريرية والصحة الوقائية' : 'Clinical Simulation and Preventive Health Research',
                    'slug' => 'clinical-simulation-preventive-health',
                    'summary' => $l === 'ar'
                        ? 'تسلط أعمال الكلية الضوء على دراسات التشخيص المبكر وصحة المجتمع.'
                        : 'Faculty-led work highlights early diagnosis and community health studies.',
                    'imageUrl' => '/images/frame-114.webp',
                    'categoryLabel' => 'Medicine',
                    'url' => '/'.$l.'/research',
                ],
                [
                    'id' => 2,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'دراسات طب الأسنان الرقمي وتجديد الأنسجة الفموية' : 'Digital Dentistry and Oral Regeneration Studies',
                    'slug' => 'digital-dentistry-oral-regeneration',
                    'summary' => $l === 'ar'
                        ? 'تشمل محاور البحث تقنيات الترميم والتخطيط القائم على الأدلة.'
                        : 'Research themes include restorative techniques and evidence-based planning.',
                    'imageUrl' => '/images/unsplash_s9CC2SKySJM.webp',
                    'categoryLabel' => 'Dentistry',
                    'url' => '/'.$l.'/research',
                ],
                [
                    'id' => 3,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'صياغة الأدوية، مراقبة الجودة، والعلاجات' : 'Drug Formulation, Quality Control, and Therapeutics',
                    'slug' => 'drug-formulation-quality-control',
                    'summary' => $l === 'ar'
                        ? 'تركز المشاريع على دراسات الاستخدام الآمن والفعال للأدوية.'
                        : 'Projects focus on safe and effective medication use studies.',
                    'imageUrl' => '/images/unsplash_VckdJzo7ig0.webp',
                    'categoryLabel' => 'Pharmacy',
                    'url' => '/'.$l.'/research',
                ],
                [
                    'id' => 4,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'الذكاء الاصطناعي التطبيقي للأنظمة الصناعية' : 'Applied AI for Industrial Systems',
                    'slug' => 'applied-ai-industrial-systems',
                    'summary' => $l === 'ar'
                        ? 'استكشاف الأنظمة الذكية ودعم القرار القائم على البيانات.'
                        : 'Exploring intelligent systems and data-driven decision support.',
                    'imageUrl' => '/images/Gemini_Generated_Image_c89yjwc89yjwc89y.webp',
                    'categoryLabel' => 'AI',
                    'url' => '/'.$l.'/research',
                ],
                [
                    'id' => 5,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'البناء الذكي والتصميم المستدام' : 'Smart Construction and Sustainable Design',
                    'slug' => 'smart-construction-sustainable-design',
                    'summary' => $l === 'ar'
                        ? 'التحقيق في الأداء الإنشائي وكفاءة الموقع.'
                        : 'Investigating structural performance and site efficiency.',
                    'imageUrl' => '/images/Gemini_Generated_Image_rrcjc2rrcjc2rrcj.webp',
                    'categoryLabel' => 'Construction',
                    'url' => '/'.$l.'/research',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Events & Activities – first 5 from mockCalendarEvents
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function events(string $locale): array
    {
        $l = $locale;

        return [
            'title' => $l === 'ar' ? 'فعاليات وأنشطة' : 'SPU Events & Activities',
            'events' => [
                [
                    'id' => 1,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'ندوة الحرم الجامعي المفتوح' : 'Open Campus Seminar',
                    'slug' => 'open-campus-seminar',
                    'summary' => $l === 'ar'
                        ? 'استكشف الحرم الجامعي وتعرف على البرامج الدراسية.'
                        : 'Explore campus and learn about programs.',
                    'imageUrl' => '/images/slider-2.webp',
                    'startsAt' => '2026-03-13',
                    'timeLabel' => 'Seminar',
                    'location' => $l === 'ar' ? 'الحرم الجامعي' : 'Main Campus',
                    'url' => '/'.$l.'/admissions',
                ],
                [
                    'id' => 2,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'ورشة عمل اكتشاف البرامج' : 'Program Discovery Workshop',
                    'slug' => 'program-discovery-workshop',
                    'summary' => $l === 'ar'
                        ? 'ورشة عمل تفاعلية للتخطيط للمسار الأكاديمي.'
                        : 'Interactive workshop for academic track planning.',
                    'imageUrl' => '/images/slider-3.webp',
                    'startsAt' => '2026-03-13',
                    'timeLabel' => 'Workshop',
                    'location' => $l === 'ar' ? 'الحرم الجامعي' : 'Main Campus',
                    'url' => '/'.$l.'/admissions',
                ],
                [
                    'id' => 3,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'جولة في الحرم الجامعي' : 'Campus Tour',
                    'slug' => 'campus-tour',
                    'summary' => $l === 'ar'
                        ? 'جولة إرشادية في مسارات الطلاب بالجامعة.'
                        : 'Guided tour of university student spaces.',
                    'imageUrl' => '/images/unsplash_s9CC2SKySJM.webp',
                    'startsAt' => '2026-03-13',
                    'timeLabel' => 'Tour',
                    'location' => $l === 'ar' ? 'الحرم الجامعي' : 'Main Campus',
                    'url' => '/'.$l.'/contact',
                ],
                [
                    'id' => 4,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'منتدى الابتكار الطبي' : 'Medical Innovation Forum',
                    'slug' => 'medical-innovation-forum',
                    'summary' => $l === 'ar'
                        ? 'باحثون من الهيئة التدريسية يشاركون الابتكارات الطبية الحالية.'
                        : 'Faculty researchers share current medical innovations.',
                    'imageUrl' => '/images/slider-4.webp',
                    'startsAt' => '2026-03-23',
                    'timeLabel' => 'Research Talk',
                    'location' => $l === 'ar' ? 'الحرم الجامعي' : 'Main Campus',
                    'url' => '/'.$l.'/research',
                ],
                [
                    'id' => 5,
                    'locale' => $l,
                    'title' => $l === 'ar' ? 'معرض الأندية الجامعية' : 'Campus Clubs Fair',
                    'slug' => 'campus-clubs-fair',
                    'summary' => $l === 'ar'
                        ? 'انضم إلى الأنشطة التي تناسب اهتماماتك في الحرم الجامعي.'
                        : 'Join activities matching your interests on campus.',
                    'imageUrl' => '/images/unsplash_VckdJzo7ig0.webp',
                    'startsAt' => '2026-03-30',
                    'timeLabel' => 'Student Life',
                    'location' => $l === 'ar' ? 'الحرم الجامعي' : 'Main Campus',
                    'url' => '/'.$l.'/student-life',
                ],
            ],
            'content' => [
                'calendarHighlights' => [
                    ['date' => '2026-03-13', 'label' => $l === 'ar' ? 'ندوة' : 'Seminar'],
                    ['date' => '2026-03-23', 'label' => $l === 'ar' ? 'منتدى' : 'Forum'],
                    ['date' => '2026-03-30', 'label' => $l === 'ar' ? 'معرض' : 'Fair'],
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Medical Facilities – from healthcareContent
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function medical(string $locale): array
    {
        $l = $locale;

        return [
            'title' => $l === 'ar' ? 'المرافق الطبية والخدمية' : 'SPU Healthcare Facilities',
            'items' => [
                [
                    'title' => $l === 'ar' ? 'الرعاية الصحية في SPU' : 'HealthCare at SPU',
                    'summary' => $l === 'ar'
                        ? 'توفر SPU للطلاب إمكانية الوصول إلى الممارسة الطبية والسنية العملية في الحرم الجامعي'
                        : 'SPU provides students with access to practical medical and dental exposure on campus',
                    'imageUrl' => '/images/healthcare-main.webp',
                    'features' => $l === 'ar'
                        ? ['مشفى الجامعة', 'عيادة الأسنان', 'دعم التعلم السريري']
                        : ['University Hospital', 'Dental Clinic', 'Clinical Learning Support'],
                    'action' => $this->action(
                        $l === 'ar' ? 'استكشاف المشفى' : 'Explore Hospital',
                        '/'.$l.'/faculties',
                    ),
                ],
                [
                    'title' => $l === 'ar' ? 'مشفى SPU' : 'SPU Hospital',
                    'summary' => $l === 'ar'
                        ? 'تشخيص طبي متقدم ومرافق تدريب سريري.'
                        : 'Advanced medical diagnostics and clinical training facilities.',
                    'imageUrl' => '/images/healthcare-hospital.webp',
                ],
                [
                    'title' => $l === 'ar' ? 'عيادة SPU للأسنان' : 'SPU Dental Clinical',
                    'imageUrl' => '/images/healthcare-dental.webp',
                    'action' => $this->action(
                        $l === 'ar' ? 'استكشاف العيادة' : 'Explore Clinic',
                        '/'.$l.'/faculties',
                    ),
                ],
            ],
            'stats' => [
                $this->stat('200', $l === 'ar' ? 'أسرة المشفى' : 'HOSPITAL BEDS', suffix: '+', sortOrder: 1),
                $this->stat('80', $l === 'ar' ? 'أطباء أخصائيين' : 'SPECIALIST DOCTORS', suffix: '+', sortOrder: 2),
                $this->stat('30', $l === 'ar' ? 'كراسي الأسنان' : 'DENTAL CHAIRS', suffix: '+', sortOrder: 3),
                $this->stat('12', $l === 'ar' ? 'مرضى سنوياً' : 'PATIENTS ANNUALLY', suffix: '+', sortOrder: 4),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Bottom Stats
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function bottomStats(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'أرقام إضافية' : 'Additional Figures',
            'stats' => [
                $this->stat('200', $locale === 'ar' ? 'أسرة المشفى' : 'Hospital Beds', suffix: '+', sortOrder: 1),
                $this->stat('80', $locale === 'ar' ? 'أطباء أخصائيين' : 'Specialist Doctors', suffix: '+', sortOrder: 2),
                $this->stat('30', $locale === 'ar' ? 'كراسي الأسنان' : 'Dental Chairs', suffix: '+', sortOrder: 3),
                $this->stat('12', $locale === 'ar' ? 'مرضى سنوياً' : 'Patients Annually', suffix: '+', sortOrder: 4),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Footer – from footerContent in layout-content.js
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function footer(string $locale): array
    {
        $l = $locale;

        return [
            'footerColumns' => [
                [
                    'title' => $l === 'ar' ? 'استكشف SPU' : 'EXPLORE SPU',
                    'links' => [
                        $this->action($l === 'ar' ? 'عن الجامعة' : 'About SPU', '/'.$l.'/about'),
                        $this->action($l === 'ar' ? 'الكليات' : 'Faculties', '/'.$l.'/faculties'),
                        $this->action($l === 'ar' ? 'القبول والتسجيل' : 'Admissions', '/'.$l.'/admissions'),
                        $this->action($l === 'ar' ? 'البحث العلمي' : 'Research', '/'.$l.'/research'),
                        $this->action($l === 'ar' ? 'الحياة الجامعية' : 'Student Life', '/'.$l.'/student-life'),
                        $this->action($l === 'ar' ? 'الأخبار' : 'News', '/'.$l.'/news'),
                    ],
                ],
            ],
            'contactLinks' => [
                [
                    'type' => 'address',
                    'icon' => 'fas fa-map-marker-alt',
                    'label' => $l === 'ar' ? 'العنوان' : 'Address',
                    'value' => $l === 'ar'
                        ? 'مقر الجامعة الرئيس، أوتوستراد درعا الدولي، بعد بلدة الكسوة، خيارة دنون، دمشق.'
                        : 'University headquarters, Daraa International Highway, past Al-Kiswa, Khayara Danoun, Damascus.',
                ],
                [
                    'type' => 'phone',
                    'icon' => 'fas fa-phone-alt',
                    'label' => 'Phone',
                    'value' => '+963 11 9860',
                ],
                [
                    'type' => 'email',
                    'icon' => 'fas fa-envelope',
                    'label' => 'Email',
                    'value' => 'info@spu.edu.sy',
                ],
            ],
            'socialLinks' => [
                ['platform' => 'Globe', 'url' => 'https://spu.edu.sy/', 'icon' => 'fas fa-globe', 'isEnabled' => true],
                ['platform' => 'Telegram', 'url' => 'https://telegram.me/SPUchannel', 'icon' => 'fab fa-telegram-plane', 'isEnabled' => true],
                ['platform' => 'Facebook', 'url' => 'https://www.facebook.com/SPUpage.sy/', 'icon' => 'fab fa-facebook-f', 'isEnabled' => true],
                ['platform' => 'Instagram', 'url' => 'https://www.instagram.com/spu_syrian_private_university/', 'icon' => 'fab fa-instagram', 'isEnabled' => true],
                ['platform' => 'YouTube', 'url' => 'https://www.youtube.com/channel/UCaoshcqsl9_fx7WVYgEZI5A', 'icon' => 'fab fa-youtube', 'isEnabled' => true],
            ],
            'content' => [
                'brandBlock' => [
                    'title' => $l === 'ar' ? 'الجامعة السورية الخاصة' : 'SYRIAN PRIVATE UNIVERSITY',
                    'body' => $l === 'ar'
                        ? 'ملتزمون بتعزيز التميز الأكاديمي والقيادة العالمية من قلب دمشق.'
                        : 'Committed to fostering academic excellence and global leadership from the heart of Damascus.',
                ],
                'mapEmbed' => [
                    'url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13346.741280351659!2d36.26129575!3d33.31448835!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1518f99e3f1e1e1f%3A0xe1e1e1e1e1e1e1e1!2sSyrian%20Private%20University!5e0!3m2!1sen!2ssy!4v1712710000000!5m2!1sen!2ssy',
                    'label' => $l === 'ar' ? 'الموقع' : 'LOCATION',
                ],
                'copyrightText' => $l === 'ar'
                    ? '© 2026 الجامعة السورية الخاصة. التميز في التعليم.'
                    : '© 2026 Syrian Private University. Excellence in Education.',
                'legalLinks' => [
                    $this->action($l === 'ar' ? 'قدّم الآن' : 'Apply Now', '/'.$l.'/admissions'),
                    $this->action($l === 'ar' ? 'بوابة الطالب' : 'Student Portal', '#'),
                    $this->action($l === 'ar' ? 'تواصل مع SPU' : 'Contact SPU', '/'.$l.'/contact'),
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function action(string $label, string $url): array
    {
        return [
            'label' => $label,
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stat(
        string $value,
        string $label,
        ?string $prefix = null,
        ?string $suffix = null,
        ?string $icon = null,
        ?string $helperText = null,
        ?int $sortOrder = null,
    ): array {
        return array_filter([
            'value' => $value,
            'label' => $label,
            'prefix' => $prefix,
            'suffix' => $suffix,
            'icon' => $icon,
            'helperText' => $helperText,
            'sortOrder' => $sortOrder,
        ], static fn (mixed $item): bool => $item !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyItem(
        string $title,
        string $imageUrl,
        string $accent,
        string $metric,
        string $actionLabel,
        string $actionUrl,
    ): array {
        return [
            'title' => $title,
            'imageUrl' => $imageUrl,
            'accent' => $accent,
            'metric' => $metric,
            'action' => $this->action($actionLabel, $actionUrl),
        ];
    }
}
