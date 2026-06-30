<?php

declare(strict_types=1);

namespace App\Services\Page;

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
        if (! in_array($targetKey, ['about.landing', 'about.history', 'about.leadership', 'about.directorates', 'about.partnerships', 'about.directorates_staff'], true)) {
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

    public function mapPerson(Person $person, string $locale): PersonDTO
    {
        $translation = $this->personTranslation($person, $locale);

        return new PersonDTO(
            id: (int) $person->getKey(),
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
            subPages: $this->listValue($content, 'subPages'),
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
            default => null,
        };
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
