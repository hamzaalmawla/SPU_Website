<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\DTOs\Admissions\AdmissionsPageDTO;
use App\DTOs\Admissions\AdmissionsSectionDTO;

final class AdmissionsPageService implements AdmissionsPageServiceInterface
{
    public function getLanding(string $locale): AdmissionsPageDTO
    {
        $landing = $this->localized($this->landingPayload(), $locale);

        return new AdmissionsPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            landing: $this->normalizeUrls($landing, $locale),
            seoTitle: $locale === 'ar' ? 'القبول والتسجيل | الجامعة السورية الخاصة' : 'Admissions | Syrian Private University',
            seoDescription: $locale === 'ar'
                ? 'تعرّف إلى متطلبات القبول وخطوات التقديم والرسوم والدعم المتاح للطلاب الجدد في الجامعة السورية الخاصة.'
                : 'Understand SPU admission requirements, application steps, tuition guidance, and enrollment support.',
            seoImage: '/images/admissions-hero-campus.webp',
        );
    }

    public function getSection(string $slug, string $locale): ?AdmissionsSectionDTO
    {
        $sections = $this->sectionPayloads($locale);
        $payload = $sections[$slug] ?? null;

        if ($payload === null) {
            return null;
        }

        $section = $this->normalizeUrls($this->localized($payload, $locale), $locale);
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
                'primaryUrl' => '/admissions/how-to-apply',
                'ctaSecondaryEn' => 'REQUEST INFORMATION',
                'ctaSecondaryAr' => 'اطلب معلومات',
                'secondaryUrl' => '/contact#admissions-support',
                'badgeLabelEn' => 'Admissions Status Badge',
                'badgeLabelAr' => 'حالة القبول',
                'badgeValueEn' => 'Applications Open',
                'badgeValueAr' => 'التقديم مفتوح',
                'checklistItems' => [
                    ['titleEn' => 'Official Transcripts', 'titleAr' => 'الشهادات الرسمية', 'descEn' => 'Sealed records from all previously attended institutions.', 'descAr' => 'سجلات مختومة من جميع المؤسسات التي التحقت بها سابقاً.'],
                    ['titleEn' => 'Personal Statement', 'titleAr' => 'البيان الشخصي', 'descEn' => 'A 500-word essay articulating your academic intentions.', 'descAr' => 'مقال من 500 كلمة يوضح نواياك الأكاديمية.'],
                ],
                'images' => ['campus' => '/images/admissions-hero-campus.webp', 'students' => '/images/admissions-hero-students.webp'],
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
                'primaryDeadlineEn' => '15 Aug 2026',
                'primaryDeadlineAr' => '15 آب 2026',
                'primaryDeadlineLabelEn' => 'PRIMARY DEADLINE',
                'primaryDeadlineLabelAr' => 'الموعد النهائي الرئيسي',
                'primaryDeadlineDescEn' => 'Review each phase of the admissions journey to better understand the requirements and prepare your application successfully.',
                'primaryDeadlineDescAr' => 'راجع كل مرحلة من رحلة القبول لفهم المتطلبات بشكل أفضل وتحضير طلبك بنجاح.',
                'image' => '/images/admissions-hero-campus.webp',
                'phases' => [
                    ['labelEn' => 'PHASE 1', 'labelAr' => 'المرحلة 1', 'titleEn' => 'Applications Open', 'titleAr' => 'فتح باب التقديم', 'dateEn' => '01 Jan 2026', 'dateAr' => '01 كانون الثاني 2026', 'active' => true],
                    ['labelEn' => 'PHASE 2', 'labelAr' => 'المرحلة 2', 'titleEn' => 'Review Period', 'titleAr' => 'فترة المراجعة', 'dateEn' => '16 Aug - 30 Sep 2026', 'dateAr' => '16 آب - 30 أيلول 2026', 'active' => false],
                    ['labelEn' => 'PHASE 3', 'labelAr' => 'المرحلة 3', 'titleEn' => 'Semester Starts', 'titleAr' => 'بداية الفصل', 'dateEn' => '12 Jan 2027', 'dateAr' => '12 كانون الثاني 2027', 'active' => false],
                ],
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
            'applyUrl' => '/admissions/how-to-apply',
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
            'feeRows' => [
                ['facultyEn' => 'Medicine', 'facultyAr' => 'الطب البشري', 'typeEn' => 'New', 'typeAr' => 'مستجد', 'tuitionFeeEn' => '$15,000', 'tuitionFeeAr' => '$15,000', 'registrationFeeEn' => '$500', 'registrationFeeAr' => '$500', 'additionalFeesEn' => '$250 (Lab)', 'additionalFeesAr' => '$250 (مخابر)', 'notesEn' => 'Includes basic insurance', 'notesAr' => 'يشمل التأمين الأساسي'],
                ['facultyEn' => 'Medicine', 'facultyAr' => 'الطب البشري', 'typeEn' => 'Transfer', 'typeAr' => 'تحويل', 'tuitionFeeEn' => '$15,000', 'tuitionFeeAr' => '$15,000', 'registrationFeeEn' => '$300', 'registrationFeeAr' => '$300', 'additionalFeesEn' => '$250 (Lab)', 'additionalFeesAr' => '$250 (مخابر)', 'notesEn' => '-', 'notesAr' => '-'],
                ['facultyEn' => 'Dentistry', 'facultyAr' => 'طب الأسنان', 'typeEn' => 'New', 'typeAr' => 'مستجد', 'tuitionFeeEn' => '$13,500', 'tuitionFeeAr' => '$13,500', 'registrationFeeEn' => '$500', 'registrationFeeAr' => '$500', 'additionalFeesEn' => '$350 (Materials)', 'additionalFeesAr' => '$350 (مواد)', 'notesEn' => 'Tool kit extra', 'notesAr' => 'عدة الأدوات غير مشمولة'],
            ],
            'emptyStateEn' => 'No tuition rows match the selected filters.',
            'emptyStateAr' => 'لا توجد رسوم مطابقة للفلاتر المحددة.',
            'paymentTitleEn' => 'Payment Methods',
            'paymentTitleAr' => 'طرق الدفع',
            'methods' => [
                [
                    'icon' => 'bank',
                    'titleEn' => 'Bank Transfer',
                    'titleAr' => 'تحويل مصرفي',
                    'descEn' => 'Direct transfer to the university official bank account. Takes 2-3 business days to clear.',
                    'descAr' => 'تحويل مباشر إلى الحساب المصرفي الرسمي للجامعة، وقد يستغرق يومين إلى ثلاثة أيام عمل للتسوية.',
                    'details' => [
                        ['labelEn' => 'Account Name', 'labelAr' => 'اسم الحساب', 'valueEn' => 'Syrian Private University', 'valueAr' => 'الجامعة السورية الخاصة'],
                        ['labelEn' => 'Bank', 'labelAr' => 'المصرف', 'valueEn' => 'Main National Bank', 'valueAr' => 'المصرف الوطني الرئيسي'],
                        ['labelEn' => 'IBAN', 'labelAr' => 'IBAN', 'valueEn' => 'SY12345678901234567890', 'valueAr' => 'SY12345678901234567890'],
                    ],
                ],
                [
                    'icon' => 'card',
                    'titleEn' => 'Online Payment',
                    'titleAr' => 'الدفع الإلكتروني',
                    'descEn' => 'Instant processing via the Student Portal using supported payment cards.',
                    'descAr' => 'معالجة فورية عبر بوابة الطالب باستخدام بطاقات الدفع المدعومة.',
                    'ctaEn' => 'Access Portal',
                    'ctaAr' => 'الدخول إلى البوابة',
                    'ctaUrl' => '#',
                ],
            ],
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
                ['titleEn' => 'Apply at Every Stage', 'titleAr' => 'التقديم في كل مرحلة', 'descEn' => 'Access the application portal immediately from any step in the journey.', 'descAr' => 'ادخل إلى بوابة التقديم مباشرة من أي خطوة في الرحلة.', 'icon' => 'apply'],
            ],
            'guideTitleEn' => 'Step-by-Step Guide', 'guideTitleAr' => 'دليل خطوة بخطوة',
            'steps' => [
                ['number' => '01', 'titleEn' => 'Choose Faculty', 'titleAr' => 'اختر الكلية', 'descEn' => 'Explore our diverse range of faculties and programs to find the perfect fit for your career aspirations.', 'descAr' => 'استكشف كلياتنا وبرامجنا المتنوعة لتجد الخيار الأنسب لطموحاتك المهنية.', 'ctaEn' => 'Explore Programs', 'ctaAr' => 'استكشف البرامج', 'href' => '/facilities/'],
                ['number' => '02', 'titleEn' => 'Review Requirements', 'titleAr' => 'راجع المتطلبات', 'descEn' => 'Ensure you meet the academic criteria and understand the specific prerequisites for your chosen degree program.', 'descAr' => 'تأكد من استيفاء المعايير الأكاديمية وفهم المتطلبات الخاصة بالبرنامج الذي اخترته.', 'ctaEn' => 'View Requirements', 'ctaAr' => 'عرض المتطلبات', 'href' => '/admissions/requirements/'],
                ['number' => '03', 'titleEn' => 'Prepare Documents', 'titleAr' => 'حضّر الوثائق', 'descEn' => 'Gather necessary paperwork, including identification, transcripts, and certificates, to streamline your application.', 'descAr' => 'اجمع الأوراق اللازمة، بما في ذلك الهوية وكشوف العلامات والشهادات، لتسهيل طلبك.', 'ctaEn' => 'Checklist Below', 'ctaAr' => 'قائمة الوثائق', 'href' => '/admissions/documents/'],
                ['number' => '04', 'titleEn' => 'Submit Application', 'titleAr' => 'قدّم الطلب', 'descEn' => 'Complete the online form and upload your prepared documents through our secure portal.', 'descAr' => 'أكمل النموذج الإلكتروني وارفع وثائقك المحضّرة عبر بوابتنا الآمنة.', 'ctaEn' => 'Apply Now', 'ctaAr' => 'قدّم الآن', 'href' => '/admissions/how-to-apply/'],
            ],
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
                    ['qEn' => 'When is the application deadline for the Fall semester?', 'qAr' => 'متى يغلق باب التقديم لفصل الخريف؟', 'aEn' => 'The regular decision deadline is typically July 15, with earlier deadlines for limited-capacity programs.', 'aAr' => 'الموعد النهائي العادي عادة هو 15 تموز، مع مواعيد أبكر للبرامج ذات الطاقة المحدودة.'],
                    ['qEn' => 'Can I apply to multiple programs at once?', 'qAr' => 'هل يمكنني التقديم لعدة برامج في نفس الوقت؟', 'aEn' => 'Yes. Each application must include the complete required documentation.', 'aAr' => 'نعم، ويجب أن يتضمن كل طلب الوثائق المطلوبة كاملة.'],
                    ['qEn' => 'How long does an admission decision take?', 'qAr' => 'كم يستغرق قرار القبول؟', 'aEn' => 'Decisions are usually issued within 3-5 weeks after receiving a complete file.', 'aAr' => 'عادة تصدر القرارات خلال 3 إلى 5 أسابيع بعد استلام ملف كامل.'],
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
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Academic Calendar', 'breadcrumbCurrentAr' => 'التقويم الأكاديمي', 'titleEn' => 'Academic Calendar 2026/2027', 'titleAr' => 'التقويم الأكاديمي 2026/2027',
            'statCards' => [
                ['titleEn' => 'Current Academic Year', 'titleAr' => 'العام الأكاديمي الحالي', 'descEn' => 'Comprehensive timeline for Fall 2026 and Spring 2027 semesters.', 'descAr' => 'جدول زمني شامل لفصلي خريف 2026 وربيع 2027.', 'icon' => 'calendar'],
                ['titleEn' => 'Downloadable PDF', 'titleAr' => 'ملف PDF قابل للتنزيل', 'descEn' => 'Prepare your portfolio with our comprehensive checklist.', 'descAr' => 'حضّر ملفك اعتماداً على قائمة الوثائق الشاملة.', 'icon' => 'download'],
                ['titleEn' => 'Key Dates', 'titleAr' => 'تواريخ مهمة', 'descEn' => 'Quick access to essential deadlines, exams, and registration windows.', 'descAr' => 'وصول سريع إلى المواعيد النهائية والامتحانات وفترات التسجيل.', 'icon' => 'key'],
            ],
            'deadlinesTitleEn' => 'Essential Deadlines', 'deadlinesTitleAr' => 'المواعيد الأساسية', 'timelineTitleEn' => 'Detailed Academic Timeline', 'timelineTitleAr' => 'الجدول الأكاديمي التفصيلي',
            'deadlines' => [
                ['typeEn' => 'Classes', 'typeAr' => 'الدروس', 'titleEn' => 'First Day of Classes', 'titleAr' => 'أول يوم دوام', 'dateEn' => 'Sept 15, 2026', 'dateAr' => '15 أيلول 2026'],
                ['typeEn' => 'Registration', 'typeAr' => 'التسجيل', 'titleEn' => 'Registration Opens', 'titleAr' => 'بدء التسجيل', 'dateEn' => 'Sept 1, 2026', 'dateAr' => '1 أيلول 2026'],
                ['typeEn' => 'Exams', 'typeAr' => 'الامتحانات', 'titleEn' => 'Final Exams Begin', 'titleAr' => 'بدء الامتحانات النهائية', 'dateEn' => 'Jan 10, 2027', 'dateAr' => '10 كانون الثاني 2027'],
            ],
            'semesters' => [
                ['titleEn' => 'First Semester (Fall 2026)', 'titleAr' => 'الفصل الأول (خريف 2026)', 'events' => [
                    ['dateEn' => 'Sept 1 - Sept 10', 'dateAr' => '1 - 10 أيلول', 'titleEn' => 'Registration Period', 'titleAr' => 'فترة التسجيل', 'descEn' => 'Course registration for continuing and new students.', 'descAr' => 'تسجيل المقررات للطلاب المستمرين والجدد.'],
                    ['dateEn' => 'Sept 15', 'dateAr' => '15 أيلول', 'titleEn' => 'Classes Begin', 'titleAr' => 'بدء الدوام', 'descEn' => 'Official start of Fall semester classes.', 'descAr' => 'البداية الرسمية لمحاضرات فصل الخريف.'],
                    ['dateEn' => 'Oct 15', 'dateAr' => '15 تشرين الأول', 'titleEn' => 'Add/Drop Deadline', 'titleAr' => 'آخر موعد للإضافة والحذف', 'descEn' => 'Last day to add or drop courses without academic penalty.', 'descAr' => 'آخر يوم لإضافة أو حذف المقررات دون عقوبة أكاديمية.'],
                ]],
                ['titleEn' => 'Second Semester (Spring 2027)', 'titleAr' => 'الفصل الثاني (ربيع 2027)', 'events' => [
                    ['dateEn' => 'Feb 1 - Feb 10', 'dateAr' => '1 - 10 شباط', 'titleEn' => 'Registration Period', 'titleAr' => 'فترة التسجيل', 'descEn' => 'Course registration for continuing and new students.', 'descAr' => 'تسجيل المقررات للطلاب المستمرين والجدد.'],
                ]],
            ],
            'download' => ['titleEn' => 'Download Official Calendar', 'titleAr' => 'تحميل التقويم الرسمي', 'descEn' => 'Get the complete 2026-2027 Academic Calendar in PDF format. (PDF, 2.4 MB)', 'descAr' => 'احصل على تقويم 2026-2027 الأكاديمي كاملاً بصيغة PDF. (PDF، 2.4 MB)', 'buttonEn' => 'Download PDF', 'buttonAr' => 'تحميل PDF', 'href' => '#'],
            'notice' => ['titleEn' => 'Official Notice', 'titleAr' => 'تنبيه رسمي', 'descEn' => 'Dates in this academic calendar are subject to change. The University reserves the right to modify the calendar as necessary. Official announcements regarding any changes will be communicated via university email and posted on the official website.', 'descAr' => 'التواريخ في هذا التقويم الأكاديمي قابلة للتغيير. تحتفظ الجامعة بحق تعديل التقويم عند الضرورة، وسيتم إبلاغ أي تغييرات عبر البريد الجامعي ونشرها على الموقع الرسمي.'],
        ];
    }

    /** @return array<string, mixed> */
    private function documentsPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'lastReviewed' => 'June 2026', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Documents & Checklists', 'breadcrumbCurrentAr' => 'الوثائق وقوائم التحقق', 'titleEn' => 'Documents & Checklists', 'titleAr' => 'الوثائق وقوائم التحقق', 'applyLabelEn' => 'APPLY NOW', 'applyLabelAr' => 'قدّم الآن', 'applyUrl' => '/admissions/how-to-apply', 'requestInfoLabelEn' => 'Request Info', 'requestInfoLabelAr' => 'اطلب معلومات', 'requestInfoUrl' => '/contact', 'requiredLabelEn' => 'Required', 'requiredLabelAr' => 'مطلوب', 'optionalLabelEn' => 'Optional', 'optionalLabelAr' => 'اختياري', 'downloadLabelEn' => 'Download PDF', 'downloadLabelAr' => 'تحميل PDF', 'downloadAllLabelEn' => 'Download All Checklists', 'downloadAllLabelAr' => 'تحميل جميع قوائم التحقق', 'downloadAllDescEn' => 'Get the complete admissions documents checklist in PDF format. (PDF, 1.2 MB)', 'downloadAllDescAr' => 'احصل على قائمة وثائق القبول الكاملة بصيغة PDF. (PDF، 1.2 ميغابايت)', 'lastReviewedLabelEn' => 'Last reviewed', 'lastReviewedLabelAr' => 'آخر مراجعة',
            'tabs' => [
                ['id' => 'checklist', 'labelEn' => 'Admission Checklist', 'labelAr' => 'قائمة القبول', 'subTabs' => [
                    ['id' => 'freshman', 'labelEn' => 'Freshman', 'labelAr' => 'مستجد', 'descEn' => 'These are the documents required for first-time university applicants holding a certified high school diploma or equivalent. All documents must be submitted in original or certified copy form.', 'descAr' => 'هذه هي الوثائق المطلوبة للمتقدمين للجامعة لأول مرة والحاصلين على شهادة الثانوية العامة المصدقة أو ما يعادلها. يجب تقديم جميع الوثائق أصلية أو مصورة مصدقة.', 'download' => ['href' => '#', 'sizeEn' => 'PDF, 280 KB', 'sizeAr' => 'PDF، 280 كيلوبايت'], 'items' => [
                        ['nameEn' => 'Certified High School Diploma', 'nameAr' => 'شهادة الثانوية العامة مصدقة', 'required' => true, 'noteEn' => 'Must be certified by the Ministry of Education.', 'noteAr' => 'يجب أن تكون مصدقة من وزارة التربية.'],
                        ['nameEn' => 'Copy of National ID or Passport', 'nameAr' => 'صورة عن الهوية الشخصية أو جواز السفر', 'required' => true, 'noteEn' => 'Valid and clear photocopy.', 'noteAr' => 'نسخة واضحة وسارية المفعول.'],
                        ['nameEn' => '4 Personal Photos (White Background)', 'nameAr' => '4 صور شخصية (خلفية بيضاء)', 'required' => true, 'noteEn' => 'Recent photos, 4x6 cm.', 'noteAr' => 'صور حديثة، مقاس 4x6 سم.'],
                        ['nameEn' => 'Medical Fitness Certificate', 'nameAr' => 'شهادة اللياقة الطبية', 'required' => true, 'noteEn' => 'From an approved medical center.', 'noteAr' => 'من مركز طبي معتمد.'],
                        ['nameEn' => 'Military Service Status Document (for males)', 'nameAr' => 'وثيقة حالة الخدمة الإلزامية (للذكور)', 'required' => true, 'noteEn' => 'Or postponement document if applicable.', 'noteAr' => 'أو وثيقة التأجيل إن وجدت.'],
                        ['nameEn' => 'Proof of Residence', 'nameAr' => 'إثبات السكن', 'required' => false, 'noteEn' => 'Recent utility bill or rental contract.', 'noteAr' => 'فاتورة خدمات حديثة أو عقد إيجار.'],
                    ]],
                    ['id' => 'transfer', 'labelEn' => 'Transfer', 'labelAr' => 'تحويل', 'descEn' => 'Transfer students must provide documents from their previous institution in addition to standard identification. Credit transfer is subject to faculty review.', 'descAr' => 'يجب على طلاب التحويل تقديم وثائق من مؤسستهم السابقة بالإضافة إلى أوراق الهوية القياسية. يخضع تحويل الساعات لموافقة الكلية.', 'download' => ['href' => '#', 'sizeEn' => 'PDF, 310 KB', 'sizeAr' => 'PDF، 310 كيلوبايت'], 'items' => [
                        ['nameEn' => 'Official University Transcript', 'nameAr' => 'كشف علامات جامعي رسمي', 'required' => true, 'noteEn' => 'Sealed and stamped by the previous university.', 'noteAr' => 'مختوم وموثق من الجامعة السابقة.'],
                        ['nameEn' => 'Course Descriptions / Syllabi', 'nameAr' => 'وصف المساقات / المناهج', 'required' => true, 'noteEn' => 'For all completed courses seeking transfer.', 'noteAr' => 'لجميع المساقات المنجزة المراد تحويلها.'],
                        ['nameEn' => 'High School Certificate Copy', 'nameAr' => 'صورة عن شهادة الثانوية', 'required' => true, 'noteEn' => 'Certified copy.', 'noteAr' => 'نسخة مصدقة.'],
                        ['nameEn' => 'Good Standing / Non-Disciplinary Letter', 'nameAr' => 'خطاب حسن سلوك / عدم تأديبي', 'required' => true, 'noteEn' => 'From the previous university registrar.', 'noteAr' => 'من مسجل الجامعة السابقة.'],
                        ['nameEn' => 'Transfer Credit Evaluation Form', 'nameAr' => 'نموذج تقييم ساعات التحويل', 'required' => false, 'noteEn' => 'Available at the Admissions Office.', 'noteAr' => 'متوفر في مكتب القبول والتسجيل.'],
                    ]],
                    ['id' => 'equivalency', 'labelEn' => 'Equivalency', 'labelAr' => 'معادلة', 'descEn' => 'Applicants with non-Syrian certificates or special-track diplomas must obtain an equivalency decision from the Ministry of Higher Education before final admission.', 'descAr' => 'يجب على المتقدمين الحاصلين على شهادات غير سورية أو ثانويات مسار خاص الحصول على قرار معادلة من وزارة التعليم العالي قبل القبول النهائي.', 'download' => ['href' => '#', 'sizeEn' => 'PDF, 295 KB', 'sizeAr' => 'PDF، 295 كيلوبايت'], 'items' => [
                        ['nameEn' => 'Original External Certificate', 'nameAr' => 'الشهادة الخارجية الأصلية', 'required' => true, 'noteEn' => 'Attested by the issuing country and Syrian embassy.', 'noteAr' => 'مصدقة من دولة الإصدار والسفارة السورية.'],
                        ['nameEn' => 'Certified Arabic Translation', 'nameAr' => 'ترجمة عربية مصدقة', 'required' => true, 'noteEn' => 'By a sworn translator if the certificate is not in Arabic.', 'noteAr' => 'من مترجم قسمي إذا كانت الشهادة ليست بالعربية.'],
                        ['nameEn' => 'Equivalency / Recognition Decision', 'nameAr' => 'قرار المعادلة / المعترف بها', 'required' => true, 'noteEn' => 'From the Syrian Ministry of Higher Education.', 'noteAr' => 'من وزارة التعليم العالي السورية.'],
                        ['nameEn' => 'Passport or National ID Copy', 'nameAr' => 'صورة عن جواز السفر أو الهوية', 'required' => true, 'noteEn' => 'Valid and clear photocopy.', 'noteAr' => 'نسخة واضحة وسارية المفعول.'],
                    ]],
                    ['id' => 'international', 'labelEn' => 'International', 'labelAr' => 'دولي', 'descEn' => 'International applicants should confirm visa status, passport validity, and equivalency requirements before submitting their application.', 'descAr' => 'يجب على المتقدمين الدوليين التأكد من حالة التأشيرة وصلاحية جواز السفر ومتطلبات المعادلة قبل تقديم طلبهم.', 'download' => ['href' => '#', 'sizeEn' => 'PDF, 340 KB', 'sizeAr' => 'PDF، 340 كيلوبايت'], 'items' => [
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
                ], 'scaleRows' => [
                    ['percentageEn' => '90 - 100%', 'percentageAr' => '90 - 100%', 'gpa' => '4.0', 'gradeEn' => 'A', 'gradeAr' => 'أ', 'descriptorEn' => 'Excellent', 'descriptorAr' => 'ممتاز'],
                    ['percentageEn' => '80 - 84%', 'percentageAr' => '80 - 84%', 'gpa' => '3.3', 'gradeEn' => 'B+', 'gradeAr' => 'ب+', 'descriptorEn' => 'Good Plus', 'descriptorAr' => 'جيد +'],
                    ['percentageEn' => '70 - 74%', 'percentageAr' => '70 - 74%', 'gpa' => '2.7', 'gradeEn' => 'B-', 'gradeAr' => 'ب-', 'descriptorEn' => 'Above Average', 'descriptorAr' => 'فوق المتوسط'],
                    ['percentageEn' => '60 - 64%', 'percentageAr' => '60 - 64%', 'gpa' => '2.0', 'gradeEn' => 'C', 'gradeAr' => 'ج', 'descriptorEn' => 'Average', 'descriptorAr' => 'متوسط'],
                    ['percentageEn' => 'Below 50%', 'percentageAr' => 'أقل من 50%', 'gpa' => '0.0', 'gradeEn' => 'F', 'gradeAr' => 'رسوب', 'descriptorEn' => 'Fail', 'descriptorAr' => 'راسب'],
                ], 'notes' => [
                    ['en' => 'The minimum passing grade for any course is 50% (D / 1.0 GPA).', 'ar' => 'الحد الأدنى للنجاح في أي مساق هو 50% (د / 1.0 معدل).'],
                    ['en' => 'GPA is calculated on a cumulative basis across all completed credit hours.', 'ar' => 'يتم حساب المعدل التراكمي على أساس جميع الساعات المعتمدة المنجزة.'],
                    ['en' => 'Some faculties may require higher minimum grades for progression.', 'ar' => 'قد تتطلب بعض الكليات علامات دنيا أعلى للترقي.'],
                ]],
                ['id' => 'warnings', 'labelEn' => 'Academic Warnings', 'labelAr' => 'الإنذارات الأكاديمية', 'introEn' => 'The academic warning system is designed to identify students whose performance falls below satisfactory levels and to provide structured pathways for recovery before dismissal.', 'introAr' => 'تم تصميم نظام الإنذار الأكاديمي لتحديد الطلاب الذين ينخفض أداؤهم عن المستويات المقبولة وتوفير مسارات منظمة للتعافي قبل الفصل.', 'levelsTitleEn' => 'Warning Levels', 'levelsTitleAr' => 'مستويات الإنذار', 'levels' => [
                    ['levelEn' => 'First Warning', 'levelAr' => 'الإنذار الأول', 'thresholdEn' => 'GPA below 2.0', 'thresholdAr' => 'معدل أقل من 2.0', 'consequencesEn' => 'Academic advising mandatory. Student must meet with faculty advisor within 2 weeks.', 'consequencesAr' => 'الإرشاد الأكاديمي إلزامي. يجب على الطالب مقابلة المرشد الأكاديمي خلال أسبوعين.', 'recoveryEn' => 'Raise GPA to 2.0 or above in the following semester to clear the warning.', 'recoveryAr' => 'رفع المعدل إلى 2.0 أو أعلى في الفصل الدراسي التالي لإلغاء الإنذار.'],
                    ['levelEn' => 'Second Warning', 'levelAr' => 'الإنذار الثاني', 'thresholdEn' => 'GPA below 2.0 for two consecutive semesters', 'thresholdAr' => 'معدل أقل من 2.0 لفصلين دراسيين متتاليين', 'consequencesEn' => 'Course load limited to 12 credit hours. Mandatory tutoring and academic support plan.', 'consequencesAr' => 'تحميل المقررات محدد بـ 12 ساعة معتمدة. خطة تأهيل ودعم أكاديمي إلزامية.', 'recoveryEn' => 'Achieve GPA of 2.0 or higher in the next semester and maintain it for one additional semester.', 'recoveryAr' => 'تحقيق معدل 2.0 أو أعلى في الفصل التالي والمحافظة عليه لفصل إضافي.'],
                    ['levelEn' => 'Final Warning / Dismissal', 'levelAr' => 'الإنذار النهائي / الفصل', 'thresholdEn' => 'GPA below 2.0 for three consecutive semesters', 'thresholdAr' => 'معدل أقل من 2.0 لثلاثة فصول دراسية متتالية', 'consequencesEn' => 'Academic dismissal from the faculty. Student may appeal to the Faculty Council within 30 days.', 'consequencesAr' => 'الفصل الأكاديمي من الكلية. يحق للطالب الاستئناف أمام مجلس الكلية خلال 30 يوماً.', 'recoveryEn' => 'Successful appeal with documented extenuating circumstances, or re-admission after one academic year with dean approval.', 'recoveryAr' => 'استئناف ناجح مع ظروف استثنائية موثقة، أو إعادة قبول بعد عام أكاديمي بموافقة العميد.'],
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function transferPayload(): array
    {
        return [
            'heroImage' => '/images/DSC_1015.JPG', 'breadcrumbHomeEn' => 'Home', 'breadcrumbHomeAr' => 'الرئيسية', 'breadcrumbParentEn' => 'Admissions', 'breadcrumbParentAr' => 'القبول والتسجيل', 'breadcrumbCurrentEn' => 'Transfer & International Students', 'breadcrumbCurrentAr' => 'التحويل والطلاب الدوليون', 'titleEn' => 'Transfer & International Students', 'titleAr' => 'التحويل والطلاب الدوليون', 'applyLabelEn' => 'APPLY NOW', 'applyLabelAr' => 'قدّم الآن', 'applyUrl' => '/admissions/how-to-apply', 'requestInfoLabelEn' => 'Request Info', 'requestInfoLabelAr' => 'اطلب معلومات', 'requestInfoUrl' => '/contact#admissions-support', 'requiredLabelEn' => 'Required', 'requiredLabelAr' => 'مطلوب', 'optionalLabelEn' => 'Optional (if Applicable)', 'optionalLabelAr' => 'اختياري (إذا توفر)',
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
}
