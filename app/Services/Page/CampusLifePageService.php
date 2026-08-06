<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\DTOs\CampusLife\CampusLifeJobDTO;
use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;
use Carbon\CarbonImmutable;

final class CampusLifePageService implements CampusLifePageServiceInterface
{
    private const JOBS_PER_PAGE = 6;

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
    ) {}

    public function getLanding(string $locale): CampusLifePageDTO
    {
        $landing = $this->publishedLocalizedPayload('campus_life.landing', $locale)
            ?? $this->localized($this->landingPayload(), $locale);

        return $this->landingDto($locale, $this->safeLandingPayload($this->normalizeUrls($landing, $locale), $locale));
    }

    public function buildPreviewLanding(string $locale, array $landing): CampusLifePageDTO
    {
        return $this->landingDto($locale, $this->safeLandingPayload($this->normalizeUrls($landing, $locale), $locale));
    }

    /** @param array<string, mixed> $landing */
    private function landingDto(string $locale, array $landing): CampusLifePageDTO
    {
        $title = (string) ($landing['hero']['title'] ?? ($locale === 'ar' ? 'الحياة الجامعية' : 'Campus Life'));
        $summary = (string) ($landing['hero']['summary'] ?? ($locale === 'ar'
            ? 'اكتشف خدمات ومرافق وأنشطة الحياة الجامعية في الجامعة السورية الخاصة.'
            : 'Discover student services, facilities, and campus activities at Syrian Private University.'));
        $seo = is_array($landing['seo'] ?? null) ? $landing['seo'] : [];

        return new CampusLifePageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            landing: $landing,
            seoTitle: (string) ($seo['title'] ?? ($title.' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'Syrian Private University'))),
            seoDescription: (string) ($seo['description'] ?? $summary),
            seoImage: (string) ($seo['image'] ?? ($landing['hero']['image'] ?? '/images/logo-spu.png')),
        );
    }

    public function getEditablePayload(string $targetKey): array
    {
        if (! str_starts_with($targetKey, 'campus_life.') || $targetKey === 'campus_life.virtual_tour') {
            throw new \InvalidArgumentException('Unsupported campus life target.');
        }

        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);

        if (is_array($published['translations']['ar'] ?? null) && is_array($published['translations']['en'] ?? null)) {
            return [
                'translations' => [
                    'ar' => $published['translations']['ar'],
                    'en' => $published['translations']['en'],
                ],
            ];
        }

        if ($targetKey === 'campus_life.landing') {
            return [
                'translations' => [
                    'ar' => $this->normalizeUrls($this->localized($this->landingPayload(), 'ar'), 'ar'),
                    'en' => $this->normalizeUrls($this->localized($this->landingPayload(), 'en'), 'en'),
                ],
            ];
        }

        if ($targetKey === 'campus_life.jobs') {
            return [
                'translations' => [
                    'ar' => $this->normalizeUrls($this->localized($this->jobBoardPayload(), 'ar'), 'ar'),
                    'en' => $this->normalizeUrls($this->localized($this->jobBoardPayload(), 'en'), 'en'),
                ],
            ];
        }

        $slug = $this->slugFromTargetKey($targetKey);
        $payload = $slug !== null ? ($this->sectionPayloads()[$slug] ?? null) : null;

        if ($payload === null) {
            throw new \InvalidArgumentException('Unsupported campus life target.');
        }

        return [
            'translations' => [
                'ar' => $this->normalizeUrls($this->localized($payload, 'ar'), 'ar'),
                'en' => $this->normalizeUrls($this->localized($payload, 'en'), 'en'),
            ],
        ];
    }

    public function getSection(string $slug, string $locale): ?CampusLifeSectionDTO
    {
        $payload = $this->publishedLocalizedPayload('campus_life.'.$slug, $locale);

        if ($payload === null) {
            $fallback = $this->sectionPayloads()[$slug] ?? null;
            $payload = is_array($fallback) ? $this->localized($fallback, $locale) : null;
        }

        if ($payload === null) {
            return null;
        }

        return $this->sectionDto($slug, $locale, $this->normalizeUrls($payload, $locale));
    }

    public function getCareerJobBoard(string $locale, array $filters = []): CampusLifeSectionDTO
    {
        $payload = $this->publishedLocalizedPayload('campus_life.jobs', $locale)
            ?? $this->localized($this->jobBoardPayload(), $locale);

        return $this->jobBoardDto($locale, $payload, $filters, false);
    }

    public function getCareerJobDetail(string $slug, string $locale): ?CampusLifeSectionDTO
    {
        $payload = $this->publishedLocalizedPayload('campus_life.jobs', $locale)
            ?? $this->localized($this->jobBoardPayload(), $locale);
        $jobs = $this->publicJobs($payload);
        $job = $this->jobBySlug($jobs, $slug);

        if (! is_array($job)) {
            return null;
        }

        $payload['job'] = $job;
        $payload['relatedJobs'] = $this->relatedJobs($jobs, $job);
        unset($payload['jobs']);

        return $this->sectionDto('career-development/jobs/'.$slug, $locale, $this->normalizeUrls($payload, $locale));
    }

    public function getCareerJobApplication(string $locale, ?string $slug): ?CampusLifeSectionDTO
    {
        if ($slug === null || trim($slug) === '') {
            return null;
        }

        $job = $this->findOpenCareerJob($slug, $locale);

        if (! $job instanceof CampusLifeJobDTO || ! $job->applicationEligible) {
            return null;
        }

        $payload = $this->localized($this->jobApplicationPayload(), $locale);
        $payload['selectedJob'] = [
            'id' => $job->id,
            'slug' => $job->slug,
            'title' => $job->title,
            'postedDate' => $job->postedDate,
            'closeDate' => $job->closeDate,
        ];

        return $this->sectionDto('career-development/jobs/apply', $locale, $this->normalizeUrls($payload, $locale));
    }

    public function findOpenCareerJob(string $slug, string $locale): ?CampusLifeJobDTO
    {
        $payload = $this->publishedLocalizedPayload('campus_life.jobs', $locale)
            ?? $this->localized($this->jobBoardPayload(), $locale);
        $job = $this->jobBySlug($this->publicJobs($payload), $slug);

        if (! is_array($job)) {
            return null;
        }

        return new CampusLifeJobDTO(
            id: (string) ($job['id'] ?? ''),
            slug: (string) ($job['slug'] ?? ''),
            title: (string) ($job['title'] ?? ''),
            status: (string) ($job['status'] ?? ''),
            postedDate: (string) ($job['postedDate'] ?? ''),
            closeDate: is_string($job['closeDate'] ?? null) && $job['closeDate'] !== '' ? $job['closeDate'] : null,
            applicationEligible: (bool) ($job['applicationEligible'] ?? false),
        );
    }

    public function buildPreviewCareerJobs(string $locale, array $content, array $filters = []): CampusLifeSectionDTO
    {
        return $this->jobBoardDto($locale, $content, $filters, true);
    }

    public function buildPreviewCareerJob(string $locale, array $content, string $slug): ?CampusLifeSectionDTO
    {
        $jobs = $this->jobArrays($content['jobs'] ?? []);
        $job = $this->jobBySlug($jobs, $slug);

        if (! is_array($job)) {
            return null;
        }

        $content['job'] = $job;
        $content['relatedJobs'] = $this->relatedJobs($jobs, $job);
        unset($content['jobs']);

        return $this->sectionDto('career-development/jobs/'.$slug, $locale, $this->normalizeUrls($content, $locale));
    }

    public function buildPreviewSection(string $targetKey, string $locale, array $section): ?CampusLifeSectionDTO
    {
        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            return null;
        }

        return $this->sectionDto($slug, $locale, $this->normalizeUrls($section, $locale));
    }

    /** @param array<string, mixed> $section */
    private function sectionDto(string $slug, string $locale, array $section): CampusLifeSectionDTO
    {
        if (in_array($section['type'] ?? null, ['dental', 'hospital'], true) && is_array($section['schedule'] ?? null)) {
            $section['today'] = $this->todaySchedule(array_values(array_filter($section['schedule'], static fn (mixed $slot): bool => is_array($slot))));
        }

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

    private function slugFromTargetKey(string $targetKey): ?string
    {
        if (! str_starts_with($targetKey, 'campus_life.') || in_array($targetKey, ['campus_life.landing', 'campus_life.virtual_tour', 'campus_life.jobs'], true)) {
            return null;
        }

        return substr($targetKey, strlen('campus_life.'));
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
            'seo' => [
                'titleEn' => 'Campus Life | Syrian Private University',
                'titleAr' => 'الحياة الجامعية | الجامعة السورية الخاصة',
                'descriptionEn' => 'Discover student services, facilities, and campus activities at Syrian Private University.',
                'descriptionAr' => 'اكتشف خدمات ومرافق وأنشطة الحياة الجامعية في الجامعة السورية الخاصة.',
                'image' => '/images/admissions-hero-campus.webp',
            ],
            'intro' => [
                'titleEn' => 'Your Campus Life Journey',
                'titleAr' => 'رحلتك في الحياة الجامعية',
                'summaryEn' => 'A connected campus journey designed to support your academic advancement, personal wellbeing, and professional development from day one.',
                'summaryAr' => 'رحلة جامعية متصلة مصممة لدعم تقدمك الأكاديمي ورفاهيتك الشخصية وتطورك المهني من اليوم الأول.',
            ],
            'stats' => [],
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
            'portalGuidanceEn' => 'Verified student portal destinations will be published here after review. Contact Student Affairs for current access guidance.',
            'portalGuidanceAr' => 'ستُنشر روابط بوابات الطلاب الموثقة هنا بعد مراجعتها. يرجى التواصل مع شؤون الطلاب للحصول على إرشادات الوصول الحالية.',
            'portals' => [
                ['titleEn' => 'Contact Student Affairs', 'titleAr' => 'التواصل مع شؤون الطلاب', 'summaryEn' => 'Get direct guidance for support needs, schedules, and student services.', 'summaryAr' => 'الحصول على إرشاد مباشر لاحتياجات الدعم والجداول والخدمات الطلابية.', 'icon' => '/images/icon-phone-outline.svg', 'url' => '/contact#admissions-support'],
            ],
            'cta' => ['titleEn' => 'Ready to Begin Your Journey?', 'titleAr' => 'مستعد لبدء رحلتك؟', 'summaryEn' => 'Join thousands of students who chose SPU as their path to academic excellence and professional success.', 'summaryAr' => 'انضم إلى آلاف الطلاب الذين اختاروا SPU كطريقهم نحو التميز الأكاديمي والنجاح المهني.', 'primaryLabelEn' => 'Apply Now', 'primaryLabelAr' => 'قدّم الآن', 'primaryUrl' => '/admissions', 'secondaryLabelEn' => 'Contact Us', 'secondaryLabelAr' => 'تواصل معنا', 'secondaryUrl' => '/contact'],
        ];
    }

    /** @param array<string, mixed> $landing @return array<string, mixed> */
    private function safeLandingPayload(array $landing, string $locale): array
    {
        $landing['stats'] = array_values(array_filter(
            is_array($landing['stats'] ?? null) ? $landing['stats'] : [],
            static fn (mixed $stat): bool => is_array($stat) && ($stat['verified'] ?? false) === true,
        ));
        $landing['portals'] = array_values(array_filter(
            is_array($landing['portals'] ?? null) ? $landing['portals'] : [],
            fn (mixed $portal): bool => is_array($portal) && $this->isSafeLandingDestination($portal['url'] ?? null, $locale),
        ));

        return $landing;
    }

    private function isSafeLandingDestination(mixed $url, string $locale): bool
    {
        return is_string($url)
            && $url !== '#'
            && ! str_starts_with($url, '//')
            && preg_match('~^/'.preg_quote($locale, '~').'/(?:campus-life|e-services|admissions|contact|facilities|virtual-tour)(?:[/?#]|$)~', $url) === 1;
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
            'damascus-research-pub' => $this->simpleInfoPayload(
                type: 'damascus-research-pub',
                titleEn: 'Damascus Research Center Publications',
                titleAr: 'منشورات مركز دمشق للأبحاث والدراسات',
                overviewTitleEn: 'Research Publications & Studies',
                overviewTitleAr: 'المنشورات والدراسات البحثية',
                summaryEn: 'The Damascus Research Center for Studies and Research at SPU produces academic publications, studies, and research papers that contribute to the scientific community and support evidence-based decision-making.',
                summaryAr: 'ينتج مركز دمشق للأبحاث والدراسات في SPU منشورات أكاديمية ودراسات وأوراقاً بحثية تساهم في المجتمع العلمي وتدعم صنع القرار المبني على الأدلة.',
                items: [
                    ['titleEn' => 'Academic Journals', 'titleAr' => 'المجلات الأكاديمية', 'bodyEn' => 'Peer-reviewed journals published regularly covering medicine, engineering, pharmacy, dentistry, and business administration.', 'bodyAr' => 'مجلات محكمة تصدر بانتظام تغطي الطب والهندسة والصيدلة وطب الأسنان والعلوم الإدارية.'],
                    ['titleEn' => 'Research Papers', 'titleAr' => 'الأوراق البحثية', 'bodyEn' => 'Original research conducted by SPU faculty and researchers, indexed in academic databases and available through the university library.', 'bodyAr' => 'أبحاث أصلية يجريها أعضاء هيئة التدريس والباحثون في SPU، مفهرسة في قواعد البيانات الأكاديمية ومتاحة عبر مكتبة الجامعة.'],
                    ['titleEn' => 'Conference Proceedings', 'titleAr' => 'وقائع المؤتمرات', 'bodyEn' => 'Documentation of research presented at local and international conferences organized or hosted by SPU.', 'bodyAr' => 'توثيق للأبحاث المقدمة في المؤتمرات المحلية والدولية التي تنظمها أو تستضيفها SPU.'],
                    ['titleEn' => 'Specialized Studies', 'titleAr' => 'الدراسات المتخصصة', 'bodyEn' => 'In-depth studies on topics relevant to Syrian society and regional development priorities.', 'bodyAr' => 'دراسات معمقة حول مواضيع ذات صلة بالمجتمع السوري وأولويات التنمية الإقليمية.'],
                ],
            ),
            'rules-regulations' => $this->simpleInfoPayload(
                type: 'rules-regulations',
                titleEn: 'Rules & Regulations',
                titleAr: 'أنظمة وتعليمات',
                overviewTitleEn: 'University Regulations',
                overviewTitleAr: 'أنظمة الجامعة',
                summaryEn: 'SPU operates under rules and regulations that govern academic, administrative, and student conduct, ensuring a structured, fair, and transparent university environment.',
                summaryAr: 'تعمل الجامعة السورية الخاصة بموجب أنظمة وتعليمات تحكم السلوك الأكاديمي والإداري والطلابي، بما يضمن بيئة جامعية منظمة وعادلة وشفافة.',
                items: [
                    ['titleEn' => 'Academic Regulations', 'titleAr' => 'الأنظمة الأكاديمية', 'bodyEn' => 'Rules governing course registration, attendance, examinations, grading, progression, and graduation requirements across all faculties.', 'bodyAr' => 'قواعد تنظم تسجيل المساقات والحضور والامتحانات والتقييم والترقي ومتطلبات التخرج في جميع الكليات.'],
                    ['titleEn' => 'Student Conduct', 'titleAr' => 'سلوك الطلاب', 'bodyEn' => 'Standards of behavior expected from all students, including campus etiquette and use of university facilities.', 'bodyAr' => 'معايير السلوك المتوقعة من جميع الطلاب، بما في ذلك آداب الحرم الجامعي واستخدام مرافق الجامعة.'],
                    ['titleEn' => 'Administrative Procedures', 'titleAr' => 'الإجراءات الإدارية', 'bodyEn' => 'Policies related to enrollment, leave of absence, transfer, document requests, and other administrative processes.', 'bodyAr' => 'سياسات تتعلق بالتسجيل والإجازة الدراسية والتحويل وطلب الوثائق والعمليات الإدارية الأخرى.'],
                    ['titleEn' => 'Faculty & Staff Regulations', 'titleAr' => 'أنظمة أعضاء الهيئة التدريسية والموظفين', 'bodyEn' => 'Employment policies, academic freedom guidelines, workload expectations, and professional development opportunities.', 'bodyAr' => 'سياسات التوظيف وإرشادات الحرية الأكاديمية وتوقعات عبء العمل وفرص التطوير المهني.'],
                ],
            ),
            'general-rules' => $this->simpleInfoPayload(
                type: 'general-rules',
                titleEn: 'General Rules & Instructions',
                titleAr: 'قواعد وتعليمات عامة',
                overviewTitleEn: 'General Rules & Instructions',
                overviewTitleAr: 'قواعد وتعليمات عامة',
                summaryEn: 'This page outlines general rules and instructions that students must follow during enrollment at SPU to maintain a productive and respectful academic environment.',
                summaryAr: 'توضح هذه الصفحة القواعد والتعليمات العامة التي يجب على الطلاب اتباعها أثناء تسجيلهم في الجامعة السورية الخاصة للحفاظ على بيئة أكاديمية منتظمة ومحترمة.',
                items: [
                    ['titleEn' => 'Attendance', 'titleAr' => 'الحضور', 'bodyEn' => 'Students are required to attend lectures, tutorials, laboratory sessions, and clinical training as specified in each course syllabus.', 'bodyAr' => 'يطلب من الطلاب حضور المحاضرات والدروس العملية والجلسات المخبرية والتدريب السريري حسبما هو محدد في توصيف كل مساق.'],
                    ['titleEn' => 'Identification Cards', 'titleAr' => 'بطاقات التعريف', 'bodyEn' => 'All students must carry their university ID cards while on campus and present them upon request.', 'bodyAr' => 'يجب على جميع الطلاب حمل بطاقات التعريف الجامعية أثناء التواجد في الحرم الجامعي وإبرازها عند الطلب.'],
                    ['titleEn' => 'Use of Facilities', 'titleAr' => 'استخدام المرافق', 'bodyEn' => 'University facilities must be used responsibly and in accordance with posted guidelines.', 'bodyAr' => 'يجب استخدام مرافق الجامعة بمسؤولية ووفقاً للإرشادات المعلنة.'],
                    ['titleEn' => 'Academic Honesty', 'titleAr' => 'النزاهة الأكاديمية', 'bodyEn' => 'Plagiarism, cheating, and any form of academic dishonesty are prohibited and subject to disciplinary action.', 'bodyAr' => 'الانتحال والغش وأي شكل من أشكال عدم النزاهة الأكاديمية ممنوع ويخضع لإجراءات تأديبية.'],
                    ['titleEn' => 'Communication', 'titleAr' => 'التواصل', 'bodyEn' => 'Students must use official SPU email addresses for university-related communication and check them regularly.', 'bodyAr' => 'يجب على الطلاب استخدام البريد الإلكتروني الرسمي للجامعة في الاتصالات المتعلقة بالجامعة والتحقق منه بانتظام.'],
                ],
            ),
            'exam-instructions' => $this->simpleInfoPayload(
                type: 'exam-instructions',
                titleEn: 'Exam Instructions',
                titleAr: 'التعليمات الامتحانية',
                overviewTitleEn: 'Examination Rules & Instructions',
                overviewTitleAr: 'قواعد وتعليمات الامتحانات',
                summaryEn: 'The following examination instructions apply to all SPU students and help ensure a fair and orderly examination process.',
                summaryAr: 'تنطبق تعليمات الامتحان التالية على جميع طلاب الجامعة السورية الخاصة وتساعد في ضمان عملية امتحانية عادلة ومنظمة.',
                items: [
                    ['titleEn' => 'Arrival Time', 'titleAr' => 'وقت الحضور', 'bodyEn' => 'Students must arrive at least 15 minutes before the scheduled exam time. Late arrivals may not be admitted.', 'bodyAr' => 'يجب على الطلاب الحضور قبل 15 دقيقة على الأقل من موعد الامتحان المحدد. قد لا يسمح للمتأخرين بالدخول.'],
                    ['titleEn' => 'Identification', 'titleAr' => 'إثبات الهوية', 'bodyEn' => 'Students must present their valid university ID card before entering the examination hall.', 'bodyAr' => 'يجب على الطلاب إبراز بطاقة التعريف الجامعية الصالحة قبل دخول قاعة الامتحان.'],
                    ['titleEn' => 'Permitted Materials', 'titleAr' => 'المواد المسموح بها', 'bodyEn' => 'Only approved writing tools and faculty-approved calculators are permitted. Phones and electronic devices must be placed outside or in designated areas.', 'bodyAr' => 'يسمح فقط بأدوات الكتابة المعتمدة والآلات الحاسبة الموافق عليها من الكلية. يجب وضع الهواتف والأجهزة الإلكترونية خارج القاعة أو في الأماكن المخصصة.'],
                    ['titleEn' => 'Exam Conduct', 'titleAr' => 'السلوك الامتحاني', 'bodyEn' => 'Unauthorized communication or any form of cheating leads to immediate dismissal and disciplinary action.', 'bodyAr' => 'يؤدي التواصل غير المصرح به أو أي شكل من أشكال الغش إلى الإخراج الفوري وإجراءات تأديبية.'],
                    ['titleEn' => 'Submission', 'titleAr' => 'التسليم', 'bodyEn' => 'Students must submit the exam paper to the invigilator before leaving the hall.', 'bodyAr' => 'يجب على الطلاب تسليم ورقة الامتحان للمراقب قبل مغادرة القاعة.'],
                ],
            ),
            'exam-penalties' => $this->simpleInfoPayload(
                type: 'exam-penalties',
                titleEn: 'Exam Penalties',
                titleAr: 'العقوبات الامتحانية',
                overviewTitleEn: 'Penalties for Examination Violations',
                overviewTitleAr: 'العقوبات المترتبة على المخالفات الامتحانية',
                summaryEn: 'SPU enforces penalties for examination violations to maintain academic integrity and fairness. Severity depends on the nature and recurrence of the violation.',
                summaryAr: 'تفرض الجامعة السورية الخاصة عقوبات على المخالفات الامتحانية للحفاظ على النزاهة الأكاديمية والعدالة. تعتمد شدة العقوبة على طبيعة المخالفة وتكرارها.',
                items: [
                    ['titleEn' => 'First Violation', 'titleAr' => 'المخالفة الأولى', 'bodyEn' => 'Verbal warning and deduction of 25% of the exam mark for the course.', 'bodyAr' => 'إنذار شفهي وخصم 25% من علامة الامتحان للمساق.'],
                    ['titleEn' => 'Second Violation', 'titleAr' => 'المخالفة الثانية', 'bodyEn' => 'Final written warning, deduction of 50% of the exam mark, and referral to the Faculty Council.', 'bodyAr' => 'إنذار كتابي نهائي وخصم 50% من علامة الامتحان والإحالة إلى مجلس الكلية.'],
                    ['titleEn' => 'Cheating', 'titleAr' => 'الغش', 'bodyEn' => 'Immediate disqualification from the exam, a grade of zero for the course, and possible suspension.', 'bodyAr' => 'الحرمان الفوري من الامتحان ودرجة صفر في المساق مع إمكانية الفصل المؤقت.'],
                    ['titleEn' => 'Impersonation', 'titleAr' => 'انتحال الشخصية', 'bodyEn' => 'Immediate expulsion from the university for both parties involved in impersonation.', 'bodyAr' => 'الفصل الفوري من الجامعة لكل من الطرفين المتورطين في انتحال الشخصية.'],
                    ['titleEn' => 'Repeated Offenses', 'titleAr' => 'المخالفات المتكررة', 'bodyEn' => 'Accumulated violations may lead to suspension for one or more semesters or permanent dismissal.', 'bodyAr' => 'قد تؤدي المخالفات المتراكمة إلى الفصل لفصل دراسي واحد أو أكثر أو الفصل النهائي.'],
                ],
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function jobApplicationPayload(): array
    {
        return [
            'type' => 'job-application',
            'hero' => [
                'image' => '/images/uni-main-place.JPG',
                'titleEn' => 'Apply for Job',
                'titleAr' => 'التقديم على الوظيفة',
                'breadcrumbs' => $this->breadcrumbs('Apply for Job', 'التقديم على الوظيفة', '/campus-life/career-development/jobs/apply'),
            ],
            'info' => [
                'titleEn' => 'Application Information',
                'titleAr' => 'معلومات التقديم',
                'summaryEn' => 'Please fill in all required fields accurately. You will be able to review your data before final submission.',
                'summaryAr' => 'يرجى ملء جميع الحقول المطلوبة بدقة. ستتمكن من مراجعة بياناتك قبل الإرسال النهائي.',
                'selectedJobEn' => 'Selected job:',
                'selectedJobAr' => 'الوظيفة المختارة:',
            ],
            'seoDescriptionEn' => 'Submit a job application to Syrian Private University through the official career development form.',
            'seoDescriptionAr' => 'قدّم طلب توظيف إلى الجامعة السورية الخاصة عبر نموذج التطوير المهني الرسمي.',
        ];
    }

    /** @return array<string, mixed> */
    private function jobBoardPayload(): array
    {
        return [
            'type' => 'job-board',
            'hero' => [
                'titleEn' => 'Job Board',
                'titleAr' => 'لوحة الوظائف',
                'summaryEn' => 'Explore current openings across academic, administrative, technical, and support roles at Syrian Private University.',
                'summaryAr' => 'استكشف الفرص المتاحة حالياً في الأدوار الأكاديمية والإدارية والتقنية وخدمات الدعم في الجامعة السورية الخاصة.',
                'image' => '/images/career-development-hero.webp',
                'breadcrumbs' => $this->breadcrumbs('Job Board', 'لوحة الوظائف', '/campus-life/career-development/jobs'),
            ],
            'labels' => [
                'categoryEn' => 'Category', 'categoryAr' => 'الفئة',
                'typeEn' => 'Job Type', 'typeAr' => 'نوع الوظيفة',
                'searchEn' => 'Search by title, department, or keyword...', 'searchAr' => 'ابحث حسب العنوان أو القسم أو الكلمة المفتاحية...',
                'searchActionEn' => 'Search', 'searchActionAr' => 'بحث',
                'showingEn' => 'Showing', 'showingAr' => 'عرض',
                'positionsEn' => 'positions', 'positionsAr' => 'وظيفة',
                'ofEn' => 'of', 'ofAr' => 'من',
                'previousEn' => 'Previous', 'previousAr' => 'السابق',
                'nextEn' => 'Next', 'nextAr' => 'التالي',
                'resetEn' => 'Reset filters', 'resetAr' => 'إعادة ضبط المرشحات',
                'noResultsEn' => 'No jobs match your search.', 'noResultsAr' => 'لا توجد وظائف تطابق بحثك.',
                'learnMoreEn' => 'Learn More', 'learnMoreAr' => 'اعرف المزيد',
                'applyEn' => 'Apply Now', 'applyAr' => 'قدّم الآن',
                'applicationsClosedEn' => 'Applications unavailable', 'applicationsClosedAr' => 'التقديم غير متاح',
                'postedOnEn' => 'Posted on', 'postedOnAr' => 'نُشر بتاريخ',
                'closesOnEn' => 'Closes on', 'closesOnAr' => 'ينتهي التقديم بتاريخ',
                'statusEn' => 'Status', 'statusAr' => 'الحالة',
                'openStatusEn' => 'Open', 'openStatusAr' => 'مفتوحة',
                'closedStatusEn' => 'Closed', 'closedStatusAr' => 'مغلقة',
                'shareEn' => 'Share', 'shareAr' => 'مشاركة',
                'copyLinkEn' => 'Copy Link', 'copyLinkAr' => 'نسخ الرابط',
                'copiedEn' => 'Copied', 'copiedAr' => 'تم النسخ',
                'relatedEn' => 'Related jobs', 'relatedAr' => 'وظائف ذات صلة',
                'overviewEn' => 'Job Overview', 'overviewAr' => 'نظرة عامة على الوظيفة',
                'requirementsEn' => 'Requirements', 'requirementsAr' => 'المتطلبات',
                'responsibilitiesEn' => 'Key Responsibilities', 'responsibilitiesAr' => 'المسؤوليات الرئيسية',
                'benefitsEn' => 'What We Offer', 'benefitsAr' => 'ما نقدمه',
                'backEn' => 'Back to Job Board', 'backAr' => 'العودة إلى لوحة الوظائف',
            ],
            'categories' => [
                ['id' => 'all', 'labelEn' => 'All Categories', 'labelAr' => 'كل الفئات'],
                ['id' => 'academic', 'labelEn' => 'Academic', 'labelAr' => 'أكاديمي'],
                ['id' => 'administrative', 'labelEn' => 'Administrative', 'labelAr' => 'إداري'],
                ['id' => 'driver', 'labelEn' => 'Driver', 'labelAr' => 'سائق'],
                ['id' => 'technical', 'labelEn' => 'Technical', 'labelAr' => 'تقني'],
                ['id' => 'medical', 'labelEn' => 'Medical', 'labelAr' => 'طبي'],
            ],
            'types' => [
                ['id' => 'all', 'labelEn' => 'All Types', 'labelAr' => 'كل الأنواع'],
                ['id' => 'full-time', 'labelEn' => 'Full-time', 'labelAr' => 'دوام كامل'],
                ['id' => 'part-time', 'labelEn' => 'Part-time', 'labelAr' => 'دوام جزئي'],
                ['id' => 'contract', 'labelEn' => 'Contract', 'labelAr' => 'عقد'],
            ],
            'jobs' => [
                $this->job('lecturer-computer-science', 'academic', 'full-time', 'Lecturer in Computer Science', 'محاضر في علوم الحاسوب', 'Faculty of Artificial Intelligence', 'كلية الذكاء الاصطناعي', 'Deliver undergraduate courses in algorithms, data structures, and software engineering.', 'تدريس مواد جامعية في الخوارزميات وهياكل البيانات وهندسة البرمجيات.', '2026-06-20'),
                $this->job('research-assistant', 'academic', 'contract', 'Research Assistant', 'مساعد باحث', 'Scientific Research Directorate', 'مديرية البحث العلمي', 'Support faculty-led research projects in data collection, analysis, and publication preparation.', 'دعم مشاريع البحث التي يقودها أعضاء الهيئة التدريسية في جمع البيانات وتحليلها وإعداد المنشورات.', '2026-06-18'),
                $this->job('administrative-coordinator', 'administrative', 'full-time', 'Administrative Coordinator', 'منسق إداري', 'Central Administration', 'الإدارة المركزية', 'Coordinate schedules, meetings, and documentation across university administrative units.', 'تنسيق الجداول والاجتماعات والوثائق بين الوحدات الإدارية في الجامعة.', '2026-06-15'),
                $this->job('admissions-officer', 'administrative', 'full-time', 'Admissions Officer', 'موظف قبول وتسجيل', 'Admissions & Registration', 'قبول وتسجيل', 'Guide prospective students through the admissions process and maintain accurate records.', 'توجيه الطلاب المحتملين خلال عملية القبول والحفاظ على السجلات الدقيقة.', '2026-06-12'),
                $this->job('campus-bus-driver', 'driver', 'full-time', 'Campus Bus Driver', 'سائق حافلة الجامعة', 'Transport Services', 'خدمات النقل', 'Operate university shuttle buses safely along designated student and staff routes.', 'تشغيل حافلات النقل الجامعي بأمان على الطرق المخصصة للطلاب والموظفين.', '2026-06-10'),
                $this->job('it-support-specialist', 'technical', 'full-time', 'IT Support Specialist', 'أخصائي دعم تقنية المعلومات', 'IT Services Directorate', 'مديرية خدمات تقنية المعلومات', 'Provide hardware, software, and network support to faculty, staff, and computer labs.', 'تقديم الدعم للأجهزة والبرمجيات والشبكات لأعضاء الهيئة التدريسية والموظفين والمختبرات الحاسوبية.', '2026-06-08'),
                $this->job('laboratory-technician', 'technical', 'contract', 'Laboratory Technician', 'فني مختبر', 'Faculty of Dentistry', 'كلية طب الأسنان', 'Maintain dental lab equipment, prepare materials, and support clinical training sessions.', 'صيانة معدات مختبر الأسنان وإعداد المواد ودعم جلسات التدريب السريري.', '2026-06-05'),
                $this->job('dental-clinic-supervisor', 'medical', 'part-time', 'Dental Clinic Supervisor', 'مشرف العيادات السنية', 'University Dental Clinics', 'عيادات الجامعة السنية', 'Oversee daily clinic operations, patient scheduling, and quality of care in the dental clinics.', 'الإشراف على العمليات اليومية وجدولة المرضى وجودة الرعاية في العيادات السنية.', '2026-06-01'),
            ],
            'seoDescriptionEn' => 'Explore current job openings across academic, administrative, technical, and support roles at Syrian Private University.',
            'seoDescriptionAr' => 'استكشف فرص العمل الحالية في الجامعة السورية الخاصة.',
        ];
    }

    /** @return array<string, mixed> */
    private function job(string $slug, string $category, string $type, string $titleEn, string $titleAr, string $departmentEn, string $departmentAr, string $summaryEn, string $summaryAr, string $postedDate): array
    {
        $number = array_search($slug, [
            'lecturer-computer-science',
            'research-assistant',
            'administrative-coordinator',
            'admissions-officer',
            'campus-bus-driver',
            'it-support-specialist',
            'laboratory-technician',
            'dental-clinic-supervisor',
        ], true);

        return [
            'id' => 'job-'.str_pad((string) (($number === false ? 0 : $number) + 1), 3, '0', STR_PAD_LEFT),
            'slug' => $slug,
            'category' => $category,
            'type' => $type,
            'status' => 'open',
            'titleEn' => $titleEn,
            'titleAr' => $titleAr,
            'departmentEn' => $departmentEn,
            'departmentAr' => $departmentAr,
            'locationEn' => 'Damascus Campus',
            'locationAr' => 'حرم دمشق',
            'shortDescriptionEn' => $summaryEn,
            'shortDescriptionAr' => $summaryAr,
            'overviewEn' => [$summaryEn, 'This role supports SPU academic and service excellence through professional, student-centered work.'],
            'overviewAr' => [$summaryAr, 'يدعم هذا الدور تميز الجامعة الأكاديمي والخدمي من خلال عمل مهني يركز على الطلاب.'],
            'responsibilitiesEn' => ['Deliver assigned duties with accuracy and professionalism.', 'Coordinate with relevant university departments.', 'Maintain records, reports, and service quality standards.'],
            'responsibilitiesAr' => ['تنفيذ المهام بدقة ومهنية.', 'التنسيق مع الجهات الجامعية المعنية.', 'الحفاظ على السجلات والتقارير ومعايير جودة الخدمة.'],
            'requirementsEn' => ['Relevant academic or professional qualification.', 'Strong communication skills in Arabic and English.', 'Commitment to university policies and service standards.'],
            'requirementsAr' => ['مؤهل أكاديمي أو مهني مناسب.', 'مهارات تواصل قوية بالعربية والإنجليزية.', 'الالتزام بسياسات الجامعة ومعايير الخدمة.'],
            'benefitsEn' => ['Professional university environment.', 'Development and training opportunities.', 'Competitive package based on role and experience.'],
            'benefitsAr' => ['بيئة جامعية مهنية.', 'فرص تطوير وتدريب.', 'حزمة تنافسية حسب الدور والخبرة.'],
            'postedDate' => $postedDate,
            'closeDate' => '2026-12-31',
            'image' => '/images/career-development-hero.webp',
            'applicationEligible' => true,
        ];
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $filters */
    private function jobBoardDto(string $locale, array $payload, array $filters, bool $preview): CampusLifeSectionDTO
    {
        $jobs = $preview ? $this->jobArrays($payload['jobs'] ?? []) : $this->publicJobs($payload);
        $categories = $this->optionIds($payload['categories'] ?? []);
        $types = $this->optionIds($payload['types'] ?? []);
        $query = mb_substr(trim(is_scalar($filters['q'] ?? null) ? (string) $filters['q'] : ''), 0, 100);
        $category = is_string($filters['category'] ?? null) && in_array($filters['category'], $categories, true) ? $filters['category'] : 'all';
        $type = is_string($filters['type'] ?? null) && in_array($filters['type'], $types, true) ? $filters['type'] : 'all';

        $filtered = array_values(array_filter($jobs, static function (array $job) use ($query, $category, $type): bool {
            if ($category !== 'all' && ($job['category'] ?? null) !== $category) {
                return false;
            }

            if ($type !== 'all' && ($job['type'] ?? null) !== $type) {
                return false;
            }

            if ($query === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                (string) ($job['title'] ?? ''),
                (string) ($job['department'] ?? ''),
                (string) ($job['location'] ?? ''),
                (string) ($job['shortDescription'] ?? ''),
            ]));

            return str_contains($haystack, mb_strtolower($query));
        }));

        $total = count($filtered);
        $lastPage = max(1, (int) ceil($total / self::JOBS_PER_PAGE));
        $requestedPage = filter_var($filters['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $page = min($requestedPage, $lastPage);
        $payload['jobs'] = array_slice($filtered, ($page - 1) * self::JOBS_PER_PAGE, self::JOBS_PER_PAGE);
        $payload['resultCount'] = $total;
        $payload['activeFilters'] = ['q' => $query, 'category' => $category, 'type' => $type, 'page' => $page];
        $payload['pagination'] = [
            'currentPage' => $page,
            'lastPage' => $lastPage,
            'perPage' => self::JOBS_PER_PAGE,
            'total' => $total,
            'from' => $total === 0 ? 0 : (($page - 1) * self::JOBS_PER_PAGE) + 1,
            'to' => min($page * self::JOBS_PER_PAGE, $total),
        ];

        return $this->sectionDto('career-development/jobs', $locale, $this->normalizeUrls($payload, $locale));
    }

    /** @return array<int, array<string, mixed>> */
    private function publicJobs(array $payload): array
    {
        $today = CarbonImmutable::today();

        return array_values(array_filter($this->jobArrays($payload['jobs'] ?? []), static function (array $job) use ($today): bool {
            if (($job['status'] ?? null) !== 'open') {
                return false;
            }

            $postedDate = (string) ($job['postedDate'] ?? '');
            $closeDate = (string) ($job['closeDate'] ?? '');
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $postedDate) !== 1 || preg_match('~^\d{4}-\d{2}-\d{2}$~', $closeDate) !== 1) {
                return false;
            }

            try {
                $posted = CarbonImmutable::parse($postedDate);
                $close = CarbonImmutable::parse($closeDate);
            } catch (\Throwable) {
                return false;
            }

            return $posted->startOfDay()->lessThanOrEqualTo($today) && $close->endOfDay()->greaterThanOrEqualTo($today);
        }));
    }

    /** @return array<int, array<string, mixed>> */
    private function jobArrays(mixed $jobs): array
    {
        return array_values(array_filter(is_array($jobs) ? $jobs : [], static fn (mixed $job): bool => is_array($job)));
    }

    /** @param array<int, array<string, mixed>> $jobs @return array<string, mixed>|null */
    private function jobBySlug(array $jobs, string $slug): ?array
    {
        foreach ($jobs as $job) {
            if (($job['slug'] ?? null) === $slug) {
                return $job;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $jobs @param array<string, mixed> $job @return array<int, array<string, mixed>> */
    private function relatedJobs(array $jobs, array $job): array
    {
        $others = array_values(array_filter($jobs, static fn (array $candidate): bool => ($candidate['slug'] ?? null) !== ($job['slug'] ?? null)));
        $sameCategory = array_values(array_filter($others, static fn (array $candidate): bool => ($candidate['category'] ?? null) === ($job['category'] ?? null)));

        return array_slice(array_values(array_reduce([...$sameCategory, ...$others], static function (array $carry, array $candidate): array {
            $slug = (string) ($candidate['slug'] ?? '');
            if ($slug !== '' && ! isset($carry[$slug])) {
                $carry[$slug] = $candidate;
            }

            return $carry;
        }, [])), 0, 3);
    }

    /** @return array<int, string> */
    private function optionIds(mixed $options): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $option): string => is_array($option) ? (string) ($option['id'] ?? '') : '',
            is_array($options) ? $options : [],
        ), static fn (string $id): bool => $id !== ''));
    }

    /** @param array<int, array<string, string>> $items @return array<string, mixed> */
    private function simpleInfoPayload(string $type, string $titleEn, string $titleAr, string $overviewTitleEn, string $overviewTitleAr, string $summaryEn, string $summaryAr, array $items): array
    {
        return [
            'type' => 'simple-info',
            'hero' => [
                'image' => '/images/admissions-hero-campus.webp',
                'titleEn' => $titleEn,
                'titleAr' => $titleAr,
                'breadcrumbs' => $this->breadcrumbs($titleEn, $titleAr, '/campus-life/'.$type),
            ],
            'overview' => [
                'titleEn' => $overviewTitleEn,
                'titleAr' => $overviewTitleAr,
                'summaryEn' => $summaryEn,
                'summaryAr' => $summaryAr,
            ],
            'items' => $items,
            'seoDescriptionEn' => $summaryEn,
            'seoDescriptionAr' => $summaryAr,
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
                ['id' => 'job-board', 'icon' => '/images/icon-file-outline.svg', 'titleEn' => 'Job Board', 'titleAr' => 'لوحة الوظائف', 'summaryEn' => 'Access full-time job opportunities for recent graduates through verified employer outreach.', 'summaryAr' => 'الوصول إلى فرص عمل بدوام كامل للخريجين عبر أصحاب عمل موثوقين.', 'linkEn' => 'Open Job Board', 'linkAr' => 'فتح لوحة الوظائف', 'href' => '/campus-life/career-development/jobs'],
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

    /** @return array<string, mixed>|null */
    private function publishedLocalizedPayload(string $targetKey, string $locale): ?array
    {
        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);
        $localized = is_array($published['translations'][$locale] ?? null)
            ? $published['translations'][$locale]
            : null;

        return is_array($localized) ? $localized : null;
    }
}
