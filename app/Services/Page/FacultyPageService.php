<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Faculty\FacultyCardDTO;
use App\DTOs\Faculty\FacultyDetailPageDTO;
use App\DTOs\Faculty\FacultyHighlightDTO;
use App\DTOs\Faculty\FacultyHubPageDTO;
use App\DTOs\Faculty\FacultyNavigationItemDTO;
use App\DTOs\Faculty\FacultySubpageDTO;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Department;
use App\Models\Faculty\DepartmentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyHighlightTranslation;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyLabTranslation;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyPageTranslation;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultyStudentProjectTranslation;
use App\Models\Faculty\FacultyTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class FacultyPageService implements FacultyPageServiceInterface
{
    private const FACULTY_SLUGS = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    /** @var array<string, string> */
    private const FACULTY_SLUG_ALIASES = [
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    private const SUBPAGE_SLUGS = ['overview', 'departments', 'labs', 'projects', 'alumni', 'valedictorians', 'training'];

    public function getHub(string $locale): FacultyHubPageDTO
    {
        return Cache::remember("public.facilities.hub.{$locale}", now()->addMinutes(30), function () use ($locale): FacultyHubPageDTO {
            $faculties = $this->baseFacultyQuery()->get();

            $content = [
                'hero' => [
                    'title' => $locale === 'ar' ? 'المرافق الأكاديمية' : 'Academic Facilities',
                    'summary' => $locale === 'ar'
                        ? ''
                        : '',
                    'image' => '/images/campus-feature-01.webp',
                    'applyLabel' => $locale === 'ar' ? 'قدم الآن' : 'Apply Now',
                    'applyUrl' => "/{$locale}/admissions/how-to-apply",
                    'campusMapLabel' => $locale === 'ar' ? 'استكشف خريطة الحرم' : 'Explore Campus Map',
                ],
                'facts' => [
                    ['value' => '24', 'label' => $locale === 'ar' ? 'إجمالي البرامج' : 'Total Programs'],
                    ['value' => '7', 'label' => $locale === 'ar' ? 'الأقسام الأكاديمية' : 'Academic Departments'],
                    ['value' => '120', 'label' => $locale === 'ar' ? 'المختبرات' : 'Laboratories'],
                    ['value' => '5k+', 'label' => $locale === 'ar' ? 'الطلاب المسجلون' : 'Enrolled Students'],
                ],
                'model' => [
                    'title' => $locale === 'ar' ? 'نموذج أكاديمي مبني حول الممارسة والبحث' : 'An Academic Model Built Around Practice and Research',
                    'cards' => [
                        ['title' => $locale === 'ar' ? 'التعلم السريري' : 'Clinical Learning', 'summary' => $locale === 'ar' ? 'تدريب عملي مدعوم ببيئات أكاديمية وسريرية ومهنية حقيقية.' : 'Hands-on training supported by real academic, clinical, and professional environments.', 'featured' => true],
                        ['title' => $locale === 'ar' ? 'التعليم التطبيقي' : 'Applied Education', 'summary' => $locale === 'ar' ? 'برامج مصممة لربط المعرفة النظرية بالمهارات العملية وحل المشكلات الواقعية.' : 'Programs designed to connect theoretical knowledge with practical skills and real-world problem solving.', 'featured' => false],
                        ['title' => $locale === 'ar' ? 'التدريس القائم على البحث' : 'Research-Led Teaching', 'summary' => $locale === 'ar' ? 'تعلم يتشكل من خلال البحث الأكاديمي والابتكار والمعرفة القائمة على الأدلة والمخرجات البحثية.' : 'Learning shaped by academic inquiry, innovation, evidence-based knowledge, and research output.', 'featured' => false],
                        ['title' => $locale === 'ar' ? 'الإعداد المهني' : 'Professional Preparation', 'summary' => $locale === 'ar' ? 'مسارات أكاديمية تعد الطلاب للمهن المستقبلية والتدريب والدراسات المتقدمة والممارسة المهنية.' : 'Academic pathways that prepare students for future careers, internships, advanced study, and professional practice.', 'featured' => false],
                        ['title' => $locale === 'ar' ? 'بيئات تعلم حديثة' : 'Modern Learning Environments', 'summary' => $locale === 'ar' ? 'فصول ومختبرات وعيادات وورش ومساحات رقمية تدعم التعلم النشط.' : 'Classrooms, laboratories, clinics, workshops, and digital spaces that support active learning.', 'featured' => false],
                    ],
                ],
            ];

            return new FacultyHubPageDTO(
                locale: $locale,
                direction: $this->direction($locale),
                content: $content,
                faculties: $faculties->map(fn (Faculty $faculty): FacultyCardDTO => $this->cardDto($faculty, $locale))->values(),
                seoTitle: $locale === 'ar' ? 'الكليات | الجامعة السورية الخاصة' : 'Faculties | Syrian Private University',
                seoDescription: (string) $content['hero']['summary'],
                seoImage: '/images/campus-feature-01.webp',
            );
        });
    }

    public function getFaculty(string $facultySlug, string $locale): ?FacultyDetailPageDTO
    {
        $facultySlug = $this->canonicalFacultySlug($facultySlug);

        return Cache::remember("public.facilities.{$facultySlug}.{$locale}", now()->addMinutes(30), function () use ($facultySlug, $locale): ?FacultyDetailPageDTO {
            $faculty = $this->facultyByPublicSlug($facultySlug);

            if (! $faculty instanceof Faculty) {
                return null;
            }

            $page = $faculty->pages->firstWhere('slug', 'overview') ?? $faculty->pages->first();
            $translation = $page instanceof FacultyPage ? $this->pageTranslation($page, $locale) : null;
            $facultyTranslation = $this->facultyTranslation($faculty, $locale);
            $pagePayload = $page instanceof FacultyPage && is_array($page->payload_json) ? $page->payload_json : [];

            return new FacultyDetailPageDTO(
                locale: $locale,
                direction: $this->direction($locale),
                slug: $this->publicSlug($faculty),
                faculty: $this->facultyPayload($faculty, $locale),
                content: [
                    'title' => (string) ($translation?->title ?? $facultyTranslation->name),
                    'summary' => (string) ($translation?->summary ?? $facultyTranslation->short_description ?? ''),
                    'body' => (string) ($translation?->body ?? $facultyTranslation->description ?? ''),
                    'tabs' => $this->overviewTabs($translation, $facultyTranslation, $locale),
                    'sections' => is_array($translation?->sections_json) ? $translation->sections_json : [],
                    'dean' => is_array($pagePayload['dean'] ?? null) ? $pagePayload['dean'] : [],
                    'gallery' => $this->gallery($faculty),
                    'latestResearch' => $this->latestResearch($faculty, $locale),
                ],
                stats: $this->localizedPayloadList($pagePayload['stats'] ?? [], $locale),
                navigation: $this->navigation($faculty, $locale, null),
                highlights: $this->highlights($faculty, $locale),
                seoTitle: (string) ($facultyTranslation->catalog_title ?? $facultyTranslation->name).' | '.($locale === 'ar' ? 'الجامعة السورية الخاصة' : 'SPU'),
                seoDescription: (string) ($facultyTranslation->short_description ?? $translation?->summary ?? ''),
                seoImage: (string) ($faculty->hero_image ?: '/images/uni-main-place.JPG'),
            );
        });
    }

    public function getSubpage(string $facultySlug, string $subpageSlug, string $locale): ?FacultySubpageDTO
    {
        if (! in_array($subpageSlug, self::SUBPAGE_SLUGS, true) || $subpageSlug === 'study-plan') {
            return null;
        }

        $facultySlug = $this->canonicalFacultySlug($facultySlug);

        return Cache::remember("public.facilities.{$facultySlug}.{$subpageSlug}.{$locale}", now()->addMinutes(30), function () use ($facultySlug, $subpageSlug, $locale): ?FacultySubpageDTO {
            $faculty = $this->facultyByPublicSlug($facultySlug);

            if (! $faculty instanceof Faculty) {
                return null;
            }

            if (! $this->isAvailableSubpage($this->publicSlug($faculty), $subpageSlug)) {
                return null;
            }

            $page = $faculty->pages->firstWhere('slug', $subpageSlug);

            if (! $page instanceof FacultyPage) {
                return null;
            }

            $translation = $this->pageTranslation($page, $locale);
            $items = $this->subpageItems($faculty, $subpageSlug, $locale, $page);

            return new FacultySubpageDTO(
                locale: $locale,
                direction: $this->direction($locale),
                facultySlug: $this->publicSlug($faculty),
                subpageSlug: $subpageSlug,
                faculty: $this->facultyPayload($faculty, $locale),
                page: [
                    'title' => (string) $translation->title,
                    'summary' => (string) ($translation->summary ?? ''),
                    'body' => (string) ($translation->body ?? ''),
                    'heroImage' => (string) ($page->hero_image ?: $faculty->hero_image ?: '/images/uni-main-place.JPG'),
                    'sections' => is_array($translation->sections_json) ? $translation->sections_json : [],
                    'payload' => is_array($page->payload_json) ? $this->localizedPayload($page->payload_json, $locale) : [],
                ],
                items: $items,
                navigation: $this->navigation($faculty, $locale, $subpageSlug),
                highlights: $this->highlights($faculty, $locale),
                seoTitle: (string) $translation->title.' | '.(string) $this->facultyTranslation($faculty, $locale)->name,
                seoDescription: (string) ($translation->summary ?? ''),
                seoImage: (string) ($page->hero_image ?: $faculty->hero_image ?: '/images/uni-main-place.JPG'),
            );
        });
    }

    public function facultySlugPattern(): string
    {
        return implode('|', [...self::FACULTY_SLUGS, ...array_keys(self::FACULTY_SLUG_ALIASES)]);
    }

    public function canonicalFacultySlug(string $slug): string
    {
        return self::FACULTY_SLUG_ALIASES[$slug] ?? $slug;
    }

    public function subpageSlugPattern(): string
    {
        return implode('|', self::SUBPAGE_SLUGS);
    }

    private function baseFacultyQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Faculty::query()
            ->enabled()
            ->whereIn('public_slug', self::FACULTY_SLUGS)
            ->with([
                'translations',
                'pages' => fn ($query) => $query->enabled()->with('translations'),
                'highlights' => fn ($query) => $query->enabled()->with('translations'),
                'departments' => fn ($query) => $query->enabled()->with('translations'),
                'labs' => fn ($query) => $query->enabled()->with('translations'),
                'studentProjects' => fn ($query) => $query->enabled()->with('translations'),
                'alumni' => fn ($query) => $query->enabled()->with(['translations', 'department.translations'])->orderByDesc('graduation_year')->limit(24),
                'honorStudents' => fn ($query) => $query->enabled()->with(['translations', 'department.translations'])->limit(24),
            ])
            ->orderBy('sort_order');
    }

    private function facultyByPublicSlug(string $slug): ?Faculty
    {
        return $this->baseFacultyQuery()->where('public_slug', $this->canonicalFacultySlug($slug))->first();
    }

    private function cardDto(Faculty $faculty, string $locale): FacultyCardDTO
    {
        $translation = $this->facultyTranslation($faculty, $locale);
        $slug = $this->publicSlug($faculty);

        return new FacultyCardDTO(
            id: (int) $faculty->getKey(),
            slug: $slug,
            name: (string) $translation->name,
            title: (string) ($translation->catalog_title ?? $translation->name),
            summary: (string) ($translation->short_description ?? ''),
            url: $this->url($locale, "/facilities/{$slug}"),
            heroImage: $faculty->hero_image,
            logoImage: $faculty->logo_image,
            accentColor: $faculty->accent_color,
            yearsLabel: $translation->years_label,
        );
    }

    /** @return array<string, mixed> */
    private function facultyPayload(Faculty $faculty, string $locale): array
    {
        $translation = $this->facultyTranslation($faculty, $locale);
        $arabicTranslation = $this->facultyTranslation($faculty, 'ar');
        $englishTranslation = $this->facultyTranslation($faculty, 'en');
        $slug = $this->publicSlug($faculty);

        return [
            'id' => (int) $faculty->getKey(),
            'slug' => $slug,
            'name' => (string) $translation->name,
            'nameAr' => (string) $arabicTranslation->name,
            'nameEn' => (string) $englishTranslation->name,
            'title' => (string) ($translation->catalog_title ?? $translation->name),
            'summary' => (string) ($translation->short_description ?? ''),
            'description' => (string) ($translation->description ?? ''),
            'yearsLabel' => $translation->years_label,
            'accentColor' => $faculty->accent_color,
            'heroImage' => $faculty->hero_image,
            'logoImage' => $faculty->logo_image,
            'url' => $this->url($locale, "/facilities/{$slug}"),
        ];
    }

    /** @return Collection<int, FacultyNavigationItemDTO> */
    private function navigation(Faculty $faculty, string $locale, ?string $active): Collection
    {
        $slug = $this->publicSlug($faculty);
        $labels = [
            'overview' => $locale === 'ar' ? 'لمحة عامة' : 'Overview',
            'departments' => $locale === 'ar' ? 'الأقسام' : 'Departments',
            'labs' => $locale === 'ar' ? 'المخابر' : 'Laboratories',
            'projects' => $locale === 'ar' ? 'المشاريع' : 'Projects',
            'alumni' => $locale === 'ar' ? 'الخريجون' : 'Alumni',
            'valedictorians' => $locale === 'ar' ? 'قائمة الشرف' : 'Honor List',
            'training' => $locale === 'ar' ? 'التدريب' : 'Training',
        ];

        return $faculty->pages
            ->filter(fn (FacultyPage $page): bool => in_array($page->slug, self::SUBPAGE_SLUGS, true))
            ->filter(fn (FacultyPage $page): bool => $this->isAvailableSubpage($slug, (string) $page->slug))
            ->sortBy('sort_order')
            ->map(fn (FacultyPage $page): FacultyNavigationItemDTO => new FacultyNavigationItemDTO(
                slug: (string) $page->slug,
                label: $labels[$page->slug] ?? (string) $page->slug,
                url: $page->slug === 'overview' ? $this->url($locale, "/facilities/{$slug}/overview") : $this->url($locale, "/facilities/{$slug}/{$page->slug}"),
                isActive: $active === $page->slug,
            ))
            ->values();
    }

    /** @return Collection<int, FacultyHighlightDTO> */
    private function highlights(Faculty $faculty, string $locale): Collection
    {
        return $faculty->highlights->map(function (FacultyHighlight $highlight) use ($locale): FacultyHighlightDTO {
            $translation = $this->highlightTranslation($highlight, $locale);

            return new FacultyHighlightDTO(
                key: (string) $highlight->key,
                title: (string) $translation->title,
                value: $highlight->value,
                summary: $translation->summary,
                icon: $highlight->icon,
                url: $highlight->url,
            );
        })->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function subpageItems(Faculty $faculty, string $subpageSlug, string $locale, FacultyPage $page): array
    {
        return match ($subpageSlug) {
            'departments' => $this->departmentItems($faculty, $locale, $page),
            'labs' => $this->labItems($faculty, $locale),
            'projects' => $this->projectItems($faculty, $locale),
            'alumni' => $this->alumniItems($faculty, $locale),
            'valedictorians' => $this->honorItems($faculty, $locale),
            'training' => $this->localizedPayloadList((is_array($page->payload_json) ? ($page->payload_json['items'] ?? []) : []), $locale),
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function departmentItems(Faculty $faculty, string $locale, FacultyPage $page): array
    {
        $departmentPayloads = collect(is_array($page->payload_json) ? ($page->payload_json['departments'] ?? []) : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['slug']))
            ->keyBy('slug');

        return $faculty->departments->map(function (Department $department) use ($locale, $departmentPayloads): array {
            $translation = $this->departmentTranslation($department, $locale);
            $payload = $departmentPayloads->get((string) $department->slug, []);
            $localeSuffix = ucfirst($locale);

            return [
                'slug' => (string) $department->slug,
                'code' => is_array($payload) ? ($payload['code'] ?? null) : null,
                'title' => (string) $translation->name,
                'summary' => (string) ($translation->description ?? ''),
                'degrees' => is_array($payload) ? ($payload['degrees'.$localeSuffix] ?? null) : null,
                'tags' => is_array($payload) && is_array($payload['tags'] ?? null) ? $payload['tags'] : [],
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function labItems(Faculty $faculty, string $locale): array
    {
        return $faculty->labs->map(function (FacultyLab $lab) use ($locale): array {
            $translation = $this->labTranslation($lab, $locale);

            return [
                'slug' => (string) $lab->slug,
                'title' => (string) $translation->title,
                'summary' => (string) ($translation->description ?? ''),
                'department' => $translation->department,
                'instructor' => $translation->instructor,
                'image' => $lab->image,
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function projectItems(Faculty $faculty, string $locale): array
    {
        return $faculty->studentProjects->map(function (FacultyStudentProject $project) use ($faculty, $locale): array {
            $translation = $this->projectTranslation($project, $locale);

            return [
                'slug' => (string) $project->slug,
                'title' => (string) $translation->title,
                'summary' => (string) ($translation->summary ?? ''),
                'tag' => $translation->tag,
                'team' => $translation->team,
                'supervisor' => $translation->supervisor,
                'image' => $project->image,
                'detailRoute' => $this->url($locale, '/facilities/'.$this->publicSlug($faculty).'/projects#'.$project->slug),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function alumniItems(Faculty $faculty, string $locale): array
    {
        return $faculty->alumni->map(function (Alumni $alumni) use ($faculty, $locale): array {
            $translation = $this->alumniTranslation($alumni, $locale);
            $department = $alumni->department instanceof Department ? $this->departmentTranslation($alumni->department, $locale)->name : null;

            return [
                'title' => (string) $translation->full_name,
                'graduationYear' => $alumni->graduation_year,
                'department' => $department,
                'faculty' => $this->facultyTranslation($faculty, $locale)->name,
                'degree' => $alumni->degree,
                'semester' => $locale === 'ar' ? 'الفصل الثاني' : 'Second Semester',
                'academicPhase' => $locale === 'ar' ? 'خريج' : 'Graduate',
                'image' => '/images/unkown.jpeg',
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function honorItems(Faculty $faculty, string $locale): array
    {
        return $faculty->honorStudents->map(function (HonorStudent $student) use ($faculty, $locale): array {
            $translation = $this->honorTranslation($student, $locale);
            $department = $student->department instanceof Department ? $this->departmentTranslation($student->department, $locale)->name : null;

            return [
                'title' => (string) $translation->full_name,
                'academicYear' => $student->academic_year,
                'department' => $department,
                'gpa' => $student->gpa,
                'semester' => $locale === 'ar' ? 'الفصل الثاني' : 'Second Semester',
                'faculty' => $this->facultyTranslation($faculty, $locale)->name,
                'image' => '/images/unkown.jpeg',
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function latestResearch(Faculty $faculty, string $locale): array
    {
        return $faculty->studentProjects->take(3)->values()->map(function (FacultyStudentProject $project, int $index) use ($faculty, $locale): array {
            $translation = $this->projectTranslation($project, $locale);
            $facultyId = strtoupper((string) $faculty->slug);

            return [
                'title' => (string) $translation->title,
                'summary' => (string) ($translation->summary ?? ''),
                'type' => (string) ($translation->tag ?? ($locale === 'ar' ? 'مشروع طلابي' : 'Student Project')),
                'date' => '2026',
                'doi' => 'SPU-'.$facultyId.'-'.($index + 1),
                'image' => $project->image ?: $faculty->hero_image,
                'url' => $this->url($locale, '/research'),
                'cta' => $index === 0 ? 'View Research' : 'Research Details',
            ];
        })->values()->all();
    }

    /** @return array<int, array{id: string, label: string, body: string}> */
    private function overviewTabs(?FacultyPageTranslation $translation, FacultyTranslation $facultyTranslation, string $locale): array
    {
        $tabs = [[
            'id' => 'overview',
            'label' => $locale === 'ar' ? 'لمحة عن الكلية' : 'Overview',
            'body' => (string) ($translation?->body ?? $facultyTranslation->description ?? ''),
        ]];

        $sections = is_array($translation?->sections_json) ? $translation->sections_json : [];

        foreach ($sections as $index => $section) {
            if (! is_array($section) || empty($section['body'])) {
                continue;
            }

            $tabs[] = [
                'id' => (string) ($section['id'] ?? 'section-'.$index),
                'label' => (string) ($section['title'] ?? ''),
                'body' => (string) $section['body'],
            ];
        }

        return $tabs;
    }

    /** @return array<int, string> */
    private function gallery(Faculty $faculty): array
    {
        $gallery = is_array($faculty->gallery_json) ? $faculty->gallery_json : [];

        return collect([$faculty->hero_image, ...$gallery])->filter()->unique()->values()->all();
    }

    private function facultyTranslation(Faculty $faculty, string $locale): FacultyTranslation
    {
        return $faculty->translations->firstWhere('locale', $locale)
            ?? $faculty->translations->firstWhere('locale', 'ar')
            ?? $faculty->translations->first();
    }

    private function pageTranslation(FacultyPage $page, string $locale): FacultyPageTranslation
    {
        return $page->translations->firstWhere('locale', $locale)
            ?? $page->translations->firstWhere('locale', 'ar')
            ?? $page->translations->first();
    }

    private function highlightTranslation(FacultyHighlight $highlight, string $locale): FacultyHighlightTranslation
    {
        return $highlight->translations->firstWhere('locale', $locale)
            ?? $highlight->translations->firstWhere('locale', 'ar')
            ?? $highlight->translations->first();
    }

    private function departmentTranslation(Department $department, string $locale): DepartmentTranslation
    {
        return $department->translations->firstWhere('locale', $locale)
            ?? $department->translations->firstWhere('locale', 'ar')
            ?? $department->translations->first();
    }

    private function labTranslation(FacultyLab $lab, string $locale): FacultyLabTranslation
    {
        return $lab->translations->firstWhere('locale', $locale)
            ?? $lab->translations->firstWhere('locale', 'ar')
            ?? $lab->translations->first();
    }

    private function projectTranslation(FacultyStudentProject $project, string $locale): FacultyStudentProjectTranslation
    {
        return $project->translations->firstWhere('locale', $locale)
            ?? $project->translations->firstWhere('locale', 'ar')
            ?? $project->translations->first();
    }

    private function alumniTranslation(Alumni $alumni, string $locale): AlumniTranslation
    {
        return $alumni->translations->firstWhere('locale', $locale)
            ?? $alumni->translations->firstWhere('locale', 'ar')
            ?? $alumni->translations->first();
    }

    private function honorTranslation(HonorStudent $student, string $locale): HonorStudentTranslation
    {
        return $student->translations->firstWhere('locale', $locale)
            ?? $student->translations->firstWhere('locale', 'ar')
            ?? $student->translations->first();
    }

    private function publicSlug(Faculty $faculty): string
    {
        return (string) ($faculty->public_slug ?: $faculty->slug);
    }

    private function isAvailableSubpage(string $facultySlug, string $subpageSlug): bool
    {
        if ($subpageSlug === 'training') {
            return $facultySlug === 'pharmacy';
        }

        if ($subpageSlug === 'labs') {
            return $facultySlug !== 'business-administration';
        }

        if ($subpageSlug === 'projects') {
            return $facultySlug !== 'petroleum';
        }

        return true;
    }

    private function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function url(string $locale, string $path): string
    {
        return '/'.$locale.'/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localizedPayload(array $payload, string $locale): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->localizedPayload($value, $locale);
            }
        }

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_ends_with($key, ucfirst($locale))) {
                continue;
            }

            $base = substr($key, 0, -2);
            $base = lcfirst($base);
            $payload[$base] = $value;
        }

        return $payload;
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function localizedPayloadList(array $items, string $locale): array
    {
        return collect($items)->map(fn (array $item): array => $this->localizedPayload($item, $locale))->values()->all();
    }
}
