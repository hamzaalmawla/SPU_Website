<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\HomepageSectionServiceInterface;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use Illuminate\Database\Seeder;

/**
 * Seeds structured homepage starter content for local development and runtime validation.
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

    /**
     * @return array<string, mixed>
     */
    private function hero(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'eyebrow' => 'الجامعة الخاصة السورية',
                'badge' => 'محتوى تمهيدي',
                'title' => 'منصة الجامعة الرئيسية',
                'subtitle' => 'واجهة ثنائية اللغة قابلة للإدارة للهوية والمحتوى الأساسي.',
                'summary' => 'هذا المحتوى مخصص للتطوير المحلي واختبار تدفق المسودات والمعاينة والنشر.',
                'backgroundImageUrl' => '/images/home/hero-ar.jpg',
                'videoUrl' => '/videos/home/hero-ar.mp4',
                'primaryAction' => $this->action('استكشف البرامج', '/ar/faculties'),
                'secondaryAction' => $this->action('ابدأ القبول', '/ar/admissions'),
                'content' => [
                    'overlay' => ['style' => 'dark-gradient', 'opacity' => '72'],
                    'alignment' => ['desktop' => 'start', 'mobile' => 'center'],
                    'imageAlt' => 'المبنى الرئيسي للجامعة الخاصة السورية',
                ],
            ];
        }

        return [
            'eyebrow' => 'Syrian Private University',
            'badge' => 'Starter content',
            'title' => 'Primary university shell',
            'subtitle' => 'A bilingual managed homepage for institutional identity and core content.',
            'summary' => 'This content exists for local development and safe validation of draft, preview, and publish flows.',
            'backgroundImageUrl' => '/images/home/hero-en.jpg',
            'videoUrl' => '/videos/home/hero-en.mp4',
            'primaryAction' => $this->action('Explore programs', '/en/faculties'),
            'secondaryAction' => $this->action('Start admissions', '/en/admissions'),
            'content' => [
                'overlay' => ['style' => 'dark-gradient', 'opacity' => '72'],
                'alignment' => ['desktop' => 'start', 'mobile' => 'center'],
                'imageAlt' => 'Syrian Private University main building',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heroStats(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'أرقام سريعة' : 'Quick stats',
            'stats' => [
                $this->stat('12', $locale === 'ar' ? 'كلية وبرنامج' : 'faculties and programs', icon: 'heroicon-o-building-office-2', sortOrder: 1),
                $this->stat('18k', $locale === 'ar' ? 'طالب وطالبة' : 'students', suffix: '+', icon: 'heroicon-o-user-group', sortOrder: 2),
                $this->stat('450', $locale === 'ar' ? 'عضو هيئة تدريس' : 'academic staff', prefix: '+', icon: 'heroicon-o-academic-cap', sortOrder: 3),
                $this->stat('25', $locale === 'ar' ? 'شراكة وتعاون' : 'partnerships', prefix: '+', icon: 'heroicon-o-globe-alt', sortOrder: 4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function academicFaculties(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'الكليات الأكاديمية' : 'Academic faculties',
            'subtitle' => $locale === 'ar' ? 'بطاقات أولية قابلة للإدارة محلياً.' : 'Managed starter cards for local development.',
            'sectionAction' => $this->action(
                $locale === 'ar' ? 'عرض جميع الكليات' : 'View all faculties',
                '/'.$locale.'/faculties',
            ),
            'items' => [
                $this->facultyItem(
                    title: $locale === 'ar' ? 'كلية الطب البشري' : 'Faculty of Medicine',
                    summary: $locale === 'ar' ? 'تجهيزات تعليمية ومخبرية متقدمة للمسار الطبي.' : 'Advanced labs and teaching spaces for medical education.',
                    imageUrl: '/images/home/faculty-medicine.jpg',
                    accent: 'crimson',
                    actionLabel: $locale === 'ar' ? 'استكشف الكلية' : 'Explore faculty',
                    actionUrl: '/'.$locale.'/faculties',
                ),
                $this->facultyItem(
                    title: $locale === 'ar' ? 'كلية طب الأسنان' : 'Faculty of Dentistry',
                    summary: $locale === 'ar' ? 'مسار تدريبي سريري متدرج مع مرافق تطبيقية.' : 'Progressive clinical training supported by practical facilities.',
                    imageUrl: '/images/home/faculty-dentistry.jpg',
                    accent: 'amber',
                    actionLabel: $locale === 'ar' ? 'استكشف الكلية' : 'Explore faculty',
                    actionUrl: '/'.$locale.'/faculties',
                ),
                $this->facultyItem(
                    title: $locale === 'ar' ? 'كلية الصيدلة' : 'Faculty of Pharmacy',
                    summary: $locale === 'ar' ? 'قاعات حديثة ومختبرات تدعم التعلم التطبيقي.' : 'Modern classrooms and labs that support applied learning.',
                    imageUrl: '/images/home/faculty-pharmacy.jpg',
                    accent: 'emerald',
                    actionLabel: $locale === 'ar' ? 'استكشف الكلية' : 'Explore faculty',
                    actionUrl: '/'.$locale.'/faculties',
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function achievements(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'إنجازات مختارة' : 'Achievements highlights',
            'subtitle' => $locale === 'ar' ? 'مساحة مرنة لإبراز الإنجازات المؤسسية.' : 'A flexible area for institutional highlights.',
            'items' => [
                [
                    'title' => $locale === 'ar' ? 'اعتماد البرامج' : 'Program accreditation',
                    'summary' => $locale === 'ar' ? 'نماذج أولية للاعتمادات والأوسمة الأكاديمية.' : 'Starter accreditation and recognition content.',
                    'metric' => $locale === 'ar' ? '8 برامج' : '8 programs',
                    'dateLabel' => '2026',
                    'action' => $this->action($locale === 'ar' ? 'التفاصيل' : 'Details', '/'.$locale.'/about'),
                ],
                [
                    'title' => $locale === 'ar' ? 'الجوائز الطلابية' : 'Student awards',
                    'summary' => $locale === 'ar' ? 'قسم منظم للإنجازات الطلابية والأنشطة.' : 'Structured content for student awards and activities.',
                    'metric' => $locale === 'ar' ? '+40 إنجازاً' : '40+ recognitions',
                    'dateLabel' => '2025',
                    'action' => $this->action($locale === 'ar' ? 'التفاصيل' : 'Details', '/'.$locale.'/about'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function news(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'أخبار الجامعة' : 'University news',
            'sectionAction' => $this->action($locale === 'ar' ? 'جميع الأخبار' : 'All news', '/'.$locale.'/news'),
            'articles' => [
                [
                    'id' => 1,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'تحديثات المنصة المؤسسية' : 'Institutional platform updates',
                    'slug' => 'institutional-platform-updates',
                    'excerpt' => $locale === 'ar' ? 'خبر تمهيدي لاختبار بطاقات الأخبار.' : 'Starter news card used for runtime validation.',
                    'imageUrl' => '/images/home/news-1.jpg',
                    'publishedAt' => now()->subDays(7)->toDateString(),
                    'categoryLabel' => $locale === 'ar' ? 'الموقع' : 'Website',
                    'badgeTag' => $locale === 'ar' ? 'مستجد' : 'New',
                    'url' => '/'.$locale.'/news',
                ],
                [
                    'id' => 2,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'استعدادات العام الدراسي' : 'Academic year preparations',
                    'slug' => 'academic-year-preparations',
                    'excerpt' => $locale === 'ar' ? 'بطاقة خبر ثانية لإظهار الوضع اليدوي المؤقت.' : 'Second card for manual-selection starter content.',
                    'imageUrl' => '/images/home/news-2.jpg',
                    'publishedAt' => now()->subDays(12)->toDateString(),
                    'categoryLabel' => $locale === 'ar' ? 'الجامعة' : 'Campus',
                    'badgeTag' => $locale === 'ar' ? 'مميز' : 'Featured',
                    'url' => '/'.$locale.'/news',
                ],
            ],
            'content' => [
                'selectionMode' => 'manual',
                'fallbackMode' => 'shell',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function research(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'البحوث والدراسات' : 'Research and studies',
            'sectionAction' => $this->action($locale === 'ar' ? 'جميع البحوث' : 'All research', '/'.$locale.'/research'),
            'researchItems' => [
                [
                    'id' => 1,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'دراسة تمهيدية في الصحة الرقمية' : 'Starter study in digital health',
                    'slug' => 'starter-digital-health-study',
                    'summary' => $locale === 'ar' ? 'بطاقة بحث أولية لاختبار الواجهة العامة.' : 'Starter research card for public runtime validation.',
                    'imageUrl' => '/images/home/research-1.jpg',
                    'publishedAt' => now()->subDays(20)->toDateString(),
                    'categoryLabel' => $locale === 'ar' ? 'دراسة' : 'Study',
                    'authors' => $locale === 'ar' ? ['فريق البحث الطبي'] : ['Medical research team'],
                    'url' => '/'.$locale.'/research',
                ],
                [
                    'id' => 2,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'مشروع تعاوني في تقنيات التعليم' : 'Collaborative education technology project',
                    'slug' => 'education-technology-project',
                    'summary' => $locale === 'ar' ? 'مثال منظم على بطاقة بحثية قابلة للتحرير.' : 'Structured example of an editable research card.',
                    'imageUrl' => '/images/home/research-2.jpg',
                    'publishedAt' => now()->subDays(35)->toDateString(),
                    'categoryLabel' => $locale === 'ar' ? 'مشروع' : 'Project',
                    'authors' => $locale === 'ar' ? ['فريق الابتكار الأكاديمي'] : ['Academic innovation group'],
                    'url' => '/'.$locale.'/research',
                ],
            ],
            'content' => [
                'selectionMode' => 'manual',
                'fallbackMode' => 'shell',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function events(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'الفعاليات والأنشطة' : 'Events and activities',
            'events' => [
                [
                    'id' => 1,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'يوم تعريفي للطلبة الجدد' : 'New student welcome day',
                    'slug' => 'new-student-welcome-day',
                    'summary' => $locale === 'ar' ? 'بطاقة فعالية أولية لواجهة الصفحة الرئيسية.' : 'Starter event card for the homepage shell.',
                    'imageUrl' => '/images/home/event-1.jpg',
                    'startsAt' => now()->addDays(10)->toDateString(),
                    'timeLabel' => $locale === 'ar' ? '10:00 صباحاً' : '10:00 AM',
                    'location' => $locale === 'ar' ? 'الحرم الجامعي الرئيسي' : 'Main campus',
                    'url' => '/'.$locale.'/events',
                ],
                [
                    'id' => 2,
                    'locale' => $locale,
                    'title' => $locale === 'ar' ? 'ورشة مهارات مهنية' : 'Career skills workshop',
                    'slug' => 'career-skills-workshop',
                    'summary' => $locale === 'ar' ? 'مثال منظم على فعالية أكاديمية عامة.' : 'Structured example of a public academic event.',
                    'imageUrl' => '/images/home/event-2.jpg',
                    'startsAt' => now()->addDays(18)->toDateString(),
                    'timeLabel' => $locale === 'ar' ? '12:30 ظهراً' : '12:30 PM',
                    'location' => $locale === 'ar' ? 'قاعة المؤتمرات' : 'Conference hall',
                    'url' => '/'.$locale.'/events',
                ],
            ],
            'content' => [
                'calendarHighlights' => [
                    ['date' => now()->addDays(10)->toDateString(), 'label' => $locale === 'ar' ? 'تعريفي' : 'Welcome'],
                    ['date' => now()->addDays(18)->toDateString(), 'label' => $locale === 'ar' ? 'مهني' : 'Career'],
                ],
                'mobileConfig' => ['compactCards' => true],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function medical(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'المرافق والخدمات الطبية' : 'Medical facilities and services',
            'items' => [
                [
                    'title' => $locale === 'ar' ? 'العيادات التعليمية' : 'Teaching clinics',
                    'summary' => $locale === 'ar' ? 'مساحات تدريبية مهيأة للتعلم السريري.' : 'Clinical training spaces for hands-on learning.',
                    'imageUrl' => '/images/home/medical-1.jpg',
                    'typeTag' => $locale === 'ar' ? 'تعليمي' : 'Teaching',
                    'action' => $this->action($locale === 'ar' ? 'المزيد' : 'Learn more', '/'.$locale.'/facilities'),
                ],
                [
                    'title' => $locale === 'ar' ? 'المختبرات الطبية' : 'Medical laboratories',
                    'summary' => $locale === 'ar' ? 'معدات وتجهيزات أولية لعرض البنية الطبية.' : 'Starter content for medical lab infrastructure.',
                    'imageUrl' => '/images/home/medical-2.jpg',
                    'typeTag' => $locale === 'ar' ? 'مرافق' : 'Facility',
                    'action' => $this->action($locale === 'ar' ? 'المزيد' : 'Learn more', '/'.$locale.'/facilities'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bottomStats(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'أرقام إضافية' : 'Additional figures',
            'stats' => [
                $this->stat('96', $locale === 'ar' ? 'مبادرات طلابية' : 'student initiatives', suffix: '%', sortOrder: 1),
                $this->stat('24', $locale === 'ar' ? 'مختبراً وقاعة' : 'labs and studios', prefix: '+', sortOrder: 2),
                $this->stat('14', $locale === 'ar' ? 'اتفاقية تعاون' : 'active agreements', prefix: '+', sortOrder: 3),
                $this->stat('7', $locale === 'ar' ? 'مسارات تطوير' : 'development tracks', prefix: '+', sortOrder: 4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function footer(string $locale): array
    {
        return [
            'footerColumns' => [
                [
                    'title' => $locale === 'ar' ? 'روابط مهمة' : 'Important links',
                    'links' => [
                        $this->action($locale === 'ar' ? 'عن الجامعة' : 'About', '/'.$locale.'/about'),
                        $this->action($locale === 'ar' ? 'الكليات' : 'Faculties', '/'.$locale.'/faculties'),
                    ],
                ],
                [
                    'title' => $locale === 'ar' ? 'الخدمات' : 'Services',
                    'links' => [
                        $this->action($locale === 'ar' ? 'القبول' : 'Admissions', '/'.$locale.'/admissions'),
                        $this->action($locale === 'ar' ? 'اتصل بنا' : 'Contact', '/'.$locale.'/contact'),
                    ],
                ],
            ],
            'contactLinks' => [
                ['type' => 'email', 'label' => $locale === 'ar' ? 'البريد الإلكتروني' : 'Email', 'value' => 'info@spu.edu.sy'],
                ['type' => 'phone', 'label' => $locale === 'ar' ? 'الهاتف' : 'Phone', 'value' => '+963 11 000 0000'],
            ],
            'socialLinks' => [
                ['platform' => 'Facebook', 'url' => 'https://facebook.com/spu', 'isEnabled' => true],
                ['platform' => 'Instagram', 'url' => 'https://instagram.com/spu', 'isEnabled' => true],
            ],
            'content' => [
                'brandBlock' => [
                    'title' => $locale === 'ar' ? 'الجامعة الخاصة السورية' : 'Syrian Private University',
                    'body' => $locale === 'ar' ? 'محتوى تمهيدي لإظهار البنية النهائية للتذييل.' : 'Starter content that reflects the final footer structure.',
                    'logoUrl' => '/images/home/footer-logo.png',
                ],
                'contactBlock' => [
                    'title' => $locale === 'ar' ? 'تواصل معنا' : 'Contact us',
                    'address' => $locale === 'ar' ? 'دمشق، سوريا' : 'Damascus, Syria',
                    'phone' => '+963 11 000 0000',
                    'email' => 'info@spu.edu.sy',
                ],
                'mapEmbed' => [
                    'type' => 'map-placeholder',
                    'label' => $locale === 'ar' ? 'موقع الجامعة' : 'Campus map',
                ],
                'legalLinks' => [
                    $this->action($locale === 'ar' ? 'سياسة الخصوصية' : 'Privacy policy', '/'.$locale.'/about'),
                    $this->action($locale === 'ar' ? 'شروط الاستخدام' : 'Terms of use', '/'.$locale.'/about'),
                ],
                'copyrightText' => $locale === 'ar'
                    ? 'الجامعة الخاصة السورية. محتوى تمهيدي للتطوير المحلي.'
                    : 'Syrian Private University. Starter content for local development.',
                'emergencyNotice' => [
                    'isEnabled' => false,
                    'title' => null,
                    'message' => null,
                ],
                'imageAlt' => $locale === 'ar' ? 'شعار الجامعة الخاصة السورية' : 'Syrian Private University logo',
            ],
        ];
    }

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
        ?int $sortOrder = null,
    ): array {
        return array_filter([
            'value' => $value,
            'label' => $label,
            'prefix' => $prefix,
            'suffix' => $suffix,
            'icon' => $icon,
            'sortOrder' => $sortOrder,
        ], static fn (mixed $item): bool => $item !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyItem(
        string $title,
        string $summary,
        string $imageUrl,
        string $accent,
        string $actionLabel,
        string $actionUrl,
    ): array {
        return [
            'title' => $title,
            'summary' => $summary,
            'imageUrl' => $imageUrl,
            'accent' => $accent,
            'action' => $this->action($actionLabel, $actionUrl),
        ];
    }
}
