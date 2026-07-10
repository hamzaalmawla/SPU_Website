<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\DTOs\About\AboutContentPageDTO;
use App\DTOs\About\AboutLandingDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\PersonDTO;
use App\Models\Content\Directorate;
use App\Models\Content\DirectorateTranslation;
use App\Models\Content\Partnership;
use App\Models\Content\PartnershipTranslation;
use App\Models\Page\AboutPage;
use App\Models\Page\AboutPageTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use Illuminate\Support\Collection;

final class AboutPageService implements AboutPageServiceInterface
{
    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly CmsTargetRegistryInterface $targetRegistry,
    ) {}

    public function getAboutLanding(string $locale): AboutLandingDTO
    {
        $published = $this->publishedLocalizedPayload('about.landing', $locale);

        if ($published !== null) {
            return $this->landingDto($locale, $published);
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', 'about')
            ->with('translations')
            ->firstOrFail();
        $translation = $this->aboutTranslation($page, $locale);
        $payload = is_array($page->payload_json) ? $page->payload_json : [];

        return $this->landingDto($locale, $this->landingPayloadFromPage($page, $locale));
    }

    public function buildPreviewAboutLanding(string $locale, array $content): AboutLandingDTO
    {
        return $this->landingDto($locale, $content);
    }

    public function getEditablePayload(string $targetKey): array
    {
        if (! in_array($targetKey, ['about.landing', 'about.history', 'about.leadership', 'about.directorates', 'about.partnerships', 'about.directorates_staff', 'about.quality-policy', 'about.ethical-charter', 'about.organizational-structure', 'about.accreditation', 'about.why-spu'], true)) {
            throw new \InvalidArgumentException('Unsupported about target.');
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

        if ($targetKey === 'about.landing') {
            $page = AboutPage::query()
                ->public()
                ->where('slug', 'about')
                ->with('translations')
                ->firstOrFail();

            return [
                'translations' => [
                    'ar' => $this->landingPayloadFromPage($page, 'ar'),
                    'en' => $this->landingPayloadFromPage($page, 'en'),
                ],
            ];
        }

        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            throw new \InvalidArgumentException('Unsupported about target.');
        }

        if ($slug === 'directorates_staff') {
            return [
                'translations' => [
                    'ar' => $this->staffDirectoryPayload('ar'),
                    'en' => $this->staffDirectoryPayload('en'),
                ],
            ];
        }

        $fallback = $this->importedContentPayload($slug);

        if ($fallback !== null) {
            return [
                'translations' => [
                    'ar' => $this->localizedImportedPayload($fallback, 'ar'),
                    'en' => $this->localizedImportedPayload($fallback, 'en'),
                ],
            ];
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', $slug)
            ->with('translations')
            ->firstOrFail();

        return [
            'translations' => [
                'ar' => $this->contentPayloadFromPage($page, 'ar'),
                'en' => $this->contentPayloadFromPage($page, 'en'),
            ],
        ];
    }

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO
    {
        $published = $this->publishedLocalizedPayload('about.'.$slug, $locale);

        if ($published !== null) {
            return $this->contentPageDto($slug, $locale, $published);
        }

        $fallback = $this->importedContentPayload($slug);

        if ($fallback !== null) {
            return $this->contentPageDto($slug, $locale, $this->localizedImportedPayload($fallback, $locale));
        }

        $page = AboutPage::query()
            ->public()
            ->where('slug', $slug)
            ->with('translations')
            ->first();

        if (! $page instanceof AboutPage) {
            return null;
        }

        return $this->contentPageDto($slug, $locale, $this->contentPayloadFromPage($page, $locale));
    }

    public function buildPreviewContentPage(string $targetKey, string $locale, array $content): ?AboutContentPageDTO
    {
        $slug = $this->slugFromTargetKey($targetKey);

        if ($slug === null) {
            return null;
        }

        return $this->contentPageDto($slug, $locale, $content);
    }

    public function getStaffDirectoryPage(string $locale): AboutContentPageDTO
    {
        $published = $this->publishedLocalizedPayload('about.directorates_staff', $locale);

        return $this->contentPageDto('directorates_staff', $locale, $published ?? $this->staffDirectoryPayload($locale));
    }

    public function getLeadershipProfiles(string $locale): Collection
    {
        return $this->mapPersons(
            Person::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getDirectorates(string $locale): Collection
    {
        return $this->mapDirectorates(
            Directorate::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO
    {
        $directorate = Directorate::query()->enabled()->where('slug', $slug)->with('translations')->first();

        if (! $directorate instanceof Directorate) {
            return null;
        }

        return $this->mapDirectorate($directorate, $locale);
    }

    public function getPartnerships(string $locale): Collection
    {
        return $this->mapPartnerships(
            Partnership::query()->enabled()->with('translations')->orderBy('sort_order')->get(),
            $locale,
        );
    }

    /** @return array<int, array<string, string>> */
    public function getAboutSubPages(string $locale): array
    {
        return $this->buildAboutNavigationCards($locale);
    }

    public function mapPerson(Person $person, string $locale): PersonDTO
    {
        $translation = $this->personTranslation($person, $locale);

        return new PersonDTO(
            id: (int) $person->getKey(),
            slug: (string) $person->slug,
            name: (string) $translation->name,
            role: (string) $translation->role,
            category: $person->category,
            facultySlug: $person->faculty_scope_slug,
            bio: $translation->bio,
            quote: $translation->quote,
            image: $person->image,
            email: $person->email,
            profileUrl: $person->profile_url,
        );
    }

    /** @param Collection<int, Person> $persons @return Collection<int, PersonDTO> */
    private function mapPersons(Collection $persons, string $locale): Collection
    {
        return $persons->map(fn (Person $person): PersonDTO => $this->mapPerson($person, $locale))->values();
    }

    /** @param Collection<int, Directorate> $directorates @return Collection<int, DirectorateDTO> */
    private function mapDirectorates(Collection $directorates, string $locale): Collection
    {
        return $directorates->map(fn (Directorate $directorate): DirectorateDTO => $this->mapDirectorate($directorate, $locale))->values();
    }

    private function mapDirectorate(Directorate $directorate, string $locale): DirectorateDTO
    {
        $translation = $this->directorateTranslation($directorate, $locale);

        return new DirectorateDTO(
            id: (int) $directorate->getKey(),
            slug: (string) $directorate->slug,
            title: (string) $translation->title,
            summary: (string) ($translation->summary ?? ''),
            description: (string) ($translation->description ?? ''),
            services: is_array($translation->services_json) ? $translation->services_json : [],
            icon: $directorate->icon,
            email: $directorate->email,
            location: $directorate->location,
        );
    }

    /** @param Collection<int, Partnership> $partnerships @return Collection<int, PartnershipDTO> */
    private function mapPartnerships(Collection $partnerships, string $locale): Collection
    {
        return $partnerships->map(function (Partnership $partnership) use ($locale): PartnershipDTO {
            $translation = $this->partnershipTranslation($partnership, $locale);

            return new PartnershipDTO(
                id: (int) $partnership->getKey(),
                name: (string) $translation->name,
                category: (string) ($translation->category ?? ''),
                status: (string) ($translation->status ?? ''),
                establishedLabel: (string) ($translation->established_label ?? ''),
                description: (string) ($translation->description ?? ''),
                logo: $partnership->logo,
                websiteUrl: $partnership->website_url,
                scope: $translation->scope,
                signedAt: $partnership->signed_at?->toDateString(),
            );
        })->values();
    }

    /** @param array<string, mixed> $content */
    private function landingDto(string $locale, array $content): AboutLandingDTO
    {
        return new AboutLandingDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            title: (string) ($content['title'] ?? ''),
            headline: (string) ($content['headline'] ?? ($content['title'] ?? '')),
            summary: (string) ($content['summary'] ?? ''),
            quote: (string) ($content['quote'] ?? ''),
            description: (string) ($content['description'] ?? ''),
            badge: (string) ($content['badge'] ?? ($content['title'] ?? '')),
            imagePrimary: (string) ($content['imagePrimary'] ?? '/images/about-hero-1.webp'),
            imageSecondary: (string) ($content['imageSecondary'] ?? '/images/about-hero-2.webp'),
            stats: $this->listValue($content, 'stats'),
            storyItems: $this->listValue($content, 'storyItems'),
            highlights: $this->listValue($content, 'highlights'),
            subPages: $this->buildAboutNavigationCards($locale),
        );
    }

    /** @return array<string, mixed> */
    private function landingPayloadFromPage(AboutPage $page, string $locale): array
    {
        $translation = $this->aboutTranslation($page, $locale);
        $payload = is_array($page->payload_json) ? $page->payload_json : [];

        return [
            'title' => (string) $translation->title,
            'headline' => (string) ($translation->headline ?: $translation->title),
            'summary' => (string) ($translation->summary ?? ''),
            'quote' => (string) ($payload[$locale]['quote'] ?? ''),
            'description' => (string) ($payload[$locale]['description'] ?? ''),
            'badge' => (string) ($payload[$locale]['badge'] ?? $translation->title),
            'imagePrimary' => (string) ($payload['images']['primary'] ?? '/images/about-hero-1.webp'),
            'imageSecondary' => (string) ($payload['images']['secondary'] ?? '/images/about-hero-2.webp'),
            'stats' => $this->localizedArray(is_array($payload['stats'] ?? null) ? $payload['stats'] : [], $locale),
            'storyItems' => $this->localizedArray(is_array($payload['story_items'] ?? null) ? $payload['story_items'] : [], $locale),
            'highlights' => $this->localizedArray(is_array($payload['highlights'] ?? null) ? $payload['highlights'] : [], $locale),
            'subPages' => $this->localizedArray(is_array($payload['sub_pages'] ?? null) ? $payload['sub_pages'] : [], $locale),
        ];
    }

    /** @param array<string, mixed> $content */
    private function contentPageDto(string $slug, string $locale, array $content): AboutContentPageDTO
    {
        return new AboutContentPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            slug: $slug,
            title: (string) ($content['title'] ?? ''),
            headline: (string) ($content['headline'] ?? ($content['title'] ?? '')),
            summary: (string) ($content['summary'] ?? ''),
            heroImage: (string) ($content['heroImage'] ?? '/images/about-hero-2.webp'),
            sections: is_array($content['sections'] ?? null) ? $content['sections'] : [],
        );
    }

    /** @return array<string, mixed> */
    private function contentPayloadFromPage(AboutPage $page, string $locale): array
    {
        $translation = $this->aboutTranslation($page, $locale);
        $slug = (string) $page->slug;

        return [
            'title' => (string) $translation->title,
            'headline' => (string) ($translation->headline ?: $translation->title),
            'summary' => (string) ($translation->summary ?? ''),
            'heroImage' => (string) ($page->hero_image ?: '/images/about-hero-2.webp'),
            'sections' => $slug === 'history'
                ? $this->historySections($locale)
                : (is_array($translation->sections_json) ? $translation->sections_json : []),
        ];
    }

    /** @return array<string, mixed>|null */
    private function slugFromTargetKey(string $targetKey): ?string
    {
        if (! str_starts_with($targetKey, 'about.') || $targetKey === 'about.landing') {
            return null;
        }

        return match (substr($targetKey, strlen('about.'))) {
            'history' => 'history',
            'leadership' => 'leadership',
            'directorates' => 'directorates',
            'directorates_staff' => 'directorates_staff',
            'partnerships' => 'partnerships',
            'quality-policy' => 'quality-policy',
            'ethical-charter' => 'ethical-charter',
            'organizational-structure' => 'organizational-structure',
            'accreditation' => 'accreditation',
            'why-spu' => 'why-spu',
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function importedContentPayload(string $slug): ?array
    {
        return match ($slug) {
            'quality-policy' => [
                'titleEn' => 'Quality Policy at SPU',
                'titleAr' => 'سياسة الجودة في الجامعة السورية الخاصة',
                'headlineEn' => 'Quality Policy at SPU',
                'headlineAr' => 'سياسة الجودة في الجامعة السورية الخاصة',
                'summaryEn' => 'SPU is committed to a comprehensive quality policy that supports continuous improvement across academic, administrative, and research activities.',
                'summaryAr' => 'تلتزم الجامعة السورية الخاصة بسياسة جودة شاملة تدعم التحسين المستمر في الأنشطة الأكاديمية والإدارية والبحثية.',
                'heroImage' => '/images/about/hero-img.jpg',
                'sections' => [
                    ['titleEn' => 'Academic Excellence', 'titleAr' => 'التميز الأكاديمي', 'bodyEn' => 'Continuous review and development of curricula to meet evolving educational standards and labor market requirements.', 'bodyAr' => 'المراجعة والتطوير المستمر للمناهج لتلبية المعايير التعليمية المتطورة ومتطلبات سوق العمل.'],
                    ['titleEn' => 'Administrative Efficiency', 'titleAr' => 'الكفاءة الإدارية', 'bodyEn' => 'Streamlined administrative processes with clear procedures, accountability, and regular performance evaluation.', 'bodyAr' => 'عمليات إدارية مبسطة مع إجراءات واضحة ومساءلة وتقييم دوري للأداء.'],
                    ['titleEn' => 'Student Satisfaction', 'titleAr' => 'رضا الطلاب', 'bodyEn' => 'Regular assessment of student feedback to improve services, learning environments, and support systems.', 'bodyAr' => 'تقييم منتظم لملاحظات الطلاب لتحسين الخدمات وبيئات التعلم وأنظمة الدعم.'],
                    ['titleEn' => 'Continuous Improvement', 'titleAr' => 'التحسين المستمر', 'bodyEn' => 'Adoption of best practices and innovative approaches to enhance institutional performance and outcomes.', 'bodyAr' => 'اعتماد أفضل الممارسات والأساليب المبتكرة لتعزيز الأداء المؤسسي والنتائج.'],
                ],
            ],
            'ethical-charter' => [
                'titleEn' => 'Ethical Charter of SPU',
                'titleAr' => 'الميثاق الأخلاقي للجامعة السورية الخاصة',
                'headlineEn' => 'Ethical Charter of SPU',
                'headlineAr' => 'الميثاق الأخلاقي للجامعة السورية الخاصة',
                'summaryEn' => 'The Ethical Charter defines the values and principles that guide students, faculty, administrators, and staff.',
                'summaryAr' => 'يحدد الميثاق الأخلاقي القيم والمبادئ التي توجه الطلاب وأعضاء الهيئة التدريسية والإداريين والموظفين.',
                'heroImage' => '/images/about/hero-img.jpg',
                'sections' => [
                    ['titleEn' => 'Academic Integrity', 'titleAr' => 'النزاهة الأكاديمية', 'bodyEn' => 'Honesty and fairness in all academic work, including teaching, research, examinations, and grading.', 'bodyAr' => 'الصدق والإنصاف في جميع الأعمال الأكاديمية، بما في ذلك التدريس والبحث والامتحانات والتصحيح.'],
                    ['titleEn' => 'Transparency', 'titleAr' => 'الشفافية', 'bodyEn' => 'Open communication and clear disclosure of policies, procedures, and decisions affecting the university community.', 'bodyAr' => 'التواصل المفتوح والإفصاح الواضح عن السياسات والإجراءات والقرارات التي تؤثر على مجتمع الجامعة.'],
                    ['titleEn' => 'Respect & Diversity', 'titleAr' => 'الاحترام والتنوع', 'bodyEn' => 'Respect for the dignity, rights, and diversity of all individuals regardless of background, belief, or affiliation.', 'bodyAr' => 'احترام كرامة وحقوق وتنوع جميع الأفراد بغض النظر عن خلفياتهم أو معتقداتهم أو انتماءاتهم.'],
                    ['titleEn' => 'Social Responsibility', 'titleAr' => 'المسؤولية الاجتماعية', 'bodyEn' => 'Commitment to serving the community and contributing to societal development through knowledge and expertise.', 'bodyAr' => 'الالتزام بخدمة المجتمع والمساهمة في التنمية المجتمعية من خلال المعرفة والخبرة.'],
                ],
            ],
            'organizational-structure' => [
                'titleEn' => 'Organizational Structure of SPU',
                'titleAr' => 'الهيكل التنظيمي للجامعة السورية الخاصة',
                'headlineEn' => 'Organizational Structure of SPU',
                'headlineAr' => 'الهيكل التنظيمي للجامعة السورية الخاصة',
                'summaryEn' => 'SPU operates within a defined structure that supports governance, clear authority, and academic and administrative services.',
                'summaryAr' => 'تعمل الجامعة السورية الخاصة ضمن هيكل محدد يدعم الحوكمة ووضوح الصلاحيات والخدمات الأكاديمية والإدارية.',
                'heroImage' => '/images/about/hero-img.jpg',
                'sections' => [
                    ['titleEn' => 'University Council', 'titleAr' => 'مجلس الجامعة', 'bodyEn' => 'The highest governing body responsible for strategic direction, policy approval, and institutional oversight.', 'bodyAr' => 'أعلى هيئة حاكمة مسؤولة عن التوجه الاستراتيجي واعتماد السياسات والإشراف المؤسسي.'],
                    ['titleEn' => 'University President', 'titleAr' => 'رئيس الجامعة', 'bodyEn' => 'The chief executive officer who leads the university\'s academic and administrative operations.', 'bodyAr' => 'الرئيس التنفيذي الذي يقود العمليات الأكاديمية والإدارية للجامعة.'],
                    ['titleEn' => 'Vice Presidents', 'titleAr' => 'نواب الرئيس', 'bodyEn' => 'Senior administrators overseeing academic affairs, administrative affairs, research, and student development.', 'bodyAr' => 'إداريون كبار يشرفون على الشؤون الأكاديمية والإدارية والبحث العلمي وتطوير الطلاب.'],
                    ['titleEn' => 'Faculties & Directorates', 'titleAr' => 'الكليات والمديريات', 'bodyEn' => 'Academic faculties deliver degree programs while central directorates provide administrative and support services.', 'bodyAr' => 'تقدم الكليات الأكاديمية برامج الدرجات العلمية بينما تقدم المديريات المركزية الخدمات الإدارية والداعمة.'],
                ],
            ],
            'accreditation' => [
                'titleEn' => 'Accreditation & Quality Assurance',
                'titleAr' => 'الاعتماد وضمان الجودة',
                'headlineEn' => 'Accreditation & Quality Assurance',
                'headlineAr' => 'الاعتماد وضمان الجودة',
                'summaryEn' => 'SPU holds official accreditation from the Syrian Ministry of Higher Education and Scientific Research, with licensed academic programs and ongoing quality review.',
                'summaryAr' => 'تحصل الجامعة السورية الخاصة على الاعتماد الرسمي من وزارة التعليم العالي والبحث العلمي، مع برامج أكاديمية مرخصة ومراجعة جودة مستمرة.',
                'heroImage' => '/images/about/hero-img.jpg',
                'sections' => [
                    ['titleEn' => 'Program Licensing', 'titleAr' => 'ترخيص البرامج', 'bodyEn' => 'Every academic program is licensed after review of curriculum, faculty qualifications, facilities, and learning outcomes.', 'bodyAr' => 'كل برنامج أكاديمي مرخص بعد مراجعة المنهاج ومؤهلات أعضاء الهيئة التدريسية والمرافق ومخرجات التعلم.'],
                    ['titleEn' => 'Periodic Review', 'titleAr' => 'المراجعة الدورية', 'bodyEn' => 'Programs undergo periodic review cycles to ensure continued compliance with national standards and best practices.', 'bodyAr' => 'تخضع البرامج لدورات مراجعة دورية لضمان الامتثال المستمر للمعايير الوطنية وأفضل الممارسات.'],
                    ['titleEn' => 'Faculty Qualifications', 'titleAr' => 'مؤهلات أعضاء الهيئة التدريسية', 'bodyEn' => 'Faculty appointment and promotion follow transparent criteria approved by the Ministry.', 'bodyAr' => 'يتبع تعيين وترقية أعضاء الهيئة التدريسية معايير شفافة معتمدة من الوزارة.'],
                    ['titleEn' => 'Student Assessment', 'titleAr' => 'تقييم الطلاب', 'bodyEn' => 'Assessment methods adhere to university-wide standards, examination regulations, and transparent grading policies.', 'bodyAr' => 'تلتزم طرق التقييم بمعايير الجامعة وأنظمة الامتحانات وسياسات التصحيح الشفافة.'],
                ],
            ],
            'why-spu' => [
                'titleEn' => 'Why Choose Syrian Private University?',
                'titleAr' => 'لماذا تختار الجامعة السورية الخاصة؟',
                'headlineEn' => 'Why Choose Syrian Private University?',
                'headlineAr' => 'لماذا تختار الجامعة السورية الخاصة؟',
                'summaryEn' => 'SPU offers a distinctive educational experience built on accreditation, clinical excellence, research, and student support.',
                'summaryAr' => 'تقدم الجامعة السورية الخاصة تجربة تعليمية متميزة قائمة على الاعتماد والتميز السريري والبحث العلمي ودعم الطلاب.',
                'heroImage' => '/images/about/hero-img.jpg',
                'sections' => [
                    ['titleEn' => 'Accredited Programs', 'titleAr' => 'برامج معتمدة', 'bodyEn' => 'All SPU programs are licensed and periodically reviewed.', 'bodyAr' => 'جميع برامج SPU مرخصة وتخضع للمراجعة الدورية.'],
                    ['titleEn' => 'Clinical Excellence', 'titleAr' => 'التميز السريري', 'bodyEn' => 'SPU operates a university hospital and dental clinics that serve the community and train students.', 'bodyAr' => 'تدير SPU مستشفى جامعياً وعيادات سنية تخدم المجتمع وتدرب الطلاب.'],
                    ['titleEn' => 'Research & Innovation', 'titleAr' => 'البحث والابتكار', 'bodyEn' => 'Active research centers and publications support academic development.', 'bodyAr' => 'تدعم مراكز البحث والمنشورات النشطة التطوير الأكاديمي.'],
                    ['titleEn' => 'Student Support', 'titleAr' => 'دعم الطلاب', 'bodyEn' => 'Comprehensive services include advising, career development, insurance, transport, and activities.', 'bodyAr' => 'تشمل الخدمات الشاملة الإرشاد والتطوير المهني والتأمين والنقل والأنشطة.'],
                ],
            ],
            default => null,
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localizedImportedPayload(array $payload, string $locale): array
    {
        $localized = [];

        foreach ($payload as $key => $value) {
            if (str_ends_with((string) $key, 'En') || str_ends_with((string) $key, 'Ar')) {
                continue;
            }

            $localized[(string) $key] = is_array($value) ? $this->localizedImportedList($value, $locale) : $value;
        }

        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        foreach ($payload as $key => $value) {
            if (str_ends_with((string) $key, $suffix)) {
                $localized[lcfirst(substr((string) $key, 0, -2))] = $value;
            }
        }

        return $localized;
    }

    /** @param array<int|string, mixed> $items @return array<int|string, mixed> */
    private function localizedImportedList(array $items, string $locale): array
    {
        if (array_is_list($items)) {
            return array_map(fn (mixed $item): mixed => is_array($item) ? $this->localizedImportedPayload($item, $locale) : $item, $items);
        }

        return $this->localizedImportedPayload($items, $locale);
    }

    /** @return array<string, mixed> */
    private function staffDirectoryPayload(string $locale): array
    {
        return [
            'title' => $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory',
            'headline' => $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory',
            'summary' => $locale === 'ar' ? 'دليل أعضاء الهيئة الأكاديمية في الجامعة السورية الخاصة.' : 'Directory of SPU academic staff members.',
            'heroImage' => '/images/about-hero-2.webp',
            'sections' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function historySections(string $locale): array
    {
        return [
            'foundingTitle' => $locale === 'ar' ? 'رؤية التأسيس' : 'The Founding Vision',
            'quote' => $locale === 'ar'
                ? 'جامعة تأسست لتعزيز التميز الأكاديمي، والإعداد المهني، والمساهمة الفاعلة في خدمة المجتمع.'
                : 'A university founded to advance academic excellence, professional preparation, and meaningful contribution to society.',
            'body' => $locale === 'ar'
                ? [
                    'انطلقت الجامعة السورية الخاصة من التزام عميق بتطوير التعليم العالي وتعزيز الابتكار الأكاديمي في المنطقة. وقد أدرك المؤسسون الحاجة إلى مؤسسة لا تكتفي بنقل المعرفة، بل تنمي التفكير النقدي والقيادة الأخلاقية والمهارات العملية المتوافقة مع المعايير العالمية.',
                    'منذ نشأتها، صُممت الجامعة لتكون منارة للرصانة الأكاديمية، تجمع بين النظريات الأساسية والتطبيق العملي، بما يضمن إعداد خريجين قادرين على التعامل مع تحديات العالم الحديث وحلها بكفاءة.',
                ]
                : [
                    'Established with a profound commitment to educational innovation, Syrian Private University emerged from a collective vision to elevate the standards of higher education in the region. The founders recognized the critical need for an institution that not only imparted knowledge but also fostered critical thinking, ethical leadership, and practical skills aligned with global standards.',
                    'From its inception, the university was designed to be a beacon of academic rigor, integrating foundational theories with applied practice. This dual approach ensures that graduates are not merely degree holders, but competent professionals ready to engage with and solve the complex challenges of the modern world.',
                ],
            'timelineTitle' => $locale === 'ar' ? 'المسار المؤسسي' : 'Institutional Timeline',
            'timeline' => $locale === 'ar'
                ? [
                    ['year' => '2005', 'title' => 'تأسيس SPU', 'body' => 'افتتحت الجامعة أبوابها رسميا، وأسست كلياتها الأساسية، ووضعت قاعدة لمنهج أكاديمي شامل.'],
                    ['year' => '2010', 'title' => 'التوسع الأكاديمي', 'body' => 'إطلاق برامج اختصاصية جديدة وافتتاح مختبرات بحثية وتعليمية متقدمة.'],
                    ['year' => '2016', 'title' => 'تطوير التعليم التطبيقي', 'body' => 'تحول استراتيجي نحو التعلم الخبروي وبناء شراكات عملية وبرامج تدريب ميداني متينة.'],
                    ['year' => '2026', 'title' => 'التحول الرقمي', 'body' => 'التوجه نحو دمج التقنيات التعليمية المتقدمة ومنصات التعاون الرقمي العالمية.'],
                ]
                : [
                    ['year' => '2005', 'title' => 'Founding of SPU', 'body' => 'The university officially opened its doors, establishing core faculties and laying the groundwork for a comprehensive academic curriculum.'],
                    ['year' => '2010', 'title' => 'Academic Expansion', 'body' => 'Introduction of new specialized degree programs and the inauguration of state-of-the-art research laboratories.'],
                    ['year' => '2016', 'title' => 'Applied Learning Development', 'body' => 'Strategic shift towards experiential learning, fostering deep industry partnerships and establishing robust internship programs.'],
                    ['year' => '2026', 'title' => 'Digital Transformation', 'body' => 'Looking ahead to full integration of advanced educational technologies and global digital collaborative platforms.'],
                ],
            'narratives' => $locale === 'ar'
                ? [
                    ['title' => 'النمو الأكاديمي', 'eyebrow' => 'توسع المناهج', 'body' => 'تطور العرض الأكاديمي عبر السنوات ليشمل طيفا واسعا من الاختصاصات من الهندسة والطب إلى الأعمال والعلوم الإنسانية، ضمن معايير اعتماد صارمة والتزام بالدراسات البينية.'],
                    ['title' => 'التعليم التطبيقي', 'eyebrow' => 'تميز عملي', 'body' => 'شكّل الانتقال من التعليم النظري إلى المنهجية التطبيقية محطة مهمة، عبر الاستثمار في مرافق سريرية وورش هندسية ومراكز محاكاة أعمال تتيح للطلاب بناء هويتهم المهنية قبل التخرج.'],
                    ['title' => 'المساهمة المجتمعية', 'eyebrow' => 'أثر اجتماعي', 'body' => 'خارج حدود الحرم الجامعي، رسخت الجامعة دورها كشريك مدني فاعل من خلال العيادات الطبية والبحث التطبيقي وبرامج خدمة المجتمع.'],
                ]
                : [
                    ['title' => 'Academic Growth', 'eyebrow' => 'Curriculum Expansion', 'body' => 'Over the decades, the academic portfolio has evolved to encompass a diverse range of disciplines, from engineering and medicine to business and the humanities. This growth has been guided by rigorous accreditation standards and a commitment to interdisciplinary studies, ensuring a holistic educational experience.'],
                    ['title' => 'Applied Learning', 'eyebrow' => 'Practical Excellence', 'body' => 'The transition from theoretical instruction to applied methodology marked a significant milestone. Investments in clinical facilities, engineering workshops, and business simulation centers have transformed the campus into a dynamic environment where students actively construct their professional identities before graduation.'],
                    ['title' => 'Community Contribution', 'eyebrow' => 'Social Impact', 'body' => 'Beyond the campus borders, the university has established itself as a vital civic partner. Through free medical clinics, public policy research, and community extension programs, the institution continually reinvests its intellectual capital back into the society it was founded to serve.'],
                ],
            'legacyTitle' => $locale === 'ar' ? 'استمرار الإرث' : 'Continuing the Legacy',
            'legacyBody' => $locale === 'ar'
                ? 'تواصل الجامعة السورية الخاصة البناء على رؤية تأسيسها من خلال تطوير البرامج الأكاديمية، ودعم الطلاب، وتعزيز التعليم التطبيقي، والمساهمة في مستقبل التعليم العالي.'
                : 'Syrian Private University continues to build on its founding vision by strengthening academic programs, supporting students, advancing applied learning, and contributing to the future of higher education.',
        ];
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

    /** @return array<int, array<string, mixed>> */
    private function listValue(array $payload, string $key): array
    {
        return array_values(array_filter(is_array($payload[$key] ?? null) ? $payload[$key] : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, string>> */
    private function localizedArray(array $items, string $locale): array
    {
        return collect($items)->map(static function (array $item) use ($locale): array {
            $localized = [];

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                if (str_ends_with((string) $key, '_ar') || str_ends_with((string) $key, '_en')) {
                    continue;
                }

                $localized[(string) $key] = (string) $value;
            }

            foreach ($item as $key => $value) {
                if (! is_string($value) && ! is_int($value)) {
                    continue;
                }

                $suffix = '_'.$locale;
                if (str_ends_with((string) $key, $suffix)) {
                    $localized[substr((string) $key, 0, -strlen($suffix))] = (string) $value;
                }
            }

            return $localized;
        })->values()->all();
    }

    private function aboutTranslation(AboutPage $page, string $locale): AboutPageTranslation
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', 'ar')
            ?? $page->translations->first();
    }

    /** @return array<int, array{title: string, link: string}> */
    private function buildAboutNavigationCards(string $locale): array
    {
        return $this->targetRegistry->forArea('about')
            ->filter(fn (\App\DTOs\Cms\CmsTargetDTO $target): bool => $target->key !== 'about.landing' && $target->publicPath !== null)
            ->values()
            ->map(fn (\App\DTOs\Cms\CmsTargetDTO $target): array => [
                'title' => __($target->labelKey),
                'link' => $target->publicPath,
            ])
            ->all();
    }

    private function personTranslation(Person $person, string $locale): PersonTranslation
    {
        return $person->translations->firstWhere('locale', $locale)
            ?? $person->translations->firstWhere('locale', 'ar')
            ?? $person->translations->first();
    }

    private function directorateTranslation(Directorate $directorate, string $locale): DirectorateTranslation
    {
        return $directorate->translations->firstWhere('locale', $locale)
            ?? $directorate->translations->firstWhere('locale', 'ar')
            ?? $directorate->translations->first();
    }

    private function partnershipTranslation(Partnership $partnership, string $locale): PartnershipTranslation
    {
        return $partnership->translations->firstWhere('locale', $locale)
            ?? $partnership->translations->firstWhere('locale', 'ar')
            ?? $partnership->translations->first();
    }
}
