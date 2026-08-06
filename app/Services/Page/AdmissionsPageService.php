<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\DTOs\Admissions\AdmissionsPageDTO;
use App\DTOs\Admissions\AdmissionsSectionDTO;

final class AdmissionsPageService implements AdmissionsPageServiceInterface
{
    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly MediaServiceInterface $mediaService,
    ) {}

    public function getLanding(string $locale): AdmissionsPageDTO
    {
        $landing = $this->publishedLocalizedPayload('admissions.landing', $locale)
            ?? $this->localized($this->landingPayload(), $locale);

        return $this->landingDto($locale, $this->sanitizeLanding($this->normalizeUrls($landing, $locale), $locale));
    }

    public function buildPreviewLanding(string $locale, array $landing): AdmissionsPageDTO
    {
        return $this->landingDto($locale, $this->sanitizeLanding($this->normalizeUrls($landing, $locale), $locale));
    }

    /** @param array<string, mixed> $landing */
    private function landingDto(string $locale, array $landing): AdmissionsPageDTO
    {
        $hero = is_array($landing['hero'] ?? null) ? $landing['hero'] : [];
        $images = is_array($hero['images'] ?? null) ? $hero['images'] : [];

        return new AdmissionsPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            landing: $landing,
            seoTitle: (string) ($landing['seoTitle'] ?? ($locale === 'ar' ? 'القبول والتسجيل | الجامعة السورية الخاصة' : 'Admissions | Syrian Private University')),
            seoDescription: (string) ($landing['seoDescription'] ?? ($locale === 'ar'
                ? 'تعرّف إلى متطلبات القبول وخطوات التقديم والرسوم والدعم المتاح للطلاب الجدد في الجامعة السورية الخاصة.'
                : 'Understand SPU admission requirements, application steps, tuition guidance, and enrollment support.')),
            seoImage: (string) ($landing['seoImage'] ?? ($images['campus'] ?? '/images/admissions-hero-campus.webp')),
        );
    }

    public function getSection(string $slug, string $locale): ?AdmissionsSectionDTO
    {
        $payload = $this->publishedLocalizedPayload('admissions.'.$slug, $locale);

        if ($payload === null) {
            $sections = $this->sectionPayloads($locale);
            $payload = $sections[$slug] ?? null;
        }

        if ($payload === null) {
            return null;
        }

        $section = $this->sanitizeSection($slug, $this->normalizeUrls($this->localized($payload, $locale), $locale), $locale);

        return $this->sectionDto($slug, $locale, $section);
    }

    public function buildPreviewSection(string $targetKey, string $locale, array $section): ?AdmissionsSectionDTO
    {
        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            return null;
        }

        return $this->sectionDto($slug, $locale, $this->sanitizeSection($slug, $this->normalizeUrls($section, $locale), $locale));
    }

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array
    {
        $published = $this->cmsWorkflowService->getPublishedPayload($targetKey);

        if (is_array($published['translations']['ar'] ?? null) && is_array($published['translations']['en'] ?? null)) {
            return [
                'translations' => [
                    'ar' => $published['translations']['ar'],
                    'en' => $published['translations']['en'],
                ],
            ];
        }

        if ($targetKey === 'admissions.landing') {
            return [
                'translations' => [
                    'ar' => $this->normalizeUrls($this->localized($this->landingPayload(), 'ar'), 'ar'),
                    'en' => $this->normalizeUrls($this->localized($this->landingPayload(), 'en'), 'en'),
                ],
            ];
        }

        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            throw new \InvalidArgumentException('Unsupported admissions target.');
        }

        $arabicPayload = $this->sectionPayloads('ar')[$slug] ?? null;
        $englishPayload = $this->sectionPayloads('en')[$slug] ?? null;

        if ($arabicPayload === null || $englishPayload === null) {
            throw new \InvalidArgumentException('Unsupported admissions target.');
        }

        return [
            'translations' => [
                'ar' => $this->normalizeUrls($this->localized($arabicPayload, 'ar'), 'ar'),
                'en' => $this->normalizeUrls($this->localized($englishPayload, 'en'), 'en'),
            ],
        ];
    }

    /** @param array<string, mixed> $section */
    private function sectionDto(string $slug, string $locale, array $section): AdmissionsSectionDTO
    {
        $title = (string) ($section['title'] ?? ($locale === 'ar' ? 'القبول والتسجيل' : 'Admissions'));
        $description = (string) ($section['seoDescription'] ?? ($locale === 'ar'
            ? 'معلومات تفصيلية حول القبول والتسجيل في الجامعة السورية الخاصة.'
            : 'Detailed admissions and enrollment information for Syrian Private University.'));

        return new AdmissionsSectionDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            sectionSlug: $slug,
            section: $section,
            seoTitle: $title.' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'SPU'),
            seoDescription: $description,
            seoImage: (string) ($section['heroImage'] ?? '/images/DSC_1015.JPG'),
        );
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

    private function slugFromTargetKey(string $targetKey): ?string
    {
        if (! str_starts_with($targetKey, 'admissions.') || $targetKey === 'admissions.landing') {
            return null;
        }

        return substr($targetKey, strlen('admissions.'));
    }

    /** @return array<string, mixed> */
    private function landingPayload(): array
    {
        return [
            'hero' => [
                'titleEn' => 'Admissions',
                'titleAr' => 'القبول والتسجيل',
                'summaryEn' => 'Explore everything you need to know about joining SPU. Begin your journey toward academic excellence and modern heritage.',
                'summaryAr' => 'اكتشف كل ما تحتاج معرفته للانضمام إلى الجامعة السورية الخاصة. ابدأ رحلتك نحو التميز الأكاديمي والإرث الحديث.',
                'ctaPrimaryEn' => 'APPLY NOW',
                'ctaPrimaryAr' => 'قدّم الآن',
                'primaryUrl' => '/admissions/how-to-apply#application',
                'ctaSecondaryEn' => 'REQUEST INFORMATION',
                'ctaSecondaryAr' => 'اطلب معلومات',
                'secondaryUrl' => '/contact#admissions-support',
                'badgeLabelEn' => 'Admissions Status Badge',
                'badgeLabelAr' => 'حالة القبول',
                'badgeValueEn' => 'Contact Admissions for current availability',
                'badgeValueAr' => 'تواصل مع القبول لمعرفة حالة التقديم الحالية',
                'checklistItems' => [
                    ['titleEn' => 'Confirm Current Requirements', 'titleAr' => 'أكد المتطلبات الحالية', 'descEn' => 'Review the current applicant guidance and confirm required records with Admissions.', 'descAr' => 'راجع إرشادات المتقدم الحالية وأكد السجلات المطلوبة مع مديرية القبول.'],
                    ['titleEn' => 'Prepare Verified Records', 'titleAr' => 'حضّر السجلات الموثقة', 'descEn' => 'Do not submit identity or academic records until Admissions provides the approved channel.', 'descAr' => 'لا ترسل وثائق الهوية أو السجلات الأكاديمية حتى تزودك مديرية القبول بالقناة المعتمدة.'],
                ],
                'images' => [
                    'campus' => '/images/admissions-hero-campus.webp',
                    'campusAltEn' => 'Syrian Private University campus',
                    'campusAltAr' => 'حرم الجامعة السورية الخاصة',
                    'students' => '/images/admission/front-img.jpg',
                    'studentsAltEn' => 'Syrian Private University students',
                    'studentsAltAr' => 'طلاب الجامعة السورية الخاصة',
                ],
            ],
            'trustBar' => [
                ['titleEn' => 'Accredited Programs', 'titleAr' => 'برامج معتمدة', 'icon' => '/images/icon-award-outline.svg'],
                ['titleEn' => 'Expert Faculty', 'titleAr' => 'هيئة تدريسية متميزة', 'icon' => '/images/icon-university-outline.svg'],
                ['titleEn' => 'Student Support', 'titleAr' => 'دعم الطلاب', 'icon' => '/images/icon-handshake-outline.svg'],
                ['titleEn' => 'International Standards', 'titleAr' => 'معايير دولية', 'icon' => '/images/icon-globe-outline.svg'],
            ],
            'journey' => [
                'eyebrowEn' => 'Your Path',
                'eyebrowAr' => 'خطوات القبول',
                'titleEn' => 'The Admissions Journey',
                'titleAr' => 'رحلة القبول',
                'steps' => [
                    ['number' => '1', 'titleEn' => 'Explore Programs', 'titleAr' => 'استكشف البرامج', 'summaryEn' => 'Find the academic path that aligns with your goals.', 'summaryAr' => 'اعثر على المسار الأكاديمي الذي يتوافق مع أهدافك.', 'active' => true],
                    ['number' => '2', 'titleEn' => 'Check Requirements', 'titleAr' => 'تحقق من المتطلبات', 'summaryEn' => 'Review academic and language prerequisites.', 'summaryAr' => 'راجع المتطلبات الأكاديمية واللغوية.', 'active' => false],
                    ['number' => '3', 'titleEn' => 'Submit Application', 'titleAr' => 'قدّم الطلب', 'summaryEn' => 'Review academic and language prerequisites.', 'summaryAr' => 'راجع المتطلبات الأكاديمية واللغوية.', 'active' => false],
                    ['number' => '4', 'titleEn' => 'Await Decision', 'titleAr' => 'انتظر القرار', 'summaryEn' => 'Review academic and language prerequisites.', 'summaryAr' => 'راجع المتطلبات الأكاديمية واللغوية.', 'active' => false],
                    ['number' => '*', 'titleEn' => 'Enroll', 'titleAr' => 'سجّل', 'summaryEn' => 'Accept your offer and join the SPU community.', 'summaryAr' => 'اقبل عرضك وانضم إلى مجتمع الجامعة.', 'active' => false],
                ],
            ],
            'timeline' => [
                'eyebrowEn' => 'Key Dates',
                'eyebrowAr' => 'المواعيد الرئيسية',
                'titleEn' => 'Admissions Timeline',
                'titleAr' => 'الجدول الزمني للقبول',
                'summaryEn' => 'Our admissions process is designed to thoroughly evaluate each candidate. Please review the following key dates to ensure your application is timely.',
                'summaryAr' => 'تم تصميم عملية القبول لدينا لتقييم كل مرشح بدقة. يرجى مراجعة التواريخ الرئيسية التالية لضمان تقديم طلبك في الوقت المناسب.',
                'primaryDeadlineEn' => '',
                'primaryDeadlineAr' => '',
                'primaryDeadlineLabelEn' => 'CURRENT DATES',
                'primaryDeadlineLabelAr' => 'المواعيد الحالية',
                'primaryDeadlineDescEn' => 'Admission dates are published only after official approval. Contact Admissions or review official university announcements before applying.',
                'primaryDeadlineDescAr' => 'تنشر مواعيد القبول بعد اعتمادها رسمياً فقط. تواصل مع مديرية القبول أو راجع إعلانات الجامعة الرسمية قبل التقديم.',
                'image' => '/images/admissions-hero-campus.webp',
                'imageAltEn' => 'Syrian Private University campus',
                'imageAltAr' => 'حرم الجامعة السورية الخاصة',
                'phases' => [],
            ],
            'resources' => [
                'eyebrowEn' => 'Resources',
                'eyebrowAr' => 'موارد',
                'titleEn' => 'Admissions Resources',
                'titleAr' => 'موارد القبول',
                'subtitleEn' => 'Explore everything you need to know about joining SPU',
                'subtitleAr' => 'اكتشف كل ما تحتاج معرفته للانضمام إلى الجامعة',
                'cards' => $this->resourceCards(),
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function sectionPayloads(string $locale): array
    {
        return [
            'requirements' => $this->requirementsPayload($locale),
            'tuition' => $this->tuitionPayload($locale),
            'how-to-apply' => $this->howToApplyPayload(),
            'faq' => $this->faqPayload(),
            'calendar' => $this->calendarPayload(),
            'documents' => $this->documentsPayload(),
            'transfer' => $this->transferPayload(),
            'filling-vacancies' => $this->fillingVacanciesPayload(),
            'graduation-exams' => $this->graduationExamsPayload(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resourceCards(): array
    {
        return [
            ['titleEn' => 'How to Apply', 'titleAr' => 'كيفية التقديم', 'icon' => '/images/icon-telegram-outline.svg', 'descEn' => 'Step-by-step application guide', 'descAr' => 'دليل التقديم خطوة بخطوة', 'linkEn' => 'Start Application ->', 'linkAr' => '<- ابدأ التقديم', 'active' => true, 'slug' => 'how-to-apply'],
            ['titleEn' => 'Admission Requirements', 'titleAr' => 'متطلبات القبول', 'icon' => '/images/icon-check-circle-outline.svg', 'descEn' => 'Documents and prerequisites', 'descAr' => 'الوثائق والمتطلبات المسبقة', 'slug' => 'requirements'],
            ['titleEn' => 'Tuition & Fees', 'titleAr' => 'الرسوم والأقساط', 'icon' => '/images/icon-file-outline.svg', 'descEn' => 'Program costs and payment plans', 'descAr' => 'تكاليف البرامج وخطط الدفع', 'slug' => 'tuition'],
            ['titleEn' => 'FAQ', 'titleAr' => 'الأسئلة الشائعة', 'icon' => '/images/icon-envelope-outline.svg', 'descEn' => 'Common questions answered', 'descAr' => 'إجابات على الأسئلة الشائعة', 'slug' => 'faq'],
            ['titleEn' => 'Academic Calendar', 'titleAr' => 'التقويم الأكاديمي', 'icon' => '/images/icon-calendar-outline.svg', 'descEn' => 'Important dates and deadlines', 'descAr' => 'تواريخ ومواعيد هامة', 'slug' => 'calendar'],
            ['titleEn' => 'Documents Checklist', 'titleAr' => 'قائمة الوثائق', 'icon' => '/images/icon-file-outline.svg', 'descEn' => 'Required paperwork for all students', 'descAr' => 'الأوراق المطلوبة لجميع الطلاب', 'slug' => 'documents'],
            ['titleEn' => 'Transfer & International', 'titleAr' => 'طلاب التحويل والدوليون', 'icon' => '/images/icon-globe-outline.svg', 'descEn' => 'Pathways for global students', 'descAr' => 'مسارات للطلاب الدوليين', 'slug' => 'transfer'],
            ['titleEn' => 'Filling Vacancies', 'titleAr' => 'ملء الشواغر', 'icon' => '/images/icon-university-outline.svg', 'descEn' => 'Eligibility and process for vacant seats', 'descAr' => 'الأهلية وإجراءات التقديم على المقاعد الشاغرة', 'slug' => 'filling-vacancies'],
            ['titleEn' => 'Graduation & National Exams', 'titleAr' => 'التخرج والامتحانات الوطنية', 'icon' => '/images/icon-award-outline.svg', 'descEn' => 'Graduation clearance and national exam guidance', 'descAr' => 'إرشادات استكمال التخرج والامتحانات الوطنية', 'slug' => 'graduation-exams'],
        ];
    }

    /** @return array<string, mixed> */
    private function requirementsPayload(string $locale): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG',
            'breadcrumbHomeEn' => 'Home',
            'breadcrumbHomeAr' => 'الرئيسية',
            'breadcrumbParentEn' => 'Admissions',
            'breadcrumbParentAr' => 'القبول والتسجيل',
            'breadcrumbCurrentEn' => 'Admission Requirements',
            'breadcrumbCurrentAr' => 'متطلبات القبول',
            'titleEn' => 'Admission Requirements',
            'titleAr' => 'متطلبات القبول',
            'applyLabelEn' => 'Apply Now',
            'applyLabelAr' => 'قدّم الآن',
            'applyUrl' => '/admissions/how-to-apply#application',
            'requestInfoLabelEn' => 'Request Info',
            'requestInfoLabelAr' => 'اطلب معلومات',
            'requestInfoUrl' => '/contact#admissions-support',
            'eligibilityTitleEn' => 'Eligibility Criteria',
            'eligibilityTitleAr' => 'معايير الأهلية',
            'documentsTitleEn' => 'Required Documents',
            'documentsTitleAr' => 'الوثائق المطلوبة',
            'readyTitleEn' => 'Are You Ready to Apply?',
            'readyTitleAr' => 'هل أنت جاهز للتقديم؟',
            'notesTitleEn' => 'Important Institutional Notes',
            'notesTitleAr' => 'ملاحظات تنظيمية هامة',
            'requiredLabelEn' => 'Required',
            'requiredLabelAr' => 'مطلوب',
            'optionalLabelEn' => 'Optional (If applicable)',
            'optionalLabelAr' => 'اختياري (إن وجد)',
            'tabs' => [
                [
                    'id' => 'new',
                    'labelEn' => 'New Entrants',
                    'labelAr' => 'الطلاب المستجدون',
                    'criteria' => [
                        ['titleEn' => 'High School Diploma', 'titleAr' => 'شهادة الدراسة الثانوية', 'descEn' => 'Must hold a certified high school diploma or its equivalent recognized by the Syrian Ministry of Higher Education.', 'descAr' => 'يجب أن يحمل المتقدم شهادة ثانوية مصدقة أو ما يعادلها ومعترفاً بها من وزارة التعليم العالي السورية.'],
                        ['titleEn' => 'GPA Requirements', 'titleAr' => 'متطلبات المعدل', 'descEn' => 'Minimum GPA varies by faculty. Medical faculties typically require stronger thresholds than humanities tracks.', 'descAr' => 'يختلف الحد الأدنى للمعدل حسب الكلية، وتتطلب الكليات الطبية عادة معدلات أعلى من المسارات الإنسانية.'],
                        ['titleEn' => 'Age Limit', 'titleAr' => 'حدود العمر', 'descEn' => 'Applicants must satisfy annual university and ministry guidelines for initial enrollment.', 'descAr' => 'يجب أن يستوفي المتقدم تعليمات الجامعة والوزارة السنوية الخاصة بالتسجيل الأولي.'],
                    ],
                    'documents' => [
                        ['nameEn' => 'Original High School Transcript', 'nameAr' => 'كشف علامات الثانوية الأصلي', 'required' => true],
                        ['nameEn' => 'Photocopy of ID Card / Passport', 'nameAr' => 'صورة عن الهوية الشخصية أو جواز السفر', 'required' => true],
                        ['nameEn' => 'Four (4) Recent Passport Photos', 'nameAr' => 'أربع صور شخصية حديثة', 'required' => true],
                        ['nameEn' => 'Medical Fitness Certificate', 'nameAr' => 'شهادة لياقة صحية', 'required' => true],
                        ['nameEn' => 'Language Proficiency Test Scores', 'nameAr' => 'نتائج اختبار كفاءة اللغة', 'required' => false],
                    ],
                    'checklist' => [
                        ['en' => 'I know my applicant type', 'ar' => 'أعرف فئة طلبي'],
                        ['en' => 'I reviewed the requirements for my chosen faculty', 'ar' => 'راجعت متطلبات الكلية المختارة'],
                        ['en' => 'I have prepared all required documents', 'ar' => 'حضّرت جميع الوثائق المطلوبة'],
                        ['en' => 'I checked current application deadlines', 'ar' => 'تحققت من مواعيد التقديم الحالية'],
                    ],
                    'noteEn' => 'All admission requirements, including minimum GPA thresholds and accepted document formats, are subject to periodic review and may change based on official directives.',
                    'noteAr' => 'تخضع جميع شروط القبول، بما في ذلك الحدود الدنيا للمعدلات وصيغ الوثائق المقبولة، للمراجعة الدورية وقد تتغير وفقاً للتوجيهات الرسمية.',
                ],
                [
                    'id' => 'transfer',
                    'labelEn' => 'Transfer Students',
                    'labelAr' => 'طلاب التحويل',
                    'criteria' => [
                        ['titleEn' => 'University Enrollment Record', 'titleAr' => 'سجل قيد جامعي', 'descEn' => 'Applicant must have been enrolled at a recognized university or higher education institution before requesting transfer.', 'descAr' => 'يجب أن يكون المتقدم مسجلاً في جامعة أو مؤسسة تعليم عال معترف بها قبل طلب التحويل.'],
                        ['titleEn' => 'Academic Standing', 'titleAr' => 'الوضع الأكاديمي', 'descEn' => 'The student should be in good academic and disciplinary standing with no unresolved restrictions.', 'descAr' => 'ينبغي أن يكون الطالب بوضع أكاديمي وانضباطي جيد دون قيود غير محلولة.'],
                        ['titleEn' => 'Course Compatibility', 'titleAr' => 'توافق المقررات', 'descEn' => 'Completed courses are reviewed by the relevant faculty for content, credit hours, grades, and active regulations.', 'descAr' => 'تراجع الكلية المختصة المقررات المنجزة من حيث المحتوى والساعات والدرجات والأنظمة النافذة.'],
                    ],
                    'documents' => [
                        ['nameEn' => 'Official University Transcript', 'nameAr' => 'كشف علامات جامعي رسمي', 'required' => true],
                        ['nameEn' => 'Course Descriptions / Syllabi', 'nameAr' => 'توصيف المقررات', 'required' => true],
                        ['nameEn' => 'High School Certificate Copy', 'nameAr' => 'صورة عن شهادة الثانوية', 'required' => true],
                        ['nameEn' => 'Good Standing Letter', 'nameAr' => 'وثيقة حسن سيرة جامعية', 'required' => true],
                        ['nameEn' => 'Transfer Credit Evaluation Form', 'nameAr' => 'استمارة تقييم الساعات المحولة', 'required' => false],
                    ],
                    'checklist' => [
                        ['en' => 'I collected my official transcript', 'ar' => 'جمعت كشف العلامات الرسمي'],
                        ['en' => 'I prepared course descriptions', 'ar' => 'حضّرت توصيف المقررات'],
                        ['en' => 'I understand transfer credits are decided by the faculty', 'ar' => 'أفهم أن الساعات المقبولة تحددها الكلية'],
                        ['en' => 'I checked the transfer application period', 'ar' => 'تحققت من فترة تقديم التحويل'],
                    ],
                    'noteEn' => 'Transfer admission and credit recognition are not automatic. Final placement and accepted courses are determined after faculty review.',
                    'noteAr' => 'القبول بالتحويل واحتساب الساعات ليسا تلقائيين، ويتم تحديد الوضع النهائي والمقررات المقبولة بعد مراجعة الكلية.',
                ],
                [
                    'id' => 'equivalency',
                    'labelEn' => 'Equivalency Students',
                    'labelAr' => 'طلاب المعادلة',
                    'criteria' => [
                        ['titleEn' => 'Recognized External Certificate', 'titleAr' => 'شهادة خارجية معترف بها', 'descEn' => 'Applicants with non-Syrian certificates must provide documents eligible for equivalency review.', 'descAr' => 'يجب على أصحاب الشهادات غير السورية تقديم وثائق قابلة للمراجعة والمعادلة.'],
                        ['titleEn' => 'Equivalency Approval', 'titleAr' => 'قرار المعادلة', 'descEn' => 'Admission is conditional on obtaining the required equivalency or recognition decision.', 'descAr' => 'يكون القبول مشروطاً بالحصول على قرار المعادلة أو الاعتراف المطلوب.'],
                        ['titleEn' => 'Faculty Eligibility', 'titleAr' => 'أهلية الكلية', 'descEn' => 'The equivalency result must satisfy faculty-specific stream, subject, and minimum score conditions.', 'descAr' => 'يجب أن تحقق نتيجة المعادلة شروط الكلية من حيث المسار والمواد والحد الأدنى للدرجات.'],
                    ],
                    'documents' => [
                        ['nameEn' => 'Original External Certificate', 'nameAr' => 'الشهادة الخارجية الأصلية', 'required' => true],
                        ['nameEn' => 'Certified Arabic Translation', 'nameAr' => 'ترجمة عربية مصدقة', 'required' => true],
                        ['nameEn' => 'Equivalency / Recognition Decision', 'nameAr' => 'قرار المعادلة أو الاعتراف', 'required' => true],
                        ['nameEn' => 'Passport or National ID Copy', 'nameAr' => 'صورة جواز السفر أو الهوية', 'required' => true],
                        ['nameEn' => 'Authentication from Relevant Authorities', 'nameAr' => 'تصديقات الجهات المختصة', 'required' => false],
                    ],
                    'checklist' => [
                        ['en' => 'I verified that my certificate can be reviewed', 'ar' => 'تحققت من إمكانية مراجعة شهادتي'],
                        ['en' => 'I prepared certified translations where required', 'ar' => 'حضّرت الترجمات المصدقة عند الحاجة'],
                        ['en' => 'I started the official equivalency process', 'ar' => 'بدأت إجراءات المعادلة الرسمية'],
                        ['en' => 'I checked faculty-specific score conditions', 'ar' => 'راجعت شروط الدرجات الخاصة بالكلية'],
                    ],
                    'noteEn' => 'Equivalency cases require official verification before final admission. SPU may request additional authentication or ministry approvals.',
                    'noteAr' => 'تتطلب حالات المعادلة تحققاً رسمياً قبل القبول النهائي، وقد تطلب الجامعة تصديقات أو موافقات إضافية.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function tuitionPayload(string $locale): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG',
            'breadcrumbHomeEn' => 'Home',
            'breadcrumbHomeAr' => 'الرئيسية',
            'breadcrumbParentEn' => 'Admissions',
            'breadcrumbParentAr' => 'القبول والتسجيل',
            'breadcrumbCurrentEn' => 'Tuition & Fees',
            'breadcrumbCurrentAr' => 'الرسوم والأقساط',
            'titleEn' => 'Tuition & Fees',
            'titleAr' => 'الرسوم والأقساط',
            'filters' => [
                'facultyLabelEn' => 'Select Faculty',
                'facultyLabelAr' => 'اختر الكلية',
                'studentTypeLabelEn' => 'Select Student Type',
                'studentTypeLabelAr' => 'اختر نوع الطالب',
            ],
            'overviewTitleEn' => 'Tuition Fees Overview',
            'overviewTitleAr' => 'نظرة عامة على الرسوم',
            'tableHeaders' => [
                ['key' => 'faculty', 'labelEn' => 'Faculty', 'labelAr' => 'الكلية'],
                ['key' => 'type', 'labelEn' => 'Type', 'labelAr' => 'النوع'],
                ['key' => 'tuitionFee', 'labelEn' => 'Tuition Fee (Per Year)', 'labelAr' => 'الرسوم السنوية'],
                ['key' => 'registrationFee', 'labelEn' => 'Registration Fee', 'labelAr' => 'رسم التسجيل'],
                ['key' => 'additionalFees', 'labelEn' => 'Additional Fees', 'labelAr' => 'رسوم إضافية'],
                ['key' => 'notes', 'labelEn' => 'Notes', 'labelAr' => 'ملاحظات'],
            ],
            'feeRows' => [],
            'emptyStateEn' => 'No tuition rows match the selected filters.',
            'emptyStateAr' => 'لا توجد رسوم مطابقة للفلاتر المحددة.',
            'availabilityGuidanceEn' => 'Verified tuition amounts are not currently published on this page. Contact the Admissions or Finance Office for the approved fee schedule before making any payment.',
            'availabilityGuidanceAr' => 'لا توجد مبالغ رسوم معتمدة منشورة حالياً في هذه الصفحة. تواصل مع مديرية القبول أو المديرية المالية للحصول على جدول الرسوم المعتمد قبل إجراء أي دفعة.',
            'paymentTitleEn' => 'Payment Methods',
            'paymentTitleAr' => 'طرق الدفع',
            'methods' => [],
            'paymentGuidanceEn' => 'Use only payment instructions issued directly by SPU. No bank account or online payment link is published on this page at present.',
            'paymentGuidanceAr' => 'استخدم تعليمات الدفع الصادرة مباشرة عن الجامعة فقط. لا يوجد حالياً حساب مصرفي أو رابط دفع إلكتروني منشور في هذه الصفحة.',
            'notesTitleEn' => 'Important Financial Notes',
            'notesTitleAr' => 'ملاحظات مالية هامة',
            'notes' => [
                ['en' => 'All fees are subject to annual review and may be adjusted in accordance with university policy and local regulations.', 'ar' => 'تخضع جميع الرسوم للمراجعة السنوية وقد تعدل وفق سياسة الجامعة والأنظمة المحلية.'],
                ['en' => 'Registration fees are non-refundable once the academic semester commences.', 'ar' => 'رسوم التسجيل غير قابلة للاسترداد بعد بدء الفصل الدراسي.'],
                ['en' => 'Students must clear all outstanding financial dues prior to final examinations or graduation.', 'ar' => 'يجب على الطلاب تسديد جميع الذمم المالية قبل الامتحانات النهائية أو التخرج.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function howToApplyPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'How to Apply', 'breadcrumbCurrentAr' => 'كيفية التقديم', 'titleEn' => 'How to Apply', 'titleAr' => 'كيفية التقديم',
            'heroTitleEn' => 'Admissions Journey', 'heroTitleAr' => 'رحلة القبول',
            'heroDescEn' => 'Your path to joining Syrian Private University is designed to be clear and supportive. We ensure no dead ends-our admissions team is here to guide you at every step toward your academic future.',
            'heroDescAr' => 'تم تصميم طريقك للانضمام إلى الجامعة السورية الخاصة ليكون واضحاً وداعماً. نضمن عدم وجود طرق مسدودة، وفريق القبول لدينا هنا لإرشادك في كل خطوة نحو مستقبلك الأكاديمي.',
            'featureCards' => [
                ['titleEn' => 'Clear Steps', 'titleAr' => 'خطوات واضحة', 'descEn' => 'A straightforward, numbered process from application to enrollment.', 'descAr' => 'عملية مرقمة وواضحة من التقديم حتى التسجيل.', 'icon' => 'steps'],
                ['titleEn' => 'Required Documents', 'titleAr' => 'الوثائق المطلوبة', 'descEn' => 'Prepare your portfolio with our comprehensive checklist.', 'descAr' => 'حضّر ملفك اعتماداً على قائمة الوثائق الشاملة.', 'icon' => 'document'],
                ['titleEn' => 'Secure Application', 'titleAr' => 'طلب آمن', 'descEn' => 'Submit your information through the validated form on this page for Admissions review.', 'descAr' => 'أرسل معلوماتك عبر النموذج المتحقق منه في هذه الصفحة لمراجعتها من مديرية القبول.', 'icon' => 'apply'],
            ],
            'guideTitleEn' => 'Step-by-Step Guide', 'guideTitleAr' => 'دليل خطوة بخطوة',
            'steps' => [
                ['number' => '01', 'titleEn' => 'Choose Faculty', 'titleAr' => 'اختر الكلية', 'descEn' => 'Explore our diverse range of faculties and programs to find the perfect fit for your career aspirations.', 'descAr' => 'استكشف كلياتنا وبرامجنا المتنوعة لتجد الخيار الأنسب لطموحاتك المهنية.', 'ctaEn' => 'Explore Programs', 'ctaAr' => 'استكشف البرامج', 'href' => '/facilities/'],
                ['number' => '02', 'titleEn' => 'Review Requirements', 'titleAr' => 'راجع المتطلبات', 'descEn' => 'Ensure you meet the academic criteria and understand the specific prerequisites for your chosen degree program.', 'descAr' => 'تأكد من استيفاء المعايير الأكاديمية وفهم المتطلبات الخاصة بالبرنامج الذي اخترته.', 'ctaEn' => 'View Requirements', 'ctaAr' => 'عرض المتطلبات', 'href' => '/admissions/requirements/'],
                ['number' => '03', 'titleEn' => 'Prepare Documents', 'titleAr' => 'حضّر الوثائق', 'descEn' => 'Gather necessary paperwork, including identification, transcripts, and certificates, to streamline your application.', 'descAr' => 'اجمع الأوراق اللازمة، بما في ذلك الهوية وكشوف العلامات والشهادات، لتسهيل طلبك.', 'ctaEn' => 'Checklist Below', 'ctaAr' => 'قائمة الوثائق', 'href' => '/admissions/documents/'],
                ['number' => '04', 'titleEn' => 'Submit Application', 'titleAr' => 'قدّم الطلب', 'descEn' => 'Complete the secure admissions application form. Admissions staff will review the submitted information and contact you about the next required steps.', 'descAr' => 'أكمل نموذج طلب القبول الآمن. ستراجع مديرية القبول المعلومات المقدمة وتتواصل معك بشأن الخطوات المطلوبة التالية.', 'ctaEn' => 'Open Application Form', 'ctaAr' => 'افتح نموذج الطلب', 'href' => '/admissions/how-to-apply#application'],
            ],
            'applicationTitleEn' => 'Admissions Application',
            'applicationTitleAr' => 'طلب القبول',
            'applicationGuidanceEn' => 'Submitting this form starts an admissions review request. It does not reserve a seat or constitute an admission decision. Do not send documents or payments until Admissions provides verified instructions.',
            'applicationGuidanceAr' => 'يبدأ إرسال هذا النموذج طلب مراجعة للقبول، ولا يحجز مقعداً ولا يعد قرار قبول. لا ترسل وثائق أو دفعات قبل أن تزودك مديرية القبول بتعليمات موثقة.',
        ];
    }

    /** @return array<string, mixed> */
    private function faqPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Admissions FAQ', 'breadcrumbCurrentAr' => 'الأسئلة الشائعة للقبول', 'titleEn' => 'Admissions FAQ', 'titleAr' => 'الأسئلة الشائعة للقبول',
            'searchLabelEn' => 'Search admissions questions', 'searchLabelAr' => 'ابحث في أسئلة القبول', 'searchPlaceholderEn' => 'Search admissions questions...', 'searchPlaceholderAr' => 'ابحث في أسئلة القبول...', 'emptyStateEn' => 'No matching admissions questions found.', 'emptyStateAr' => 'لا توجد أسئلة مطابقة.',
            'sections' => [
                ['id' => 'application-process', 'titleEn' => 'Application Process', 'titleAr' => 'عملية تقديم الطلب', 'icon' => '/images/icon-file-outline.svg', 'items' => [
                    ['qEn' => 'Where can I find the current application deadline?', 'qAr' => 'أين أجد موعد التقديم الحالي؟', 'aEn' => 'Deadlines are published through official university announcements after approval. Confirm the active period with Admissions before submitting.', 'aAr' => 'تنشر المواعيد عبر إعلانات الجامعة الرسمية بعد اعتمادها. أكد الفترة النافذة مع مديرية القبول قبل التقديم.'],
                    ['qEn' => 'Can I apply to multiple programs at once?', 'qAr' => 'هل يمكنني التقديم لعدة برامج في نفس الوقت؟', 'aEn' => 'Yes. Each application must include the complete required documentation.', 'aAr' => 'نعم، ويجب أن يتضمن كل طلب الوثائق المطلوبة كاملة.'],
                    ['qEn' => 'How will I receive an admission decision?', 'qAr' => 'كيف أتلقى قرار القبول؟', 'aEn' => 'Admissions will use the verified contact information in your application to communicate review updates and required next steps.', 'aAr' => 'تستخدم مديرية القبول معلومات التواصل الموثقة في طلبك لإبلاغك بمستجدات المراجعة والخطوات المطلوبة.'],
                    ['qEn' => 'Where do I submit my application?', 'qAr' => 'أين أقدّم طلبي؟', 'aEn' => 'Applications are submitted through the official admissions channel announced by SPU. Contact admissions support if you need help before submission.', 'aAr' => 'تُقدّم الطلبات عبر قناة القبول الرسمية التي تعلنها الجامعة. تواصل مع دعم القبول إذا احتجت إلى مساعدة قبل التقديم.'],
                    ['qEn' => 'Can I update my application after submission?', 'qAr' => 'هل يمكنني تعديل طلبي بعد التقديم؟', 'aEn' => 'Updates may be possible before review is complete. The admissions office will confirm whether your file can still be amended.', 'aAr' => 'قد يكون التعديل ممكناً قبل اكتمال المراجعة، ويؤكد مكتب القبول ما إذا كان ملفك ما يزال قابلاً للتعديل.'],
                    ['qEn' => 'Do I need to visit campus before applying?', 'qAr' => 'هل يجب زيارة الحرم قبل التقديم؟', 'aEn' => 'A campus visit is not required, but applicants are welcome to request advising or visit days when available.', 'aAr' => 'زيارة الحرم ليست مطلوبة، لكن يمكن للمتقدمين طلب الاستشارة أو المشاركة في أيام الزيارة عند توفرها.'],
                    ['qEn' => 'What happens after I am accepted?', 'qAr' => 'ماذا يحدث بعد قبولي؟', 'aEn' => 'Accepted applicants receive instructions for confirming enrollment, completing documents, and paying required fees.', 'aAr' => 'يتلقى المقبولون تعليمات تثبيت التسجيل واستكمال الوثائق وتسديد الرسوم المطلوبة.'],
                ]],
                ['id' => 'admission-requirements', 'titleEn' => 'Admission Requirements', 'titleAr' => 'متطلبات القبول', 'icon' => '/images/icon-check-circle-outline.svg', 'items' => [
                    ['qEn' => 'What are the minimum high school GPA requirements?', 'qAr' => 'ما الحد الأدنى لمعدل الثانوية؟', 'aEn' => 'Minimum requirements vary by program and faculty.', 'aAr' => 'تختلف الحدود الدنيا حسب البرنامج والكلية.'],
                    ['qEn' => 'Do I need to submit standardized test scores (e.g., SAT/ACT)?', 'qAr' => 'هل أحتاج إلى تقديم درجات اختبارات معيارية؟', 'aEn' => 'Test requirements depend on the faculty and program.', 'aAr' => 'تعتمد متطلبات الاختبارات على الكلية والبرنامج.'],
                    ['qEn' => 'Which documents are required for new students?', 'qAr' => 'ما الوثائق المطلوبة للطلاب الجدد؟', 'aEn' => 'New students usually need a certified secondary certificate, identification documents, photos, and any faculty-specific requirements.', 'aAr' => 'يحتاج الطلاب الجدد عادةً إلى شهادة ثانوية مصدقة ووثائق شخصية وصور وأي متطلبات خاصة بالكلية.'],
                    ['qEn' => 'Are English placement tests required?', 'qAr' => 'هل اختبارات تحديد مستوى اللغة الإنجليزية مطلوبة؟', 'aEn' => 'Some programs may require language placement or proficiency verification before final enrollment.', 'aAr' => 'قد تتطلب بعض البرامج اختبار تحديد مستوى أو إثبات كفاءة لغوية قبل تثبيت التسجيل.'],
                    ['qEn' => 'Can transfer students apply?', 'qAr' => 'هل يمكن لطلاب التحويل التقديم؟', 'aEn' => 'Yes. Transfer applicants must submit university transcripts and course descriptions for faculty review.', 'aAr' => 'نعم. يجب على طلاب التحويل تقديم كشوف جامعية وتوصيف مقررات للمراجعة من قبل الكلية.'],
                    ['qEn' => 'Are original documents required?', 'qAr' => 'هل الوثائق الأصلية مطلوبة؟', 'aEn' => 'Original or certified documents may be requested for verification before final registration.', 'aAr' => 'قد تُطلب الوثائق الأصلية أو المصدقة للتحقق قبل التسجيل النهائي.'],
                ]],
                ['id' => 'tuition-fees', 'titleEn' => 'Tuition & Fees', 'titleAr' => 'الرسوم والأقساط', 'icon' => '/images/icon-file-outline.svg', 'items' => [
                    ['qEn' => 'What are the minimum high school GPA requirements?', 'qAr' => 'ما الحد الأدنى لمعدل الثانوية؟', 'aEn' => 'Fee categories do not replace academic requirements; review faculty eligibility before applying.', 'aAr' => 'فئات الرسوم لا تغني عن الشروط الأكاديمية؛ راجع أهلية الكلية قبل التقديم.'],
                    ['qEn' => 'Do I need to submit standardized test scores (e.g., SAT/ACT)?', 'qAr' => 'هل أحتاج إلى تقديم درجات اختبارات معيارية؟', 'aEn' => 'Testing requirements are determined by academic program, not by tuition category.', 'aAr' => 'تحدد متطلبات الاختبارات حسب البرنامج الأكاديمي وليس حسب فئة الرسوم.'],
                    ['qEn' => 'When are tuition payments due?', 'qAr' => 'متى تستحق دفعات الرسوم؟', 'aEn' => 'Payment deadlines are announced with registration instructions for each academic term.', 'aAr' => 'تُعلن مواعيد الدفع مع تعليمات التسجيل لكل فصل أكاديمي.'],
                    ['qEn' => 'Are installment plans available?', 'qAr' => 'هل تتوفر خطط تقسيط؟', 'aEn' => 'Instalment options may vary by faculty and academic year.', 'aAr' => 'قد تختلف خيارات التقسيط حسب الكلية والعام الأكاديمي.'],
                    ['qEn' => 'Are there scholarships or discounts?', 'qAr' => 'هل توجد منح أو حسومات؟', 'aEn' => 'Scholarship and discount availability depends on current university policy and official announcements.', 'aAr' => 'يعتمد توفر المنح والحسومات على سياسة الجامعة الحالية والإعلانات الرسمية.'],
                    ['qEn' => 'What payment methods are accepted?', 'qAr' => 'ما طرق الدفع المقبولة؟', 'aEn' => 'SPU accepts payment through official university channels announced by the finance office.', 'aAr' => 'تقبل الجامعة الدفع عبر القنوات الرسمية التي يعلنها مكتب المالية.'],
                ]],
                ['id' => 'international-students', 'titleEn' => 'International Students', 'titleAr' => 'الطلاب الدوليون', 'icon' => '/images/icon-globe-outline.svg', 'items' => [
                    ['qEn' => 'What are the minimum high school GPA requirements?', 'qAr' => 'ما الحد الأدنى لمعدل الثانوية للطلاب الدوليين؟', 'aEn' => 'Minimum requirements depend on certificate equivalency and the chosen faculty.', 'aAr' => 'تعتمد الحدود الدنيا على معادلة الشهادة والكلية المختارة.'],
                    ['qEn' => 'Do I need to submit standardized test scores (e.g., SAT/ACT)?', 'qAr' => 'هل أحتاج إلى تقديم اختبارات معيارية؟', 'aEn' => 'Some international certificates or programs may require additional testing or verification.', 'aAr' => 'قد تتطلب بعض الشهادات الدولية أو البرامج اختبارات أو تحققاً إضافياً.'],
                    ['qEn' => 'Do international documents need translation?', 'qAr' => 'هل تحتاج الوثائق الدولية إلى ترجمة؟', 'aEn' => 'Documents not issued in Arabic may require certified translation and authentication.', 'aAr' => 'قد تحتاج الوثائق غير الصادرة بالعربية إلى ترجمة وتصديق رسميين.'],
                    ['qEn' => 'Does SPU provide visa guidance?', 'qAr' => 'هل تقدم الجامعة إرشاداً للتأشيرة؟', 'aEn' => 'SPU can provide guidance, but applicants should confirm official visa requirements with competent authorities.', 'aAr' => 'يمكن للجامعة تقديم الإرشاد، لكن يجب تأكيد متطلبات التأشيرة الرسمية مع الجهات المختصة.'],
                    ['qEn' => 'Can I apply from outside Syria?', 'qAr' => 'هل يمكنني التقديم من خارج سوريا؟', 'aEn' => 'Yes. Applicants outside Syria should coordinate with admissions regarding document submission and authentication.', 'aAr' => 'نعم. على المتقدمين من خارج سوريا التنسيق مع القبول حول تقديم الوثائق وتصديقها.'],
                    ['qEn' => 'Is housing support available?', 'qAr' => 'هل يتوفر دعم للسكن؟', 'aEn' => 'Housing support availability may vary. Contact student affairs or admissions for current guidance.', 'aAr' => 'قد يختلف توفر دعم السكن. تواصل مع شؤون الطلاب أو القبول للحصول على المعلومات الحالية.'],
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function calendarPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Academic Calendar', 'breadcrumbCurrentAr' => 'التقويم الأكاديمي', 'titleEn' => 'Academic Calendar', 'titleAr' => 'التقويم الأكاديمي',
            'statCards' => [
                ['titleEn' => 'Official Schedule', 'titleAr' => 'الجدول الرسمي', 'descEn' => 'Approved dates are added here after publication by the University.', 'descAr' => 'تضاف المواعيد المعتمدة هنا بعد نشرها من الجامعة.', 'icon' => 'calendar'],
            ],
            'deadlinesTitleEn' => 'Essential Deadlines', 'deadlinesTitleAr' => 'المواعيد الأساسية', 'timelineTitleEn' => 'Detailed Academic Timeline', 'timelineTitleAr' => 'الجدول الأكاديمي التفصيلي',
            'deadlines' => [],
            'semesters' => [],
            'scheduleGuidanceEn' => 'No approved academic dates are currently published on this page. Check official university announcements or contact Student Affairs for the current calendar.',
            'scheduleGuidanceAr' => 'لا توجد مواعيد أكاديمية معتمدة منشورة حالياً في هذه الصفحة. راجع إعلانات الجامعة الرسمية أو تواصل مع شؤون الطلاب للحصول على التقويم الحالي.',
            'download' => [],
            'notice' => ['titleEn' => 'Official Notice', 'titleAr' => 'تنبيه رسمي', 'descEn' => 'Dates in this academic calendar are subject to change. The University reserves the right to modify the calendar as necessary. Official announcements regarding any changes will be communicated via university email and posted on the official website.', 'descAr' => 'التواريخ في هذا التقويم الأكاديمي قابلة للتغيير. تحتفظ الجامعة بحق تعديل التقويم عند الضرورة، وسيتم إبلاغ أي تغييرات عبر البريد الجامعي ونشرها على الموقع الرسمي.'],
        ];
    }

    /** @return array<string, mixed> */
    private function documentsPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'lastReviewed' => '', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Documents & Checklists', 'breadcrumbCurrentAr' => 'الوثائق وقوائم التحقق', 'titleEn' => 'Documents & Checklists', 'titleAr' => 'الوثائق وقوائم التحقق', 'applyLabelEn' => 'APPLY NOW', 'applyLabelAr' => 'قدّم الآن', 'applyUrl' => '/admissions/how-to-apply#application', 'requestInfoLabelEn' => 'Request Info', 'requestInfoLabelAr' => 'اطلب معلومات', 'requestInfoUrl' => '/contact', 'requiredLabelEn' => 'Required', 'requiredLabelAr' => 'مطلوب', 'optionalLabelEn' => 'Optional', 'optionalLabelAr' => 'اختياري', 'downloadLabelEn' => 'Download PDF', 'downloadLabelAr' => 'تحميل PDF', 'downloadAllLabelEn' => 'Admissions files', 'downloadAllLabelAr' => 'ملفات القبول', 'downloadAllDescEn' => 'Verified downloadable files will appear here after publication through the Media Library.', 'downloadAllDescAr' => 'ستظهر الملفات القابلة للتنزيل هنا بعد اعتمادها ونشرها عبر مكتبة الوسائط.', 'downloadGuidanceEn' => 'No verified admissions file is currently available for download. Use the on-page checklist and confirm current requirements with Admissions before submitting documents.', 'downloadGuidanceAr' => 'لا يتوفر حالياً ملف قبول موثق للتنزيل. استخدم القائمة المعروضة في الصفحة وأكد المتطلبات الحالية مع مديرية القبول قبل تقديم الوثائق.', 'lastReviewedLabelEn' => 'Last reviewed', 'lastReviewedLabelAr' => 'آخر مراجعة',
            'tabs' => [
                ['id' => 'checklist', 'labelEn' => 'Admission Checklist', 'labelAr' => 'قائمة القبول', 'subTabs' => [
                    ['id' => 'freshman', 'labelEn' => 'Freshman', 'labelAr' => 'مستجد', 'descEn' => 'These are the documents required for first-time university applicants holding a certified high school diploma or equivalent. Confirm the current form and certification requirements with Admissions.', 'descAr' => 'هذه وثائق إرشادية للمتقدمين للجامعة لأول مرة والحاصلين على شهادة الثانوية العامة أو ما يعادلها. أكد النموذج الحالي ومتطلبات التصديق مع مديرية القبول.', 'download' => [], 'items' => [
                        ['nameEn' => 'Certified High School Diploma', 'nameAr' => 'شهادة الثانوية العامة مصدقة', 'required' => true, 'noteEn' => 'Must be certified by the Ministry of Education.', 'noteAr' => 'يجب أن تكون مصدقة من وزارة التربية.'],
                        ['nameEn' => 'Copy of National ID or Passport', 'nameAr' => 'صورة عن الهوية الشخصية أو جواز السفر', 'required' => true, 'noteEn' => 'Valid and clear photocopy.', 'noteAr' => 'نسخة واضحة وسارية المفعول.'],
                        ['nameEn' => '4 Personal Photos (White Background)', 'nameAr' => '4 صور شخصية (خلفية بيضاء)', 'required' => true, 'noteEn' => 'Recent photos, 4x6 cm.', 'noteAr' => 'صور حديثة، مقاس 4x6 سم.'],
                        ['nameEn' => 'Medical Fitness Certificate', 'nameAr' => 'شهادة اللياقة الطبية', 'required' => true, 'noteEn' => 'From an approved medical center.', 'noteAr' => 'من مركز طبي معتمد.'],
                        ['nameEn' => 'Military Service Status Document (for males)', 'nameAr' => 'وثيقة حالة الخدمة الإلزامية (للذكور)', 'required' => true, 'noteEn' => 'Or postponement document if applicable.', 'noteAr' => 'أو وثيقة التأجيل إن وجدت.'],
                        ['nameEn' => 'Proof of Residence', 'nameAr' => 'إثبات السكن', 'required' => false, 'noteEn' => 'Recent utility bill or rental contract.', 'noteAr' => 'فاتورة خدمات حديثة أو عقد إيجار.'],
                    ]],
                    ['id' => 'transfer', 'labelEn' => 'Transfer', 'labelAr' => 'تحويل', 'descEn' => 'Transfer students provide records from their previous institution in addition to identification. Credit recognition remains subject to faculty review.', 'descAr' => 'يقدم طلاب التحويل سجلات مؤسستهم السابقة إضافة إلى وثائق الهوية. يبقى احتساب الساعات خاضعاً لمراجعة الكلية.', 'download' => [], 'items' => [
                        ['nameEn' => 'Official University Transcript', 'nameAr' => 'كشف علامات جامعي رسمي', 'required' => true, 'noteEn' => 'Sealed and stamped by the previous university.', 'noteAr' => 'مختوم وموثق من الجامعة السابقة.'],
                        ['nameEn' => 'Course Descriptions / Syllabi', 'nameAr' => 'وصف المساقات / المناهج', 'required' => true, 'noteEn' => 'For all completed courses seeking transfer.', 'noteAr' => 'لجميع المساقات المنجزة المراد تحويلها.'],
                        ['nameEn' => 'High School Certificate Copy', 'nameAr' => 'صورة عن شهادة الثانوية', 'required' => true, 'noteEn' => 'Certified copy.', 'noteAr' => 'نسخة مصدقة.'],
                        ['nameEn' => 'Good Standing / Non-Disciplinary Letter', 'nameAr' => 'خطاب حسن سلوك / عدم تأديبي', 'required' => true, 'noteEn' => 'From the previous university registrar.', 'noteAr' => 'من مسجل الجامعة السابقة.'],
                        ['nameEn' => 'Transfer Credit Evaluation Form', 'nameAr' => 'نموذج تقييم ساعات التحويل', 'required' => false, 'noteEn' => 'Available at the Admissions Office.', 'noteAr' => 'متوفر في مكتب القبول والتسجيل.'],
                    ]],
                    ['id' => 'equivalency', 'labelEn' => 'Equivalency', 'labelAr' => 'معادلة', 'descEn' => 'Applicants with certificates requiring equivalency should confirm the current competent-authority process before final admission.', 'descAr' => 'على أصحاب الشهادات التي تتطلب معادلة تأكيد الإجراءات الحالية لدى الجهة المختصة قبل القبول النهائي.', 'download' => [], 'items' => [
                        ['nameEn' => 'Original External Certificate', 'nameAr' => 'الشهادة الخارجية الأصلية', 'required' => true, 'noteEn' => 'Attested by the issuing country and Syrian embassy.', 'noteAr' => 'مصدقة من دولة الإصدار والسفارة السورية.'],
                        ['nameEn' => 'Certified Arabic Translation', 'nameAr' => 'ترجمة عربية مصدقة', 'required' => true, 'noteEn' => 'By a sworn translator if the certificate is not in Arabic.', 'noteAr' => 'من مترجم قسمي إذا كانت الشهادة ليست بالعربية.'],
                        ['nameEn' => 'Equivalency / Recognition Decision', 'nameAr' => 'قرار المعادلة / المعترف بها', 'required' => true, 'noteEn' => 'From the Syrian Ministry of Higher Education.', 'noteAr' => 'من وزارة التعليم العالي السورية.'],
                        ['nameEn' => 'Passport or National ID Copy', 'nameAr' => 'صورة عن جواز السفر أو الهوية', 'required' => true, 'noteEn' => 'Valid and clear photocopy.', 'noteAr' => 'نسخة واضحة وسارية المفعول.'],
                    ]],
                    ['id' => 'international', 'labelEn' => 'International', 'labelAr' => 'دولي', 'descEn' => 'International applicants should confirm current passport, residency, authentication, and equivalency requirements before applying.', 'descAr' => 'على المتقدمين الدوليين تأكيد متطلبات جواز السفر والإقامة والتصديق والمعادلة الحالية قبل التقديم.', 'download' => [], 'items' => [
                        ['nameEn' => 'Valid Passport Copy', 'nameAr' => 'صورة عن جواز السفر الساري', 'required' => true, 'noteEn' => 'Must be valid for at least 6 months.', 'noteAr' => 'يجب أن يكون سارياً لمدة 6 أشهر على الأقل.'],
                        ['nameEn' => 'Certified Secondary School Certificate', 'nameAr' => 'شهادة الثانوية العامة مصدقة', 'required' => true, 'noteEn' => 'Attested by the issuing country and Syrian embassy.', 'noteAr' => 'مصدقة من دولة الإصدار والسفارة السورية.'],
                        ['nameEn' => 'Ministry Equivalency Documents', 'nameAr' => 'وثائق معادلة الوزارة', 'required' => true, 'noteEn' => 'Decision from the Ministry of Higher Education.', 'noteAr' => 'قرار من وزارة التعليم العالي.'],
                        ['nameEn' => '4 Recent Passport Photos', 'nameAr' => '4 صور جواز سفر حديثة', 'required' => true, 'noteEn' => 'White background, 4x6 cm.', 'noteAr' => 'خلفية بيضاء، مقاس 4x6 سم.'],
                        ['nameEn' => 'Visa or Residency Documents', 'nameAr' => 'وثائق التأشيرة أو الإقامة', 'required' => false, 'noteEn' => 'If already residing in Syria.', 'noteAr' => 'إذا كان المتقدم يقيم في سوريا.'],
                    ]],
                ]],
                ['id' => 'granted', 'labelEn' => 'University Documents', 'labelAr' => 'وثائق الجامعة', 'introEn' => 'The following official documents are issued by Syrian Private University to enrolled and graduated students upon request. Processing times and fees may vary.', 'introAr' => 'الوثائق الرسمية التالية تصدرها الجامعة السورية الخاصة للطلاب المسجلين والخريجين بناءً على الطلب. قد تختلف أوقات المعالجة والرسوم.', 'items' => [
                    ['titleEn' => 'Transcript of Records', 'titleAr' => 'كشف علامات / سجل أكاديمي', 'descEn' => 'Official academic transcript showing all completed courses, grades, and GPA in Arabic and English.', 'descAr' => 'سجل أكاديمي رسمي يظهر جميع المساقات المنجزة والعلامات والمعدل التراكمي بالعربية والإنجليزية.', 'availabilityEn' => 'Available for all students', 'availabilityAr' => 'متاح لجميع الطلاب'],
                    ['titleEn' => 'Graduation Notice', 'titleAr' => 'إشعار التخرج', 'descEn' => 'Official graduation notification issued in both Arabic and English upon completion of degree requirements.', 'descAr' => 'إشعار تخرج رسمي يصدر بالعربية والإنجليزية عند استيفاء متطلبات الدرجة.', 'availabilityEn' => 'Graduates only', 'availabilityAr' => 'للخريجين فقط'],
                    ['titleEn' => 'Registration & Attendance Proof', 'titleAr' => 'إثبات التسجيل والحضور', 'descEn' => 'Document certifying current enrollment and attendance status for the academic year.', 'descAr' => 'وثيقة تصدق بحالة التسجيل والحضور الحالية للعام الأكاديمي.', 'availabilityEn' => 'Current students', 'availabilityAr' => 'للطلاب المسجلين حالياً'],
                    ['titleEn' => 'Course Description', 'titleAr' => 'وصف المساق', 'descEn' => 'Detailed syllabus and course content description for individual subjects, often required for transfer or equivalency.', 'descAr' => 'المنهج التفصيلي ووصف محتوى المساق للمواد الفردية، مطلوب غالباً للتحويل أو المعادلة.', 'availabilityEn' => 'Per course request', 'availabilityAr' => 'حسب طلب المساق'],
                ]],
                ['id' => 'studySystem', 'labelEn' => 'Study System & GPA', 'labelAr' => 'نظام الدراسة والمعدل', 'introEn' => 'Syrian Private University follows a semester-based credit hour system. Academic performance is evaluated using a 4.0 GPA scale alongside the traditional percentage system.', 'introAr' => 'تتبع الجامعة السورية الخاصة نظام الساعات المعتمدة المبني على الفصول الدراسية. يتم تقييم الأداء الأكاديمي باستخدام مقياس المعدل التراكمي 4.0 إلى جانب نظام النسبة المئوية التقليدي.', 'scaleTitleEn' => 'Grading Scale', 'scaleTitleAr' => 'مقياس التقديرات', 'scaleHeaders' => [
                    ['key' => 'percentage', 'labelEn' => 'Percentage', 'labelAr' => 'النسبة المئوية'], ['key' => 'gpa', 'labelEn' => 'GPA (4.0)', 'labelAr' => 'المعدل (4.0)'], ['key' => 'grade', 'labelEn' => 'Grade', 'labelAr' => 'التقدير'], ['key' => 'descriptor', 'labelEn' => 'Descriptor', 'labelAr' => 'الوصف'],
                ], 'scaleRows' => [], 'notes' => [
                    ['en' => 'The complete grading scale is published only after verification against the current study regulations. Contact the Registrar for the applicable scale.', 'ar' => 'ينشر مقياس التقديرات الكامل بعد التحقق من نظام الدراسة النافذ. تواصل مع أمانة السجل للحصول على المقياس المطبق.'],
                ]],
                ['id' => 'warnings', 'labelEn' => 'Academic Warnings', 'labelAr' => 'الإنذارات الأكاديمية', 'introEn' => 'Academic-warning thresholds and consequences depend on the current study regulations. Contact the Registrar or your faculty adviser for verified guidance.', 'introAr' => 'تعتمد حدود الإنذارات الأكاديمية وآثارها على نظام الدراسة النافذ. تواصل مع أمانة السجل أو مرشد الكلية للحصول على إرشادات موثقة.', 'levelsTitleEn' => 'Warning Levels', 'levelsTitleAr' => 'مستويات الإنذار', 'levels' => []],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function transferPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Transfer & International Students', 'breadcrumbCurrentAr' => 'التحويل والطلاب الدوليون', 'titleEn' => 'Transfer & International Students', 'titleAr' => 'التحويل والطلاب الدوليون', 'applyLabelEn' => 'APPLY NOW', 'applyLabelAr' => 'قدّم الآن', 'applyUrl' => '/admissions/how-to-apply#application', 'requestInfoLabelEn' => 'Request Info', 'requestInfoLabelAr' => 'اطلب معلومات', 'requestInfoUrl' => '/contact#admissions-support', 'requiredLabelEn' => 'Required', 'requiredLabelAr' => 'مطلوب', 'optionalLabelEn' => 'Optional (if Applicable)', 'optionalLabelAr' => 'اختياري (إذا توفر)',
            'tabs' => [
                ['id' => 'transfer', 'labelEn' => 'Transfer Student', 'labelAr' => 'طالب محوّل', 'policiesTitleEn' => 'Transfer Policies', 'policiesTitleAr' => 'سياسات التحويل', 'policies' => [['icon' => 'transfer', 'titleEn' => 'Credit Transfer Policy', 'titleAr' => 'سياسة تحويل الساعات المعتمدة', 'descEn' => 'Credits are evaluated on a course-by-course basis. A minimum grade of C or equivalent is required for transfer consideration. Core curriculum courses undergo rigorous review by the respective college dean.', 'descAr' => 'يتم تقييم الساعات المعتمدة على أساس كل مقرر على حدة. يُشترط الحصول على حد أدنى من الدرجات C أو ما يعادلها للنظر في التحويل الأكاديمي. وتخضع مساقات المنهج الأساسي لمراجعة دقيقة من قبل عميد الكلية المختص.'], ['icon' => 'equivalency', 'titleEn' => 'Course Equivalency', 'titleAr' => 'تعادل المقررات الدراسية', 'descEn' => 'Applicants must provide detailed syllabi for courses seeking equivalency. The academic committee assesses content overlap, credit hours, and learning outcomes against SPU standards.', 'descAr' => 'يجب على المتقدمين تقديم توصيف تفصيلي للمقررات التي يطلبون معادلتها. وتقوم اللجنة الأكاديمية بتقييم تداخل المحتوى، والساعات المعتمدة، ومخرجات التعلم مقارنة بمعايير جامعة SPU.']], 'documentsTitleEn' => 'Required Documents', 'documentsTitleAr' => 'الوثائق المطلوبة للتقديم', 'documents' => [['titleEn' => 'Original High School Transcript', 'titleAr' => 'وثيقة شهادة الثانوية العامة الأصلية المصدقة', 'required' => true], ['titleEn' => 'Photocopy of ID Card / Passport', 'titleAr' => 'صورة عن الهوية الشخصية أو جواز السفر', 'required' => true], ['titleEn' => 'Four (4) Recent Passport Photos', 'titleAr' => 'أربع (4) صور شخصية ملونة حديثة', 'required' => true], ['titleEn' => 'Medical Fitness Certificate', 'titleAr' => 'شهادة خلو من الأمراض السارية (شهادة صحية)', 'required' => true], ['titleEn' => 'Language Proficiency Test Scores', 'titleAr' => 'درجات اختبار كفاءة اللغة الإنجليزية (إن وجد)', 'required' => false]], 'processTitleEn' => 'Application Process', 'processTitleAr' => 'خطوات وإجراءات التقديم', 'steps' => [['titleEn' => 'Choose Type', 'titleAr' => 'اختر الفئة', 'descEn' => 'Determine if you are applying as a transfer or international student.', 'descAr' => 'تحديد ما إذا كنت تتقدم للدراسة كطالب محوّل أو طالب دولي.'], ['titleEn' => 'Review Requirements', 'titleAr' => 'مراجعة المتطلبات الأكاديمية', 'descEn' => 'Gather all necessary documentation based on your applicant type.', 'descAr' => 'جمع كافة المستندات والوثائق المطلوبة بناءً على فئة التقديم الخاصة بك.'], ['titleEn' => 'Prepare Documents', 'titleAr' => 'تحضير وتصديق الوثائق', 'descEn' => 'Ensure transcripts, syllabi, and identification are certified.', 'descAr' => 'التأكد من تصديق كشوف العلامات، وتوصيف المقررات الأكاديمية، والوثائق الشخصية رسمياً.']]],
                ['id' => 'international', 'labelEn' => 'International Student', 'labelAr' => 'طالب دولي', 'policiesTitleEn' => 'International Student Policies', 'policiesTitleAr' => 'سياسات الطلاب الدوليين', 'policies' => [['icon' => 'language', 'titleEn' => 'Language Requirements', 'titleAr' => 'متطلبات الكفاءة اللغوية', 'descEn' => 'Applicants may be asked to provide Arabic or English language evidence depending on faculty requirements and the chosen academic programme.', 'descAr' => 'قد يُطلب من المتقدمين تقديم إثبات إتقان اللغة العربية أو الإنجليزية اعتماداً على شروط القبول في الكلية والبرنامج الأكاديمي المختار.'], ['icon' => 'visa', 'titleEn' => 'Visa & Equivalency Guide', 'titleAr' => 'دليل التأشيرات ومعادلة الشهادات', 'descEn' => 'International applicants should confirm visa status, passport validity, and Ministry of Education equivalency requirements before final admission.', 'descAr' => 'يجب على المتقدمين الدوليين التأكد من حالة التأشيرة وصلاحية جواز السفر، واستيفاء متطلبات المعادلة لدى وزارة التعليم العالي السورية قبل القبول النهائي.']], 'documentsTitleEn' => 'Required Documents', 'documentsTitleAr' => 'الوثائق المطلوبة للطلاب الدوليين', 'documents' => [['titleEn' => 'Valid Passport Copy', 'titleAr' => 'صورة واضحة عن جواز سفر ساري المفعول', 'required' => true], ['titleEn' => 'Certified Secondary School Certificate', 'titleAr' => 'شهادة الدراسة الثانوية العامة مصدقة من وزارة الخارجية والسفارة السورية', 'required' => true], ['titleEn' => 'Ministry Equivalency Documents', 'titleAr' => 'وثيقة معادلة الشهادة الثانوية الصادرة عن وزارة التعليم العالي السورية', 'required' => true], ['titleEn' => 'Four (4) Recent Passport Photos', 'titleAr' => 'أربع (4) صور شخصية ملونة حديثة', 'required' => true], ['titleEn' => 'Visa or Residency Documents', 'titleAr' => 'صورة عن التأشيرة الدراسية أو إقامة سارية المفعول في سوريا', 'required' => false]], 'processTitleEn' => 'Application Process', 'processTitleAr' => 'خطوات تقديم طلبات القبول الدولي', 'steps' => [['titleEn' => 'Confirm Eligibility', 'titleAr' => 'التحقق من الأهلية والمعادلة', 'descEn' => 'Review country-specific academic and equivalency requirements.', 'descAr' => 'مراجعة المتطلبات الأكاديمية وشروط معادلة الشهادات المعتمدة لكل جنسية.'], ['titleEn' => 'Submit Documents', 'titleAr' => 'تقديم ملف الوثائق', 'descEn' => 'Provide certified academic records, passport documents, and translations.', 'descAr' => 'تقديم السجلات الأكاديمية المصدقة، وصور جواز السفر، والترجمات المعتمدة قانونياً.'], ['titleEn' => 'Finalize Admission', 'titleAr' => 'استكمال التسجيل والقبول', 'descEn' => 'Coordinate with Admissions for acceptance, visa guidance, and registration steps.', 'descAr' => 'التنسيق مع مكتب القبول والتسجيل لاستلام إشعار القبول الرسمي وإتمام إجراءات الإقامة.']]],
            ],
            'notesTitleEn' => 'Important Institutional Notes', 'notesTitleAr' => 'ملاحظات تنظيمية هامة للمتقدمين', 'notesDescEn' => 'All admission requirements, including minimum GPA thresholds and accepted document formats, are subject to periodic review and may change based on directives from the Syrian Ministry of Higher Education. Applicants are strongly advised to consult the official university announcements or contact the Admissions Office directly for the most current information before submitting their application. The University reserves the right to request additional documentation to verify applicant eligibility.', 'notesDescAr' => 'تخضع جميع شروط القبول والمعدلات الدنيا للقبول وصيغ الوثائق المعتمدة للمراجعة الدورية، وقد يطرأ عليها تعديل بموجب القرارات الناظمة الصادرة عن وزارة التعليم العالي والبحث العلمي السورية. ويُنصح المتقدمون بشدة بالاطلاع على لوائح الجامعة الرسمية المستجدة أو التواصل المباشر مع مديرية القبول والتسجيل للحصول على أدق المعلومات قبل التقديم. وتحتفظ الجامعة بالحق في طلب مستندات إضافية عند الحاجة للتحقق من أهلية المتقدم.',
        ];
    }

    /** @return array<int, array<string, string>> */
    private function navigationCards(string $currentSlug, string $locale): array
    {
        return collect($this->resourceCards())
            ->reject(static fn (array $card): bool => ($card['slug'] ?? '') === $currentSlug)
            ->map(function (array $card) use ($locale): array {
                $localized = $this->localized($card, $locale);

                return [
                    'title' => (string) ($localized['title'] ?? ''),
                    'href' => '/admissions/'.(string) ($card['slug'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function fillingVacanciesPayload(): array
    {
        return [
            'type' => 'simple-cards',
            'heroImage' => '/images/admission/front-img.jpg',
            'breadcrumbHomeEn' => 'Home',
            'breadcrumbHomeAr' => 'الرئيسية',
            'breadcrumbParentEn' => 'Admissions',
            'breadcrumbParentAr' => 'القبول والتسجيل',
            'breadcrumbCurrentEn' => 'Filling Vacancies',
            'breadcrumbCurrentAr' => 'ملء الشواغر',
            'titleEn' => 'Filling Vacant Seats',
            'titleAr' => 'ملء الشواغر',
            'introEn' => 'No verified vacant-seat announcement is currently published on this page. Availability, eligible faculties, dates, and the official submission method will appear here only after approval through the Admissions CMS workflow.',
            'introAr' => 'لا يوجد حالياً إعلان موثق لملء الشواغر منشور في هذه الصفحة. لن تظهر أعداد الشواغر والكليات المؤهلة والمواعيد وآلية التقديم الرسمية هنا إلا بعد اعتمادها عبر نظام إدارة محتوى القبول.',
            'cardsTitleEn' => '',
            'cardsTitleAr' => '',
            'cards' => [],
            'seoDescriptionEn' => 'Apply for vacant seats at SPU after the initial enrollment period. Check eligibility, required documents, and application process.',
            'seoDescriptionAr' => 'التقديم على المقاعد الشاغرة في الجامعة السورية الخاصة بعد فترة التسجيل الأولي، مع الأهلية والوثائق المطلوبة وآلية التقديم.',
        ];
    }

    /** @return array<string, mixed> */
    private function graduationExamsPayload(): array
    {
        return [
            'type' => 'simple-steps',
            'heroImage' => '/images/admission/front-img.jpg',
            'breadcrumbHomeEn' => 'Home',
            'breadcrumbHomeAr' => 'الرئيسية',
            'breadcrumbParentEn' => 'Admissions',
            'breadcrumbParentAr' => 'القبول والتسجيل',
            'breadcrumbCurrentEn' => 'Graduation & National Exams',
            'breadcrumbCurrentAr' => 'التخرج والامتحانات الوطنية',
            'titleEn' => 'Graduation & National Examinations',
            'titleAr' => 'التخرج والامتحانات الوطنية',
            'intro' => [
                ['en' => 'SPU follows the graduation requirements and national examination regulations set by the Syrian Ministry of Higher Education and Scientific Research. Students must fulfill all academic, administrative, and financial obligations before being cleared for graduation.', 'ar' => 'تتبع الجامعة السورية الخاصة متطلبات التخرج وأنظمة الامتحانات الوطنية التي وضعتها وزارة التعليم العالي والبحث العلمي. يجب على الطلاب استيفاء جميع الالتزامات الأكاديمية والإدارية والمالية قبل الموافقة على تخرجهم.'],
                ['en' => 'In addition to faculty-specific graduation requirements, certain programs require students to pass national examinations administered by the Ministry. These exams assess graduate competency and may be required for professional licensing.', 'ar' => 'بالإضافة إلى متطلبات التخرج الخاصة بكل كلية، تتطلب بعض البرامج اجتياز امتحانات وطنية تديرها الوزارة. تقيم هذه الامتحانات كفاءة الخريجين وقد تكون شرطا للترخيص المهني.'],
            ],
            'stepsTitleEn' => 'Graduation Clearance Steps',
            'stepsTitleAr' => 'خطوات استكمال التخرج',
            'steps' => [
                ['titleEn' => 'Complete Course Requirements', 'titleAr' => 'استكمال متطلبات المساقات', 'bodyEn' => 'Successfully pass all required courses, credit hours, and practical training as specified by the faculty study plan.', 'bodyAr' => 'اجتياز جميع المساقات المطلوبة والساعات المعتمدة والتدريب العملي بنجاح حسب الخطة الدراسية للكلية.'],
                ['titleEn' => 'Clear Financial Obligations', 'titleAr' => 'تسوية الالتزامات المالية', 'bodyEn' => 'Settle all tuition fees and any outstanding financial dues to the university.', 'bodyAr' => 'تسديد جميع الرسوم الدراسية وأي مستحقات مالية متبقية للجامعة.'],
                ['titleEn' => 'Submit Graduation Documents', 'titleAr' => 'تقديم وثائق التخرج', 'bodyEn' => 'Submit all required graduation documents including the graduation notice, academic status document, and other forms to the faculty administration.', 'bodyAr' => 'تقديم جميع وثائق التخرج المطلوبة بما في ذلك إشعار التخرج ووثيقة الحالة الأكاديمية والنماذج الأخرى إلى إدارة الكلية.'],
                ['titleEn' => 'National Examinations', 'titleAr' => 'الامتحانات الوطنية', 'bodyEn' => 'Pass the national examinations required for certain professional programs as mandated by the Ministry of Higher Education.', 'bodyAr' => 'اجتياز الامتحانات الوطنية المطلوبة لبعض البرامج المهنية وفقاً لما تحدده وزارة التعليم العالي.'],
                ['titleEn' => 'Graduation Approval', 'titleAr' => 'الموافقة على التخرج', 'bodyEn' => 'Upon fulfilling all requirements, the faculty council recommends graduation and the university issues the official graduation certificate and transcript.', 'bodyAr' => 'عند استيفاء جميع المتطلبات، يوصي مجلس الكلية بالتخرج وتصدر الجامعة شهادة التخرج الرسمية وكشف الدرجات.'],
            ],
            'seoDescriptionEn' => 'Learn about SPU graduation requirements, national examinations, and the steps to complete your degree.',
            'seoDescriptionAr' => 'تعرف إلى متطلبات التخرج والامتحانات الوطنية وخطوات استكمال الدرجة في الجامعة السورية الخاصة.',
        ];
    }

    private function localized(mixed $value, string $locale): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->localized($item, $locale), $value);
        }

        if (array_key_exists('en', $value) && array_key_exists('ar', $value)) {
            return $value[$locale] ?? $value['ar'];
        }

        $localized = [];

        foreach ($value as $key => $item) {
            $key = (string) $key;

            if (str_ends_with($key, 'En') || str_ends_with($key, 'Ar')) {
                continue;
            }

            $localized[$key] = $this->localized($item, $locale);
        }

        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        foreach ($value as $key => $item) {
            $key = (string) $key;

            if (str_ends_with($key, $suffix)) {
                $localized[lcfirst(substr($key, 0, -2))] = $this->localized($item, $locale);
            }
        }

        return $localized;
    }

    private function normalizeUrls(mixed $value, string $locale): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeUrls($item, $locale), $value);
        }

        if (! is_string($value) || ! str_starts_with($value, '/')) {
            return $value;
        }

        if (str_starts_with($value, '/images/') || str_starts_with($value, '/fonts/') || str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, '/ar/') || str_starts_with($value, '/en/')) {
            return $value;
        }

        return '/'.$locale.$value;
    }

    /** @param array<string, mixed> $landing
     * @return array<string, mixed>
     */
    private function sanitizeLanding(array $landing, string $locale): array
    {
        $hero = is_array($landing['hero'] ?? null) ? $landing['hero'] : [];
        if ($this->containsKnownAdmissionsPlaceholder($hero['badgeValue'] ?? null)) {
            $landing['hero']['badgeValue'] = $locale === 'ar'
                ? 'تواصل مع القبول لمعرفة حالة التقديم الحالية'
                : 'Contact Admissions for current availability';
        }

        $timeline = is_array($landing['timeline'] ?? null) ? $landing['timeline'] : [];
        if ($this->containsKnownAdmissionsPlaceholder($timeline)) {
            $landing['timeline']['primaryDeadline'] = '';
            $landing['timeline']['phases'] = [];
            $landing['timeline']['primaryDeadlineDesc'] = $locale === 'ar'
                ? 'تنشر مواعيد القبول بعد اعتمادها رسمياً فقط. راجع إعلانات الجامعة أو تواصل مع مديرية القبول.'
                : 'Admission dates are published only after official approval. Review university announcements or contact Admissions.';
        }

        return $this->removeInertAdmissionsUrls($landing);
    }

    /** @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private function sanitizeSection(string $slug, array $section, string $locale): array
    {
        if ($slug === 'tuition') {
            $section['feeRows'] = array_values(array_filter(
                is_array($section['feeRows'] ?? null) ? $section['feeRows'] : [],
                fn (mixed $row): bool => is_array($row) && ! $this->containsKnownAdmissionsPlaceholder($row),
            ));
            $section['methods'] = array_values(array_filter(
                is_array($section['methods'] ?? null) ? $section['methods'] : [],
                fn (mixed $method): bool => is_array($method)
                    && ! $this->containsKnownAdmissionsPlaceholder($method)
                    && (! isset($method['ctaUrl']) || $this->isSafePaymentUrl($method['ctaUrl'])),
            ));
            $section['availabilityGuidance'] ??= $locale === 'ar'
                ? 'لا توجد مبالغ رسوم معتمدة منشورة حالياً. تواصل مع مديرية القبول أو المديرية المالية قبل إجراء أي دفعة.'
                : 'Verified tuition amounts are not currently published. Contact Admissions or Finance before making any payment.';
            $section['paymentGuidance'] ??= $locale === 'ar'
                ? 'استخدم تعليمات الدفع الصادرة مباشرة عن الجامعة فقط.'
                : 'Use only payment instructions issued directly by SPU.';
        }

        if ($slug === 'calendar') {
            if ($this->containsKnownAdmissionsPlaceholder($section['deadlines'] ?? null)) {
                $section['deadlines'] = [];
            }
            if ($this->containsKnownAdmissionsPlaceholder($section['semesters'] ?? null)) {
                $section['semesters'] = [];
            }
            if ($this->containsKnownAdmissionsPlaceholder($section['title'] ?? null)) {
                $section['title'] = $locale === 'ar' ? 'التقويم الأكاديمي' : 'Academic Calendar';
            }
            $section['scheduleGuidance'] ??= $locale === 'ar'
                ? 'لا توجد مواعيد أكاديمية معتمدة منشورة حالياً. راجع إعلانات الجامعة الرسمية.'
                : 'No approved academic dates are currently published. Check official university announcements.';
            $section['download'] = $this->verifiedDownload(is_array($section['download'] ?? null) ? $section['download'] : []);
        }

        if ($slug === 'documents') {
            foreach (($section['tabs'] ?? []) as $tabIndex => $tab) {
                if (! is_array($tab) || ! is_array($tab['subTabs'] ?? null)) {
                    continue;
                }
                foreach ($tab['subTabs'] as $subIndex => $subTab) {
                    if (is_array($subTab)) {
                        $section['tabs'][$tabIndex]['subTabs'][$subIndex]['download'] = $this->verifiedDownload(
                            is_array($subTab['download'] ?? null) ? $subTab['download'] : [],
                        );
                    }
                }
            }
            $section['downloadGuidance'] ??= $locale === 'ar'
                ? 'لا يتوفر حالياً ملف قبول موثق للتنزيل. أكد المتطلبات الحالية مع مديرية القبول.'
                : 'No verified admissions file is currently available. Confirm current requirements with Admissions.';
        }

        if ($slug === 'how-to-apply') {
            foreach (($section['steps'] ?? []) as $index => $step) {
                if (is_array($step) && $this->isHowToApplySelfLoop($step['href'] ?? null, $locale)) {
                    $section['steps'][$index]['href'] = '/'.$locale.'/admissions/how-to-apply#application';
                }
            }
            $section['applicationTitle'] ??= $locale === 'ar' ? 'طلب القبول' : 'Admissions Application';
            $section['applicationGuidance'] ??= $locale === 'ar'
                ? 'إرسال النموذج لا يحجز مقعداً ولا يعد قرار قبول. ستتواصل مديرية القبول معك بشأن الخطوات التالية.'
                : 'Submitting the form does not reserve a seat or constitute admission. Admissions will contact you about next steps.';
        }

        return $this->removeInertAdmissionsUrls($section);
    }

    /** @param array<string, mixed> $download
     * @return array<string, mixed>
     */
    private function verifiedDownload(array $download): array
    {
        $mediaId = is_numeric($download['mediaId'] ?? null) ? (int) $download['mediaId'] : 0;
        $href = is_string($download['href'] ?? null) ? trim($download['href']) : '';

        return $mediaId > 0 && $href !== '' && $href !== '#' && $this->mediaService->publicDocumentsArePublishable([$mediaId])
            ? $download
            : [];
    }

    private function isHowToApplySelfLoop(mixed $url, string $locale): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return in_array($path, ['/admissions/how-to-apply', '/admissions/how-to-apply/', '/'.$locale.'/admissions/how-to-apply', '/'.$locale.'/admissions/how-to-apply/'], true)
            && $fragment !== 'application';
    }

    private function isSafePaymentUrl(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '' || trim($url) === '#') {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! str_contains(mb_strtolower($url), 'example.');
    }

    private function containsKnownAdmissionsPlaceholder(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsKnownAdmissionsPlaceholder($item)) {
                    return true;
                }
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        $known = [
            '#', 'applications open', 'التقديم مفتوح', 'main national bank', 'المصرف الوطني الرئيسي',
            'sy12345678901234567890', '$15,000', '$13,500', '$500', '$300', '$250 (lab)', '$350 (materials)',
            '15 aug 2026', '15 آب 2026', '01 jan 2026', '01 كانون الثاني 2026', '2026/2027',
            'sept 15, 2026', 'sept 1, 2026', 'jan 10, 2027', 'fall 2026', 'spring 2027',
            'pdf, 2.4 mb', 'pdf, 1.2 mb', 'pdf, 280 kb', 'pdf, 310 kb', 'pdf, 295 kb', 'pdf, 340 kb',
        ];

        return in_array($normalized, $known, true)
            || str_contains($normalized, 'lorem ipsum')
            || str_contains($normalized, 'placeholder')
            || str_contains($normalized, 'example.com');
    }

    private function removeInertAdmissionsUrls(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $itemKey => $item) {
                $clean[$itemKey] = $this->removeInertAdmissionsUrls($item, is_string($itemKey) ? $itemKey : null);
            }

            return $clean;
        }

        if (! is_string($value) || $key === null || ! preg_match('/(?:url|href)$/i', $key)) {
            return $value;
        }

        $url = trim($value);

        return $url === '#' || str_starts_with(mb_strtolower($url), 'javascript:') ? '' : $value;
    }
}
