<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\CampusLifePageServiceInterface;
use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;

final class CampusLifePageService implements CampusLifePageServiceInterface
{
    public function getLanding(string $locale): CampusLifePageDTO
    {
        $landing = $this->normalizeUrls($this->localized($this->landingPayload(), $locale), $locale);

        return new CampusLifePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            landing: $landing,
            seoTitle: $locale === 'ar' ? 'الحياة الجامعية | الجامعة السورية الخاصة' : 'Campus Life | Syrian Private University',
            seoDescription: $locale === 'ar'
                ? 'اكتشف الخدمات والمرافق والأنشطة الطلابية والحياة المهنية والصحية في الجامعة السورية الخاصة.'
                : 'Find student services, campus activities, academic guidance, and digital access paths at SPU.',
            seoImage: '/images/logo-spu.png',
        );
    }

    public function getSection(string $slug, string $locale): ?CampusLifeSectionDTO
    {
        $payload = $this->sectionPayloads()[$slug] ?? null;

        if ($payload === null) {
            return null;
        }

        $section = $this->normalizeUrls($this->localized($payload, $locale), $locale);
        $title = (string) ($section['hero']['title'] ?? $section['title'] ?? ($locale === 'ar' ? 'الحياة الجامعية' : 'Campus Life'));
        $description = (string) ($section['seoDescription'] ?? ($locale === 'ar'
            ? 'معلومات عن خدمات ومرافق الحياة الجامعية في الجامعة السورية الخاصة.'
            : 'Student life services and campus facility information at Syrian Private University.'));

        return new CampusLifeSectionDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sectionSlug: $slug,
            section: $section,
            seoTitle: $title.' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'SPU'),
            seoDescription: $description,
            seoImage: (string) ($section['hero']['image'] ?? '/images/logo-spu.png'),
        );
    }

    /** @return array<string, mixed> */
    private function landingPayload(): array
    {
        return [
            'hero' => [
                'titleEn' => 'Campus Life',
                'titleAr' => 'الحياة الجامعية',
                'summaryEn' => 'Discover the vibrant community, essential services, and exceptional facilities that shape your experience at Syrian Private University.',
                'summaryAr' => 'اكتشف المجتمع النابض بالحياة والخدمات الأساسية والمرافق الاستثنائية التي تشكل تجربتك في الجامعة السورية الخاصة.',
                'image' => '/images/admissions-hero-campus.webp',
                'quickLinks' => [
                    ['labelEn' => 'Campus Services', 'labelAr' => 'خدمات الحرم الجامعي', 'href' => '/campus-life/services'],
                    ['labelEn' => 'Health & Wellbeing', 'labelAr' => 'الصحة والرفاهية', 'href' => '/campus-life#health'],
                    ['labelEn' => 'Student Activities', 'labelAr' => 'الأنشطة الطلابية', 'href' => '/campus-life/clubs-activities'],
                    ['labelEn' => 'Career Development', 'labelAr' => 'التطوير المهني', 'href' => '/campus-life/career-development'],
                ],
            ],
            'intro' => [
                'titleEn' => 'Your Campus Life Journey',
                'titleAr' => 'رحلتك في الحياة الجامعية',
                'summaryEn' => 'A connected campus journey designed to support your academic advancement, personal wellbeing, and professional development from day one.',
                'summaryAr' => 'رحلة جامعية متصلة مصممة لدعم تقدمك الأكاديمي ورفاهيتك الشخصية وتطورك المهني من اليوم الأول.',
            ],
            'stats' => [
                ['id' => 'students', 'value' => 8500, 'suffixEn' => '+', 'suffixAr' => '+', 'labelEn' => 'Active Students', 'labelAr' => 'طالب نشط', 'icon' => '/images/icon-user-graduate-outline.svg'],
                ['id' => 'clubs', 'value' => 25, 'suffixEn' => '+', 'suffixAr' => '+', 'labelEn' => 'Student Clubs', 'labelAr' => 'نادي طلابي', 'icon' => '/images/icon-users-outline.svg'],
                ['id' => 'events', 'value' => 120, 'suffixEn' => '+', 'suffixAr' => '+', 'labelEn' => 'Annual Events', 'labelAr' => 'فعالية سنوية', 'icon' => '/images/icon-calendar-outline.svg'],
                ['id' => 'satisfaction', 'value' => 96, 'suffixEn' => '%', 'suffixAr' => '%', 'labelEn' => 'Student Satisfaction', 'labelAr' => 'رضا الطلاب', 'icon' => '/images/icon-handshake-outline.svg'],
            ],
            'features' => [
                'eyebrowEn' => 'WHY SPU',
                'eyebrowAr' => 'لماذا SPU',
                'titleEn' => 'A Campus Built for Your Success',
                'titleAr' => 'حرم جامعي مصمم لنجاحك',
                'summaryEn' => 'From world-class facilities to a supportive community, everything at SPU is designed to help you thrive academically and personally.',
                'summaryAr' => 'من المرافق العالمية إلى المجتمع الداعم، كل شيء في SPU مصمم لمساعدتك على التفوق أكاديمياً وشخصياً.',
                'items' => [
                    ['icon' => '/images/icons/hospital.svg', 'titleEn' => 'University Hospital', 'titleAr' => 'المستشفى الجامعي', 'summaryEn' => 'Full-service teaching hospital providing clinical training and healthcare for students and the community.', 'summaryAr' => 'مستشفى تعليمي متكامل يوفر التدريب السريري والرعاية الصحية للطلاب والمجتمع.'],
                    ['icon' => '/images/icons/lab.svg', 'titleEn' => 'Advanced Laboratories', 'titleAr' => 'مختبرات متقدمة', 'summaryEn' => 'State-of-the-art research and teaching labs equipped with the latest scientific instruments.', 'summaryAr' => 'مختبرات بحثية وتعليمية مجهزة بأحدث الأجهزة العلمية.'],
                    ['icon' => '/images/icons/globe.svg', 'titleEn' => 'Digital Campus', 'titleAr' => 'حرم رقمي', 'summaryEn' => 'Full Wi-Fi coverage, smart classrooms, and integrated digital platforms for seamless learning.', 'summaryAr' => 'تغطية واي فاي كاملة وفصول ذكية ومنصات رقمية متكاملة للتعلم السلس.'],
                    ['icon' => '/images/icons/book.svg', 'titleEn' => 'Modern Library', 'titleAr' => 'مكتبة حديثة', 'summaryEn' => 'Extensive physical and digital collections with quiet study spaces and collaborative areas.', 'summaryAr' => 'مجموعات مادية ورقمية واسعة مع مساحات دراسة هادئة ومناطق تعاونية.'],
                    ['icon' => '/images/icons/training.svg', 'titleEn' => 'Sports Facilities', 'titleAr' => 'المرافق الرياضية', 'summaryEn' => 'Indoor and outdoor sports facilities including courts, fitness center, and recreational areas.', 'summaryAr' => 'مرافق رياضية داخلية وخارجية تشمل ملاعب ومركز لياقة ومناطق ترفيهية.'],
                    ['icon' => '/images/icons/exchange.svg', 'titleEn' => 'Transport Network', 'titleAr' => 'شبكة النقل', 'summaryEn' => 'Organized bus routes connecting campus to major city areas with flexible schedules.', 'summaryAr' => 'خطوط حافلات منظمة تربط الحرم الجامعي بالمناطق الرئيسية في المدينة بجداول مرنة.'],
                ],
            ],
            'servicesHeading' => ['eyebrowEn' => 'OUR SERVICES', 'eyebrowAr' => 'خدماتنا', 'titleEn' => 'Everything You Need in One Place', 'titleAr' => 'كل ما تحتاجه في مكان واحد'],
            'services' => [
                ['number' => '01', 'titleEn' => 'Campus Services', 'titleAr' => 'خدمات الحرم الجامعي', 'summaryEn' => 'A centralized directory for essential student services, including transport, health, IT, cafeteria, and accommodation.', 'summaryAr' => 'دليل مركزي للخدمات الطلابية الأساسية، بما في ذلك النقل والصحة وتقنية المعلومات والكافتيريا والسكن.', 'href' => '/campus-life/services', 'linkEn' => 'Explore Services', 'linkAr' => 'استكشف الخدمات', 'image' => '/images/admissions-hero-students.webp', 'imagePosition' => 'right'],
                ['number' => '02', 'titleEn' => 'University Hospital', 'titleAr' => 'المستشفى الجامعي', 'summaryEn' => 'Information about hospital departments, medical services, working hours, appointments, insurance, and emergency contact.', 'summaryAr' => 'معلومات عن أقسام المستشفى والخدمات الطبية وساعات العمل والمواعيد والتأمين والاتصال بالطوارئ.', 'href' => '/campus-life/hospital', 'linkEn' => 'Explore Hospital', 'linkAr' => 'استكشف المستشفى', 'image' => '/images/campus-hospital.webp', 'imagePosition' => 'left'],
                ['number' => '03', 'titleEn' => 'Dental Clinics', 'titleAr' => 'عيادات الأسنان', 'summaryEn' => 'Details about dental services, clinic hours, booking process, and patient access for students and the public.', 'summaryAr' => 'تفاصيل حول خدمات الأسنان وساعات العيادة وعملية الحجز ووصول المرضى للطلاب والجمهور.', 'href' => '/campus-life/dental', 'linkEn' => 'Explore Clinics', 'linkAr' => 'استكشف العيادات', 'image' => '/images/campus-dental.webp', 'imagePosition' => 'right'],
                ['number' => '04', 'titleEn' => 'Student Clubs & Activities', 'titleAr' => 'الأندية والأنشطة الطلابية', 'summaryEn' => 'A directory of active student clubs and activities, including club descriptions and how students can join.', 'summaryAr' => 'دليل الأندية والأنشطة الطلابية النشطة، بما في ذلك أوصاف الأندية وكيفية انضمام الطلاب.', 'href' => '/campus-life/clubs-activities', 'linkEn' => 'Explore Clubs', 'linkAr' => 'استكشف الأندية', 'image' => '/images/campus-clubs.webp', 'imagePosition' => 'left'],
                ['number' => '05', 'titleEn' => 'Career Development', 'titleAr' => 'التطوير المهني', 'summaryEn' => 'Career support resources, including events, job opportunities, CV workshops, internships, and employer partnerships.', 'summaryAr' => 'موارد الدعم المهني، بما في ذلك الفعاليات وفرص العمل وورش السيرة الذاتية والتدريب والشراكات مع أصحاب العمل.', 'href' => '/campus-life/career-development', 'linkEn' => 'Explore Portal', 'linkAr' => 'استكشف البوابة', 'image' => '/images/about/campus-career.webp', 'imagePosition' => 'right'],
                ['number' => '06', 'titleEn' => 'Health & Insurance', 'titleAr' => 'الصحة والتأمين', 'summaryEn' => 'Student health insurance information, including coverage details, how to use the insurance, and contact information.', 'summaryAr' => 'معلومات التأمين الصحي للطلاب، بما في ذلك تفاصيل التغطية وكيفية استخدام التأمين ومعلومات الاتصال.', 'href' => '/campus-life/health-insurance', 'linkEn' => 'Explore Coverage', 'linkAr' => 'استكشف التغطية', 'image' => '/images/campus-health.webp', 'imagePosition' => 'left'],
                ['number' => '07', 'titleEn' => 'Transport', 'titleAr' => 'النقل', 'summaryEn' => 'Transport routes, schedules, fees, and registration information for students.', 'summaryAr' => 'مسارات النقل والجداول والرسوم ومعلومات التسجيل للطلاب.', 'href' => '/campus-life/transport', 'linkEn' => 'View Routes', 'linkAr' => 'عرض المسارات', 'image' => '/images/campus-transport.webp', 'imagePosition' => 'right'],
            ],
            'gallery' => [
                'eyebrowEn' => 'CAMPUS GALLERY', 'eyebrowAr' => 'معرض الحرم الجامعي', 'titleEn' => 'Experience SPU Campus', 'titleAr' => 'عش تجربة حرم SPU',
                'summaryEn' => 'Take a visual tour through our modern campus facilities, vibrant student spaces, and state-of-the-art learning environments.',
                'summaryAr' => 'قم بجولة بصرية عبر مرافق حرمنا الحديثة ومساحات الطلاب النابضة بالحياة وبيئات التعلم المتطورة.',
                'images' => [
                    ['src' => '/images/campus-feature-01.webp', 'altEn' => 'Campus main building', 'altAr' => 'المبنى الرئيسي للحرم'],
                    ['src' => '/images/campus-feature-02.webp', 'altEn' => 'Student collaboration space', 'altAr' => 'مساحة تعاون الطلاب'],
                    ['src' => '/images/dsc-1060.webp', 'altEn' => 'Campus grounds', 'altAr' => 'أرض الحرم الجامعي'],
                    ['src' => '/images/dsc-1075.webp', 'altEn' => 'University facilities', 'altAr' => 'مرافق الجامعة'],
                    ['src' => '/images/about-hero-2.jpg', 'altEn' => 'Academic environment', 'altAr' => 'البيئة الأكاديمية'],
                    ['src' => '/images/slider-1.webp', 'altEn' => 'SPU campus aerial view', 'altAr' => 'منظر جوي لحرم SPU'],
                ],
            ],
            'portalsHeading' => ['eyebrowEn' => 'DIGITAL ACCESS', 'eyebrowAr' => 'الوصول الرقمي', 'titleEn' => 'Digital Service Portals', 'titleAr' => 'بوابات الخدمات الرقمية'],
            'portals' => [
                ['titleEn' => 'Student Portal', 'titleAr' => 'بوابة الطالب', 'summaryEn' => 'Access student records and core digital services.', 'summaryAr' => 'الوصول إلى السجلات الطلابية والخدمات الرقمية الأساسية.', 'icon' => '/images/icon-check-circle-outline.svg', 'url' => '#'],
                ['titleEn' => 'Electronic Registration', 'titleAr' => 'التسجيل الإلكتروني', 'summaryEn' => 'Reach online registration services through the official portal entry point.', 'summaryAr' => 'الوصول إلى خدمات التسجيل الإلكتروني عبر بوابة الجامعة الرسمية.', 'icon' => '/images/icon-file-outline.svg', 'url' => '#'],
                ['titleEn' => 'Contact Student Affairs', 'titleAr' => 'التواصل مع شؤون الطلاب', 'summaryEn' => 'Get direct guidance for support needs, schedules, and student services.', 'summaryAr' => 'الحصول على إرشاد مباشر لاحتياجات الدعم والجداول والخدمات الطلابية.', 'icon' => '/images/icon-phone-outline.svg', 'url' => '/contact#admissions-support'],
            ],
            'cta' => ['titleEn' => 'Ready to Begin Your Journey?', 'titleAr' => 'مستعد لبدء رحلتك؟', 'summaryEn' => 'Join thousands of students who chose SPU as their path to academic excellence and professional success.', 'summaryAr' => 'انضم إلى آلاف الطلاب الذين اختاروا SPU كطريقهم نحو التميز الأكاديمي والنجاح المهني.', 'primaryLabelEn' => 'Apply Now', 'primaryLabelAr' => 'قدّم الآن', 'primaryUrl' => '/admissions', 'secondaryLabelEn' => 'Contact Us', 'secondaryLabelAr' => 'تواصل معنا', 'secondaryUrl' => '/contact'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function sectionPayloads(): array
    {
        return [
            'services' => $this->campusServicesPayload(),
            'transport' => $this->transportPayload(),
            'clubs-activities' => $this->clubsActivitiesPayload(),
            'career-development' => $this->careerDevelopmentPayload(),
            'dental' => $this->dentalPayload(),
            'hospital' => $this->hospitalPayload(),
            'health-insurance' => $this->healthInsurancePayload(),
        ];
    }

    /** @return array<string, mixed> */
    private function campusServicesPayload(): array
    {
        return [
            'type' => 'services',
            'hero' => ['image' => '/images/admissions-hero-campus.webp', 'titleEn' => 'Campus Services', 'titleAr' => 'خدمات الحرم الجامعي', 'breadcrumbs' => $this->breadcrumbs('Campus Services', 'خدمات الحرم الجامعي', '/campus-life/services')],
            'services' => [
                'titleEn' => 'Available Services', 'titleAr' => 'الخدمات المتاحة', 'accessLabelEn' => 'How to access:', 'accessLabelAr' => 'كيفية الوصول:', 'detailsLabelEn' => 'View Details', 'detailsLabelAr' => 'عرض التفاصيل',
                'items' => [
                    ['id' => 'transport', 'titleEn' => 'Transport', 'titleAr' => 'النقل', 'accessEn' => 'Register at the Transport Office (Building A) and select your route.', 'accessAr' => 'سجّل في مكتب النقل (المبنى A) واختر المسار المناسب لك.', 'href' => '/campus-life/transport', 'image' => '/images/campus-transport.webp'],
                    ['id' => 'health', 'titleEn' => 'Health Services', 'titleAr' => 'الخدمات الصحية', 'accessEn' => 'Visit the on-campus Health Center or review coverage details online.', 'accessAr' => 'زر المركز الصحي داخل الحرم الجامعي أو راجع تفاصيل التغطية الطبية إلكترونياً.', 'href' => '/campus-life/health-insurance', 'image' => '/images/campus-health.webp'],
                    ['id' => 'it', 'titleEn' => 'IT Support', 'titleAr' => 'الدعم التقني', 'accessEn' => 'Submit a service request via E-Services or visit the IT Help Desk.', 'accessAr' => 'قدّم طلب خدمة عبر الخدمات الإلكترونية أو زر مكتب الدعم التقني.', 'href' => '/e-services', 'image' => '/images/healthcare-main.webp'],
                    ['id' => 'cafeteria', 'titleEn' => 'Cafeteria', 'titleAr' => 'الكافتيريا', 'accessEn' => 'Open daily in the Student Center with meals and beverages.', 'accessAr' => 'تعمل يومياً في المركز الطلابي مع وجبات ومشروبات مناسبة.', 'href' => '/campus-life', 'image' => '/images/campus-feature-01.webp', 'wide' => true],
                    ['id' => 'accommodation', 'titleEn' => 'Accommodation', 'titleAr' => 'السكن الطلابي', 'accessEn' => 'Apply through Student Affairs to review available residence options.', 'accessAr' => 'قدّم عبر شؤون الطلاب للاطلاع على خيارات السكن المتاحة.', 'href' => '/contact', 'image' => '/images/campus-feature-01.webp', 'wide' => true],
                ],
            ],
            'support' => ['image' => '/images/slider-4.webp', 'imageAltEn' => 'Campus collaboration area', 'imageAltAr' => 'مساحة تعاون داخل الحرم الجامعي', 'titleEn' => 'Dedicated to Your Success', 'titleAr' => 'ملتزمون بنجاحك', 'summaryEn' => 'Beyond the classroom, we ensure that every student has the tools and support needed to thrive. Our campus services are designed with accessibility and efficiency in mind.', 'summaryAr' => 'ما بعد قاعات الدراسة، نوفر لكل طالب الموارد والإرشاد اللازمين للنجاح. خدمات الحرم الجامعي لدينا مصممة لسهولة الوصول والكفاءة.', 'badges' => [['labelEn' => 'Quality Assured', 'labelAr' => 'دعم معتمد للجودة', 'icon' => '/images/icon-check-circle-outline.svg'], ['labelEn' => 'Student Support', 'labelAr' => 'سلامة الطالب أولاً', 'icon' => '/images/student.svg']]],
            'seoDescriptionEn' => 'Explore SPU campus services including transport, health services, IT support, and student care resources.',
            'seoDescriptionAr' => 'استكشف خدمات الحرم الجامعي في SPU بما في ذلك النقل والصحة والدعم التقني وموارد رعاية الطلاب.',
        ];
    }

    /** @return array<string, mixed> */
    private function careerDevelopmentPayload(): array
    {
        return [
            'type' => 'career-development',
            'hero' => ['image' => '/images/career-development-hero.webp', 'titleEn' => 'Career Development', 'titleAr' => 'التطوير المهني', 'breadcrumbs' => $this->breadcrumbs('Career Development', 'التطوير المهني', '/campus-life/career-development'), 'panel' => ['titleEn' => 'Empowering Your Future', 'titleAr' => 'تمكين مستقبلك المهني', 'summaryEn' => 'The Career Growth Hub serves as the vital connector between academic achievement and professional success.', 'summaryAr' => 'يعمل مركز النمو المهني كحلقة وصل أساسية بين الإنجاز الأكاديمي والنجاح المهني.']],
            'services' => ['titleEn' => 'Career Services', 'titleAr' => 'خدمات التطوير المهني', 'items' => [
                ['id' => 'career-guidance', 'icon' => '/images/icon-globe-outline.svg', 'titleEn' => 'Career Guidance', 'titleAr' => 'الإرشاد المهني', 'summaryEn' => 'One-on-one support for students preparing career direction and professional profiles.', 'summaryAr' => 'دعم فردي للطلاب في تحديد المسار المهني وبناء الملف الاحترافي.', 'linkEn' => 'Get Career Guidance', 'linkAr' => 'احصل على إرشاد مهني', 'href' => '/campus-life/career-development#career-guidance'],
                ['id' => 'cv-workshops', 'icon' => '/images/icon-award-outline.svg', 'titleEn' => 'CV Workshops', 'titleAr' => 'ورش السيرة الذاتية', 'summaryEn' => 'Interactive workshops on CV structure, interview readiness, and job search confidence.', 'summaryAr' => 'ورش تفاعلية حول بنية السيرة الذاتية والاستعداد للمقابلات والبحث عن العمل.', 'linkEn' => 'View Workshops', 'linkAr' => 'عرض الورش', 'href' => '/campus-life/career-development#cv-workshops'],
                ['id' => 'internship-listings', 'icon' => '/images/icon-calendar-outline.svg', 'titleEn' => 'Internship Listings', 'titleAr' => 'فرص التدريب', 'summaryEn' => 'Seasonal internship listings with university partners across academic disciplines.', 'summaryAr' => 'فرص تدريب موسمية مع شركاء الجامعة في مختلف الاختصاصات.', 'linkEn' => 'View Internships', 'linkAr' => 'عرض فرص التدريب', 'href' => '/campus-life/career-development#internship-listings'],
                ['id' => 'job-board', 'icon' => '/images/icon-file-outline.svg', 'titleEn' => 'Job Board', 'titleAr' => 'لوحة الوظائف', 'summaryEn' => 'Access full-time job opportunities for recent graduates through verified employer outreach.', 'summaryAr' => 'الوصول إلى فرص عمل بدوام كامل للخريجين عبر أصحاب عمل موثوقين.', 'linkEn' => 'Open Job Board', 'linkAr' => 'فتح لوحة الوظائف', 'href' => '/campus-life/career-development#job-board'],
                ['id' => 'employer-partners', 'icon' => '/images/icon-handshake-outline.svg', 'titleEn' => 'Employer Partners', 'titleAr' => 'شركاء التوظيف', 'summaryEn' => 'Discover cooperating organizations and employer resources connected with Syrian Private University.', 'summaryAr' => 'اكتشف المؤسسات المتعاونة وموارد أصحاب العمل المرتبطة بالجامعة السورية الخاصة.', 'linkEn' => 'View Partners', 'linkAr' => 'عرض الشركاء', 'href' => '/campus-life/career-development#employer-partners'],
                ['id' => 'career-events', 'icon' => '/images/icon-sitemap-outline.svg', 'titleEn' => 'Career Events', 'titleAr' => 'فعاليات التوظيف', 'summaryEn' => 'Explore upcoming career fairs, employer information sessions, and specialized hiring events.', 'summaryAr' => 'استكشف معارض التوظيف والجلسات التعريفية وفعاليات التوظيف المتخصصة.', 'linkEn' => 'View Events', 'linkAr' => 'عرض الفعاليات', 'href' => '/campus-life/career-development#career-events'],
            ]],
            'success' => ['image' => '/images/career-development-success.webp', 'imageAltEn' => 'Syrian Private University campus building', 'imageAltAr' => 'مبنى في حرم الجامعة السورية الخاصة', 'titleEn' => 'Dedicated to Your Success', 'titleAr' => 'ملتزمون بنجاحك', 'summaryEn' => 'Beyond the classroom, we ensure that every student has the tools and support systems needed to thrive. Our campus services are designed with accessibility and efficiency in mind.', 'summaryAr' => 'خارج القاعات الدراسية، نضمن حصول كل طالب على الأدوات وأنظمة الدعم اللازمة للنجاح. صممت خدماتنا الجامعية لتكون سهلة الوصول وفعالة.', 'badges' => [['labelEn' => 'Quality Assured', 'labelAr' => 'جودة موثوقة', 'icon' => '/images/icon-check-circle-outline.svg'], ['labelEn' => 'Pro Support', 'labelAr' => 'دعم مهني', 'icon' => '/images/icon-user-graduate-outline.svg']]],
            'seoDescriptionEn' => 'Explore SPU career guidance, CV workshops, internship listings, job opportunities, employer partnerships, and career events.',
            'seoDescriptionAr' => 'استكشف الإرشاد المهني وورش السيرة الذاتية وفرص التدريب والعمل وشراكات التوظيف في SPU.',
        ];
    }

    /** @return array<string, mixed> */
    private function clubsActivitiesPayload(): array
    {
        return [
            'type' => 'clubs-activities',
            'hero' => ['image' => '/images/admissions-hero-campus.webp', 'titleEn' => 'Student Clubs & Activities', 'titleAr' => 'الأندية والأنشطة الطلابية', 'breadcrumbs' => $this->breadcrumbs('Clubs & Activities', 'الأندية والأنشطة', '/campus-life/clubs-activities')],
            'clubs' => ['titleEn' => 'Student Clubs', 'titleAr' => 'الأندية الطلابية', 'directoryLabelEn' => 'View Directory', 'directoryLabelAr' => 'عرض الدليل', 'directoryUrl' => '/campus-life/clubs-activities#clubs', 'detailsLabelEn' => 'View Details', 'detailsLabelAr' => 'عرض التفاصيل', 'items' => [
                ['id' => 'ai-technology', 'tagEn' => 'Technology', 'tagAr' => 'تقنية', 'titleEn' => 'AI & Technology Club', 'titleAr' => 'نادي الذكاء الاصطناعي والتكنولوجيا', 'summaryEn' => 'Exploring artificial intelligence and public speaking skills through weekly regional and national competitions.', 'summaryAr' => 'استكشاف الذكاء الاصطناعي ومهارات العرض من خلال لقاءات أسبوعية ومشاركات محلية ووطنية.', 'image' => '/images/campus-feature-01.webp', 'href' => '/campus-life/clubs-activities#ai-technology'],
                ['id' => 'medical-students', 'tagEn' => 'Health', 'tagAr' => 'صحة', 'titleEn' => 'Medical Students Club', 'titleAr' => 'نادي طلاب الطب', 'summaryEn' => 'Connecting students with local health initiatives through sustained volunteer partnerships and community action.', 'summaryAr' => 'ربط الطلاب بالمبادرات الصحية المحلية عبر شراكات تطوعية مستمرة وعمل مجتمعي.', 'image' => '/images/campus-clubs.webp', 'href' => '/campus-life/clubs-activities#medical-students'],
                ['id' => 'business-entrepreneurship', 'tagEn' => 'Business', 'tagAr' => 'أعمال', 'titleEn' => 'Business & Entrepreneurship', 'titleAr' => 'نادي الأعمال وريادة الأعمال', 'summaryEn' => 'An open space for students of all levels to join, perform at campus events, and appreciate creative culture.', 'summaryAr' => 'مساحة مفتوحة للطلاب للمشاركة في الفعاليات الجامعية وتطوير ثقافة المبادرة والإبداع.', 'image' => '/images/admissions-hero-campus.webp', 'href' => '/campus-life/clubs-activities#business-entrepreneurship'],
            ]],
            'activities' => ['titleEn' => 'Upcoming Activities', 'titleAr' => 'الأنشطة القادمة', 'feature' => ['badgeEn' => 'Featured Achievement', 'badgeAr' => 'إنجاز مميز', 'titleEn' => 'Autumn Club Fair & Involvement Week', 'titleAr' => 'معرض أندية الخريف وأسبوع المشاركة', 'summaryEn' => 'Kick off the new quarter by meeting representatives from over 50 student organizations. Free food, live music, and opportunities to connect on the main quad all week long.', 'summaryAr' => 'ابدأ الفصل الجديد بالتعرف إلى ممثلي الأندية والمنظمات الطلابية، مع أنشطة تواصل وفرص مشاركة طوال الأسبوع.', 'image' => '/images/dsc-1075.webp', 'href' => '/campus-life/clubs-activities#autumn-club-fair'], 'announcementLabelEn' => 'View All Announcements', 'announcementLabelAr' => 'عرض جميع الإعلانات', 'announcementUrl' => '/news', 'items' => [
                ['id' => 'tech-showcase', 'dateEn' => 'Oct 24-26 2024', 'dateAr' => '24-26 تشرين الأول 2024', 'titleEn' => 'Tech Innovation Showcase', 'titleAr' => 'معرض الابتكار التقني', 'summaryEn' => 'Computer Science club presents end-of-year projects in the library atrium.', 'summaryAr' => 'يعرض نادي علوم الحاسوب مشاريع نهاية العام في بهو المكتبة.', 'image' => '/images/healthcare-main.webp', 'href' => '/news#tech-showcase'],
                ['id' => 'charity-run', 'dateEn' => 'Oct 30 2024', 'dateAr' => '30 تشرين الأول 2024', 'titleEn' => 'Annual Falcon 5K Charity Run', 'titleAr' => 'سباق فالكون الخيري السنوي', 'summaryEn' => 'Join the Athletics board to raise funds for local healthcare access.', 'summaryAr' => 'شارك مع المجلس الرياضي لدعم مبادرات الرعاية الصحية المحلية.', 'image' => '/images/campus-feature-01.webp', 'href' => '/news#charity-run'],
                ['id' => 'charity-run-final', 'dateEn' => 'Oct 30 2024', 'dateAr' => '30 تشرين الأول 2024', 'titleEn' => 'Annual Falcon 5K Charity Run', 'titleAr' => 'سباق فالكون الخيري السنوي', 'summaryEn' => 'Join the Athletics board to raise funds for local healthcare food banks.', 'summaryAr' => 'شارك مع المجلس الرياضي لدعم بنوك الغذاء والرعاية الصحية المحلية.', 'image' => '/images/campus-feature-01.webp', 'href' => '/news#charity-run-final'],
            ]],
            'seoDescriptionEn' => 'Explore SPU student clubs, upcoming activities, announcements, and opportunities to join campus life.',
            'seoDescriptionAr' => 'استكشف الأندية والأنشطة الطلابية والإعلانات وفرص المشاركة في الحياة الجامعية.',
        ];
    }

    /** @return array<string, mixed> */
    private function transportPayload(): array
    {
        return [
            'type' => 'transport',
            'hero' => ['image' => '/images/admissions-hero-campus.webp', 'imageAltEn' => 'Syrian Private University campus buildings and walkways', 'imageAltAr' => 'مباني وممرات الحرم الجامعي في الجامعة السورية الخاصة', 'titleEn' => 'Transport Services', 'titleAr' => 'خدمات النقل', 'breadcrumbs' => $this->breadcrumbs('Transport Services', 'خدمات النقل', '/campus-life/transport')],
            'overview' => ['titleEn' => 'Student Transport Services', 'titleAr' => 'خدمات النقل الطلابي'],
            'cards' => [
                ['titleEn' => 'Schedule', 'titleAr' => 'الجدول الزمني', 'descriptionEn' => 'Practical campus routes aligned with academic class timings.', 'descriptionAr' => 'مسارات عملية للحرم الجامعي متوافقة مع مواعيد المحاضرات الأكاديمية.', 'ctaEn' => 'Get Schedule', 'ctaAr' => 'عرض الجدول', 'href' => '/campus-life/transport#schedule', 'icon' => '/images/time.svg'],
                ['titleEn' => 'Routes', 'titleAr' => 'المسارات', 'descriptionEn' => 'Extensive coverage across major districts and central pickup points.', 'descriptionAr' => 'تغطية واسعة للأحياء الرئيسية ونقاط التجمع المركزية.', 'ctaEn' => 'Learn more', 'ctaAr' => 'اعرف المزيد', 'href' => '/campus-life/transport#routes', 'icon' => '/images/icon-map-outline.svg'],
                ['titleEn' => 'Registration', 'titleAr' => 'التسجيل', 'descriptionEn' => 'Simple online process to secure your seat for the semester.', 'descriptionAr' => 'عملية إلكترونية بسيطة لحجز مقعدك خلال الفصل الدراسي.', 'ctaEn' => 'Register now', 'ctaAr' => 'سجل الآن', 'href' => '/e-services', 'icon' => '/images/icon-user-graduate-outline.svg'],
                ['titleEn' => 'Fees', 'titleAr' => 'الرسوم', 'descriptionEn' => 'Clear plans and installment details through your student portal.', 'descriptionAr' => 'خطط ورسوم واضحة مع تفاصيل التقسيط عبر بوابة الطالب.', 'ctaEn' => 'View details', 'ctaAr' => 'عرض التفاصيل', 'href' => '/campus-life/transport#fees', 'icon' => '/images/icon-file-outline.svg'],
            ],
            'success' => ['image' => '/images/campus-transport.webp', 'imageAltEn' => 'University transport bus at a campus stop', 'imageAltAr' => 'حافلة نقل جامعية عند موقف في الحرم الجامعي', 'titleEn' => 'Dedicated to Your Success', 'titleAr' => 'ملتزمون بنجاحك', 'descriptionEn' => 'Beyond the classroom, we ensure that every student has the tools and support systems needed to thrive. Our campus services are designed with accessibility and efficiency in mind.', 'descriptionAr' => 'خارج القاعة الصفية، نضمن حصول كل طالب على أدوات وأنظمة الدعم اللازمة للتفوق. صممت خدمات الحرم الجامعي مع مراعاة سهولة الوصول والكفاءة.', 'links' => [['labelEn' => 'Quality Assured', 'labelAr' => 'جودة موثوقة', 'icon' => '/images/icon-check-circle-outline.svg'], ['labelEn' => 'Portal Support', 'labelAr' => 'دعم البوابة', 'icon' => '/images/icon-users-outline.svg']]],
            'seoDescriptionEn' => 'Find SPU student transport schedules, routes, registration guidance, fees, and portal support.',
            'seoDescriptionAr' => 'اطلع على جداول ومسارات النقل الطلابي والتسجيل والرسوم ودعم البوابة في SPU.',
        ];
    }

    /** @return array<string, mixed> */
    private function dentalPayload(): array
    {
        return $this->clinicalPayload('dental');
    }

    /** @return array<string, mixed> */
    private function hospitalPayload(): array
    {
        return $this->clinicalPayload('hospital');
    }

    /** @return array<string, mixed> */
    private function clinicalPayload(string $kind): array
    {
        $isDental = $kind === 'dental';
        $schedule = $this->weeklySchedule();
        $today = $this->todaySchedule($schedule);

        if ($isDental) {
            return [
                'type' => 'dental',
                'today' => $today,
                'hero' => ['image' => '/images/dental-place.JPG', 'titleEn' => 'SPU Dental Clinics', 'titleAr' => 'عيادات SPU لطب الأسنان', 'summaryEn' => 'Providing state-of-the-art dental care to the community while serving as a premier training facility for the next generation of leading dental professionals.', 'summaryAr' => 'تقديم رعاية أسنان متطورة للمجتمع مع خدمة كمنشأة تدريبية متميزة للأجيال القادمة من أطباء الأسن.', 'ctaEn' => 'Get Directions', 'ctaAr' => 'الحصول على الاتجاهات', 'ctaUrl' => 'https://www.google.com/maps?q=Syrian+Private+University', 'breadcrumbs' => $this->breadcrumbs('Dental Clinic', 'عيادة الأسنان', '/campus-life/dental')],
                'sectionHeader' => ['titleEn' => 'Dental Services', 'titleAr' => 'خدمات عيادة الأسنان'],
                'services' => [
                    ['titleEn' => 'General Dentistry', 'titleAr' => 'طب الأسنان العام', 'descriptionEn' => 'Comprehensive oral health care including routine cleanings, fillings, root canals, and preventive care. Focused on maintaining overall dental health for all ages.', 'descriptionAr' => 'رعاية صحية فموية شاملة تشمل التنظيف الروتيني والحشوات وقنوات الجذر والصيانة الوقائية لجميع الأعمار.', 'icon' => '/images/icons/dental-general.webp'],
                    ['titleEn' => 'Orthodontics', 'titleAr' => 'تقويم الأسنان', 'descriptionEn' => 'Alignment and correction of teeth and jaws using modern braces and clear aligner technologies.', 'descriptionAr' => 'محاذاة وتصحيح الأسنان والفك باستخدام تقنيات التقويم الحديثة والأجهزة الشفافة.', 'icon' => '/images/icons/dental-ortho.webp'],
                    ['titleEn' => 'Oral Surgery', 'titleAr' => 'جراحة الفم', 'descriptionEn' => 'Expert surgical procedures including extractions, wisdom teeth removal, and minor reconstructive surgeries.', 'descriptionAr' => 'إجراءات جراحية متخصصة تشمل قلع الأسنان وخلع ضروس العقل وعلاجات إعادة البناء الطفيفة.', 'icon' => '/images/icons/dental-surgery.webp'],
                    ['titleEn' => 'Periodontics', 'titleAr' => 'أمراض اللثة', 'descriptionEn' => 'Specialized care for the gums and supporting structures of teeth. Treatment of gum disease and placement of dental implants to restore function and aesthetics.', 'descriptionAr' => 'رعاية متخصصة للثة والهياكل الداعمة، بما في ذلك علاج أمراض اللثة وصيانة الزرعات.', 'icon' => '/images/icons/dental-perio.webp'],
                ],
                'schedule' => $schedule,
                'scheduleSection' => ['titleEn' => 'Weekly Schedule', 'titleAr' => 'ساعات العمل الأسبوعية', 'statusEn' => 'Open Now', 'statusAr' => 'مفتوح الآن', 'statusClosedEn' => 'Closed Now', 'statusClosedAr' => 'مغلق الآن'],
                'scheduleDetailsEn' => 'Regular outpatient services and clinics are currently operating.',
                'scheduleDetailsAr' => 'الاستشارات العادية ودعم الطوارئ متاحة خلال هذه الساعات.',
                'seoDescriptionEn' => 'Explore the SPU Dental Clinic, providing modern dental services and hands-on clinical training.',
                'seoDescriptionAr' => 'استكشف عيادات الأسنان في SPU وخدماتها الحديثة والتدريب السريري العملي.',
            ];
        }

        return [
            'type' => 'hospital',
            'today' => $today,
            'hero' => ['image' => '/images/campus-hospital.webp', 'titleEn' => 'University Hospital', 'titleAr' => 'المستشفى الجامعي', 'summaryEn' => 'Medical departments, healthcare services, appointments, insurance support, and emergency contact.', 'summaryAr' => 'الأقسام الطبية، خدمات الرعاية الصحية، المواعيد، دعم التأمين، والاتصال في حالات الطوارئ.', 'ctaEn' => 'Get Directions', 'ctaAr' => 'الحصول على الاتجاهات', 'ctaUrl' => 'https://www.google.com/maps?q=Syrian+Private+University+Hospital', 'breadcrumbs' => $this->breadcrumbs('University Hospital', 'المستشفى الجامعي', '/campus-life/hospital')],
            'sectionHeader' => ['titleEn' => 'Medical Departments', 'titleAr' => 'الأقسام الطبية'],
            'departments' => [
                ['titleEn' => 'Cardiology', 'titleAr' => 'أمراض القلب', 'descriptionEn' => 'Comprehensive heart care, diagnostics, and advanced treatment options.', 'descriptionAr' => 'رعاية قلب شاملة، تشخيصات، وخيارات علاجية متقدمة.', 'icon' => '/images/icons/cardiology.svg'],
                ['titleEn' => 'Neurology', 'titleAr' => 'أمراض الأعصاب', 'descriptionEn' => 'Expert diagnosis and treatment for neurological disorders and brain health.', 'descriptionAr' => 'تشخيص وعلاج متخصص للاضطرابات العصبية وصحة الدماغ.', 'icon' => '/images/icons/neurology.svg'],
                ['titleEn' => 'Pediatrics', 'titleAr' => 'طب الأطفال', 'descriptionEn' => 'Specialized healthcare services for infants, children, and adolescents.', 'descriptionAr' => 'خدمات رعاية صحية متخصصة للرضع والأطفال والمراهقين.', 'icon' => '/images/icons/pediatrics.svg'],
                ['titleEn' => 'Orthopedics', 'titleAr' => 'جراحة العظام', 'descriptionEn' => 'Comprehensive care for bone, joint, and muscle conditions and injuries.', 'descriptionAr' => 'رعاية شاملة لحالات وإصابات العظام والمفاصل والعضلات.', 'icon' => '/images/icons/orthopedics.svg'],
            ],
            'schedule' => $schedule,
            'scheduleSection' => ['titleEn' => 'Weekly Schedule', 'titleAr' => 'ساعات العمل الأسبوعية', 'statusEn' => 'Open Now', 'statusAr' => 'مفتوح الآن', 'statusClosedEn' => 'Closed Now', 'statusClosedAr' => 'مغلق الآن'],
            'scheduleDetailsEn' => 'Regular outpatient services and clinics are currently operating.',
            'scheduleDetailsAr' => 'العيادات الخارجية والخدمات العادية تعمل حالياً.',
            'emergency' => ['labelEn' => 'Emergency Support', 'labelAr' => 'دعم الطوارئ', 'statusEn' => 'AVAILABLE 24/7', 'statusAr' => 'متاح 24/7', 'hotlineLabelEn' => 'Emergency Hotline', 'hotlineLabelAr' => 'خط الطوارئ الساخن', 'phone' => '+963 11 123 4567', 'callCtaEn' => 'Call Now', 'callCtaAr' => 'اتصل الآن', 'directionsCtaEn' => 'Get Directions', 'directionsCtaAr' => 'الحصول على الاتجاهات', 'icon' => '/images/icons/univ-icon.png'],
            'seoDescriptionEn' => 'Explore the SPU University Hospital, offering specialized medical services and emergency care.',
            'seoDescriptionAr' => 'استكشف المستشفى الجامعي في SPU وخدماته الطبية المتخصصة ورعاية الطوارئ.',
        ];
    }

    /** @return array<string, mixed> */
    private function healthInsurancePayload(): array
    {
        return [
            'type' => 'health-insurance',
            'hero' => ['image' => '/images/campus-health.webp', 'titleEn' => 'Health & Insurance', 'titleAr' => 'الصحة والتأمين', 'summaryEn' => 'Student insurance support with clear coverage, step-by-step usage, and required claim documents.', 'summaryAr' => 'دعم التأمين الطلابي مع تغطية واضحة وخطوات استخدام مفصلة ووثائق المطالبة المطلوبة.', 'breadcrumbs' => $this->breadcrumbs('Health & Insurance', 'الصحة والتأمين', '/campus-life/health-insurance')],
            'sections' => [
                ['id' => 'mandatory-insurance', 'type' => 'highlight', 'titleEn' => 'Mandatory Insurance', 'titleAr' => 'تأمين إلزامي', 'descriptionEn' => 'Every enrolled student at SPU is required to carry comprehensive health insurance. This policy ensures access to approved medical services and protects students throughout their academic journey.', 'descriptionAr' => 'يُلزم كل طالب مسجل في SPU بحمل تأمين صحي شامل. تضمن هذه السياسة الوصول إلى الخدمات الطبية المعتمدة وتحمي الطلاب طوال رحلتهم الأكاديمية.'],
                ['id' => 'usage-steps', 'type' => 'steps', 'titleEn' => 'How to Use Your Insurance', 'titleAr' => 'كيفية استخدام التأمين', 'items' => [['number' => '01', 'titleEn' => 'Carry Your ID', 'titleAr' => 'احمل هويتك', 'descEn' => 'Always have your valid SPU student ID and your insurance card with you.', 'descAr' => 'احمل دائماً بطاقة الطالب في SPU وبطاقة التأمين.'], ['number' => '02', 'titleEn' => 'Present at Reception', 'titleAr' => 'قدّمها عند الاستقبال', 'descEn' => 'Present both cards to the hospital or clinic reception before consultation.', 'descAr' => 'قدّم البطاقتين عند استقبال المستشفى أو العيادة قبل المعاينة.'], ['number' => '03', 'titleEn' => 'Receive Service', 'titleAr' => 'احصل على الخدمة', 'descEn' => 'Use approved departments and ask staff for your insurance-eligible options.', 'descAr' => 'استخدم الأقسام المعتمدة واسأل الموظفين عن الخيارات المشمولة بتأمينك.'], ['number' => '04', 'titleEn' => 'Keep Your Records', 'titleAr' => 'احتفظ بالسجلات', 'descEn' => 'Keep prescriptions and invoices for follow-up, reimbursement, or claims.', 'descAr' => 'احتفظ بالوصفات والفواتير للمتابعة أو التعويض أو المطالبات.']]],
                ['id' => 'coverage', 'type' => 'cards', 'titleEn' => 'What is Covered', 'titleAr' => 'ما الذي يشمله التأمين', 'items' => [['titleEn' => 'Emergency Care', 'titleAr' => 'رعاية الطوارئ', 'descEn' => '24/7 emergency access and urgent medical interventions.', 'descAr' => 'خدمة طوارئ على مدار الساعة وتدخلات طبية عاجلة.', 'icon' => '/images/icons/hospital.svg'], ['titleEn' => 'Consultations', 'titleAr' => 'الاستشارات', 'descEn' => 'Regular visits with faculty-approved doctors and specialists.', 'descAr' => 'زيارات منتظمة مع أطباء واختصاصيين معتمدين من الكلية.', 'icon' => '/images/icons/dept.svg'], ['titleEn' => 'Diagnostics', 'titleAr' => 'التحاليل والتشخيص', 'descEn' => 'Basic laboratory tests, X-rays, and required medical imaging.', 'descAr' => 'تحاليل مخبرية أساسية وصور أشعة وتصوير طبي مطلوب.', 'icon' => '/images/icons/lab.svg'], ['titleEn' => 'Medications', 'titleAr' => 'الأدوية', 'descEn' => 'Selected prescriptions based on policy limits and approved lists.', 'descAr' => 'وصفات دوائية محددة وفق حدود البوليصة والقوائم المعتمدة.', 'icon' => '/images/icons/file.svg']]],
                ['id' => 'required-documents', 'type' => 'documents', 'titleEn' => 'Required Documents', 'titleAr' => 'الوثائق المطلوبة', 'listEn' => ['Completed claim form (available at student portal).', 'Original, itemized medical invoices and receipts.', 'Copy of medical prescription or doctor’s referral.', 'Diagnostic report copies (for tests/scans).'], 'listAr' => ['نموذج مطالبة مكتمل (متاح في بوابة الطالب).', 'فواتير وإيصالات طبية أصلية ومفصلة.', 'نسخة من الوصفة الطبية أو تحويل الطبيب.', 'نسخ تقارير التشخيص (للفحوصات/الصور).'], 'support' => ['titleEn' => 'Insurance Support', 'titleAr' => 'دعم التأمين', 'locationEn' => 'Main Campus · Student Affairs Floor', 'locationAr' => 'الحرم الجامعي الرئيسي · طابق شؤون الطلاب', 'locationIcon' => '/images/icon-map-outline.svg', 'phone' => '+963 11 213 3000', 'phoneIcon' => '/images/icon-phone-outline.svg', 'email' => 'insurance@spu.edu.sy', 'emailIcon' => '/images/icon-envelope-outline.svg']],
            ],
            'seoDescriptionEn' => 'Student health insurance details, coverage, required documents, and support contacts at SPU.',
            'seoDescriptionAr' => 'تفاصيل التأمين الصحي الطلابي والتغطية والوثائق وجهات الدعم في SPU.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function weeklySchedule(): array
    {
        return [
            ['dayEn' => 'Saturday', 'dayAr' => 'السبت', 'timeEn' => '8:00 AM - 4:00 PM', 'timeAr' => '8:00 صباحاً - 4:00 مساءً', 'isEmergency' => false],
            ['dayEn' => 'Sunday', 'dayAr' => 'الأحد', 'timeEn' => '8:00 AM - 4:00 PM', 'timeAr' => '8:00 صباحاً - 4:00 مساءً', 'isEmergency' => false],
            ['dayEn' => 'Monday', 'dayAr' => 'الاثنين', 'timeEn' => '8:00 AM - 4:00 PM', 'timeAr' => '8:00 صباحاً - 4:00 مساءً', 'isEmergency' => false],
            ['dayEn' => 'Tuesday', 'dayAr' => 'الثلاثاء', 'timeEn' => '8:00 AM - 4:00 PM', 'timeAr' => '8:00 صباحاً - 4:00 مساءً', 'isEmergency' => false],
            ['dayEn' => 'Wednesday', 'dayAr' => 'الأربعاء', 'timeEn' => 'Emergency Only', 'timeAr' => 'حالات الطوارئ فقط', 'isEmergency' => true],
            ['dayEn' => 'Thursday', 'dayAr' => 'الخميس', 'timeEn' => 'Emergency Only', 'timeAr' => 'حالات الطوارئ فقط', 'isEmergency' => true],
            ['dayEn' => 'Friday', 'dayAr' => 'الجمعة', 'timeEn' => 'Emergency Only', 'timeAr' => 'حالات الطوارئ فقط', 'isEmergency' => true],
        ];
    }

    /** @param array<int, array<string, mixed>> $schedule @return array<string, mixed> */
    private function todaySchedule(array $schedule): array
    {
        $weekdayToScheduleIndex = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];
        $index = $weekdayToScheduleIndex[(int) date('w')] ?? 0;

        return $schedule[$index] ?? $schedule[0];
    }

    /** @return array<int, array<string, string>> */
    private function breadcrumbs(string $currentEn, string $currentAr, string $currentHref): array
    {
        return [
            ['labelEn' => 'Home', 'labelAr' => 'الرئيسية', 'href' => '/'],
            ['labelEn' => 'Campus Life', 'labelAr' => 'الحياة الجامعية', 'href' => '/campus-life'],
            ['labelEn' => $currentEn, 'labelAr' => $currentAr, 'href' => $currentHref],
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localized(array $payload, string $locale): array
    {
        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        $opposite = $locale === 'ar' ? 'En' : 'Ar';
        $localized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->isList($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->localized($item, $locale) : $item, $value)
                    : $this->localized($value, $locale);
            }

            if (str_ends_with((string) $key, $suffix)) {
                $localized[substr((string) $key, 0, -2)] = $value;
                continue;
            }

            if (str_ends_with((string) $key, $opposite)) {
                continue;
            }

            $localized[$key] = $value;
        }

        return $localized;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        return array_is_list($value);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeUrls(array $payload, string $locale): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->isList($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalizeUrls($item, $locale) : $item, $value)
                    : $this->normalizeUrls($value, $locale);
                continue;
            }

            if (is_string($value) && in_array($key, ['href', 'url', 'primaryUrl', 'secondaryUrl', 'directoryUrl', 'announcementUrl', 'ctaUrl'], true)) {
                $payload[$key] = $this->normalizeUrl($value, $locale);
            }
        }

        return $payload;
    }

    private function normalizeUrl(string $url, string $locale): string
    {
        if ($url === '#' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return $url;
        }

        if (str_starts_with($url, '/images/') || $url === '/sitemap.xml') {
            return $url;
        }

        $url = str_replace(['/student-life.html', '/services.html'], ['/campus-life', '/e-services'], $url);
        $url = preg_replace('~\.html(?=($|#|\?))~', '', $url) ?? $url;

        if ($url === '/') {
            return '/'.$locale;
        }

        return str_starts_with($url, '/'.$locale.'/') || $url === '/'.$locale
            ? $url
            : '/'.$locale.$url;
    }
}
