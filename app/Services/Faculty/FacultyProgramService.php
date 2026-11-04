<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyMasterProgramServiceInterface;
use App\Contracts\Faculty\FacultyProgramServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;

final class FacultyProgramService implements FacultyProgramServiceInterface
{
    /** @var array<string, string> */
    private const FACULTY_ALIASES = [
        'ai' => 'artificial-intelligence',
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    /** @var array<string, array{ar: string, en: string, arPlural: string, enPlural: string}> */
    private const LEVEL_LABELS = [
        'bachelor' => ['ar' => 'إجازة', 'en' => "Bachelor's", 'arPlural' => 'برامج الإجازة', 'enPlural' => "Bachelor's Programs"],
        'master' => ['ar' => 'ماجستير', 'en' => "Master's", 'arPlural' => 'برامج الماجستير', 'enPlural' => "Master's Programs"],
        'phd' => ['ar' => 'دكتوراه', 'en' => 'Doctorate', 'arPlural' => 'برامج الدكتوراه', 'enPlural' => 'Doctoral Programs'],
        'diploma' => ['ar' => 'دبلوم', 'en' => 'Diploma', 'arPlural' => 'برامج الدبلوم', 'enPlural' => 'Diploma Programs'],
    ];

    public function __construct(
        private readonly FacultyStudyPlanLinkServiceInterface $studyPlanLinkService,
        private readonly FacultyMasterProgramServiceInterface $masterProgramService,
    ) {}

    public function isProgramsTarget(string $targetKey): bool
    {
        return $this->facultySlugFromTarget($targetKey) !== null;
    }

    public function defaultContent(string $facultySlug, string $locale, array $departments, bool $hasMasterProgramPage): array
    {
        $facultySlug = $this->canonical($facultySlug);
        $departmentByPlanProgram = [];

        foreach ($departments as $department) {
            $planProgram = trim((string) ($department['studyPlanDepartmentId'] ?? ''));
            $slug = trim((string) ($department['slug'] ?? ''));

            if ($planProgram !== '' && $slug !== '') {
                $departmentByPlanProgram[$planProgram] ??= $slug;
            }
        }

        $programs = [];

        // Each study plan program is a bachelor program, in the department that teaches it.
        foreach ($this->studyPlanLinkService->programNames($facultySlug, $locale) as $id => $name) {
            $programs[] = [
                'id' => 'bachelor-'.$id,
                'level' => 'bachelor',
                'title' => $name,
                'summary' => '',
                'duration' => '',
                'departmentSlug' => $departmentByPlanProgram[$id] ?? null,
                'studyPlanProgramId' => (string) $id,
            ];
        }

        if ($hasMasterProgramPage) {
            $masterPlanProgram = config('faculty_subpages.master_program_department.'.$facultySlug);
            $programs[] = [
                'id' => 'master-program',
                'level' => 'master',
                'title' => $this->masterProgramTitle($facultySlug, $locale),
                'summary' => '',
                'duration' => '',
                'departmentSlug' => is_string($masterPlanProgram) ? ($departmentByPlanProgram[$masterPlanProgram] ?? null) : null,
                'studyPlanProgramId' => null,
            ];
        }

        return ['programs' => $programs];
    }

    public function programs(string $facultySlug, string $locale, array $departments, bool $hasMasterProgramPage, ?array $content = null): array
    {
        $facultySlug = $this->canonical($facultySlug);
        $content ??= $this->publishedLocaleContent('facilities.'.$facultySlug.'.programs', $locale)
            ?? $this->defaultContent($facultySlug, $locale, $departments, $hasMasterProgramPage);
        $departmentsBySlug = [];

        foreach ($departments as $department) {
            $slug = trim((string) ($department['slug'] ?? ''));

            if ($slug !== '') {
                $departmentsBySlug[$slug] = trim((string) ($department['title'] ?? ''));
            }
        }

        $planPrograms = $this->studyPlanLinkService->programNames($facultySlug, $locale);
        $base = '/'.$locale.'/faculties/'.$facultySlug;
        $programs = [];

        foreach ($this->listOfArrays($content['programs'] ?? []) as $program) {
            $id = trim((string) ($program['id'] ?? ''));
            $title = trim((string) ($program['title'] ?? ''));
            $level = in_array($program['level'] ?? null, self::LEVELS, true) ? (string) $program['level'] : 'bachelor';

            if ($id === '' || $title === '') {
                continue;
            }

            $departmentSlug = trim((string) ($program['departmentSlug'] ?? ''));
            $planProgram = trim((string) ($program['studyPlanProgramId'] ?? ''));
            $programs[] = [
                'id' => $id,
                'level' => $level,
                'levelLabel' => $this->levelLabel($level, $locale),
                'title' => $title,
                'summary' => trim((string) ($program['summary'] ?? '')),
                'duration' => trim((string) ($program['duration'] ?? '')),
                // A department no longer on the departments page is left out, not linked.
                'department' => isset($departmentsBySlug[$departmentSlug]) && $departmentsBySlug[$departmentSlug] !== ''
                    ? ['slug' => $departmentSlug, 'title' => $departmentsBySlug[$departmentSlug], 'url' => $base.'/departments#'.$departmentSlug]
                    : null,
                'studyPlanUrl' => $planProgram !== '' && isset($planPrograms[$planProgram]) ? $base.'/study-plan?department='.rawurlencode($planProgram) : null,
                'pageUrl' => $level === 'master' && $hasMasterProgramPage ? $base.'/master-program' : null,
                'url' => $base.'/programs#'.$id,
            ];
        }

        return $programs;
    }

    public function levelLabel(string $level, string $locale, bool $plural = false): string
    {
        $labels = self::LEVEL_LABELS[$level] ?? self::LEVEL_LABELS['bachelor'];

        return $labels[($locale === 'ar' ? 'ar' : 'en').($plural ? 'Plural' : '')];
    }

    public function validationErrors(string $targetKey, array $payload): array
    {
        $facultySlug = $this->facultySlugFromTarget($targetKey);

        if ($facultySlug === null) {
            return [];
        }

        $planPrograms = $this->studyPlanLinkService->programNames($facultySlug, 'en');
        $departments = $this->departmentSlugs($facultySlug);
        $errors = [];
        $shared = [];

        foreach (['ar', 'en'] as $locale) {
            $rows = [];
            $ids = [];
            $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];

            foreach ($this->listOfArrays($content['programs'] ?? []) as $program) {
                $id = trim((string) ($program['id'] ?? ''));
                $level = $program['level'] ?? null;
                $departmentSlug = trim((string) ($program['departmentSlug'] ?? ''));
                $planProgram = trim((string) ($program['studyPlanProgramId'] ?? ''));

                if ($id === '' || isset($ids[$id])) {
                    $errors[$locale][] = 'Every program needs its own id.';
                }

                $ids[$id] = true;

                if (! in_array($level, self::LEVELS, true)) {
                    $errors[$locale][] = 'Every program needs a level: bachelor, master, doctorate or diploma.';
                }

                if (trim((string) ($program['title'] ?? '')) === '') {
                    $errors[$locale][] = 'Every program needs a name.';
                }

                if ($departmentSlug !== '' && ! isset($departments[$departmentSlug])) {
                    $errors[$locale][] = 'A program\'s department must be one of this faculty\'s departments.';
                }

                if ($planProgram !== '' && ! isset($planPrograms[$planProgram])) {
                    $errors[$locale][] = 'A program\'s study plan must be a program of the faculty\'s published study plan.';
                }

                $rows[] = [$id, $level, $departmentSlug, $planProgram];
            }

            $shared[$locale] = $rows;
        }

        if ($shared['ar'] !== $shared['en']) {
            $errors['translations'][] = 'Programs must have the same level, department and study plan in Arabic and English.';
        }

        return array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors);
    }

    /** The master's program's name: its published page title, else the curriculum shipped with the site. */
    private function masterProgramTitle(string $facultySlug, string $locale): string
    {
        $published = $this->publishedLocaleContent('facilities.'.$facultySlug.'.master-program', $locale);
        $title = trim((string) ($published['title'] ?? ($this->masterProgramService->defaultContent($facultySlug, $locale)['title'] ?? '')));

        return $title !== '' ? $title : ($locale === 'ar' ? 'برنامج الماجستير' : "Master's Program");
    }

    /**
     * The faculty's departments, by slug: those on its published departments page, and its department records.
     *
     * @return array<string, true>
     */
    private function departmentSlugs(string $facultySlug): array
    {
        $slugs = [];

        foreach (['ar', 'en'] as $locale) {
            foreach ($this->listOfArrays($this->publishedLocaleContent('facilities.'.$facultySlug.'.departments', $locale)['items'] ?? []) as $item) {
                $slug = trim((string) ($item['slug'] ?? ''));

                if ($slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        $facultyId = Faculty::query()->where('public_slug', $facultySlug)->value('id');

        if (is_numeric($facultyId)) {
            foreach (Department::query()->where('faculty_id', (int) $facultyId)->pluck('slug') as $slug) {
                $slugs[(string) $slug] = true;
            }
        }

        return $slugs;
    }

    /** @return array<string, mixed>|null */
    private function publishedLocaleContent(string $targetKey, string $locale): ?array
    {
        $payload = CmsTargetContent::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Published->value)
            ->value('payload_json');
        $payload = is_string($payload) ? json_decode($payload, true) : $payload;
        $content = is_array($payload) ? ($payload['translations'][$locale] ?? null) : null;

        return is_array($content) ? $content : null;
    }

    private function facultySlugFromTarget(string $targetKey): ?string
    {
        $parts = explode('.', $targetKey);

        return count($parts) === 3 && $parts[0] === 'facilities' && $parts[2] === 'programs' && $parts[1] !== '' ? $this->canonical($parts[1]) : null;
    }

    private function canonical(string $facultySlug): string
    {
        return self::FACULTY_ALIASES[$facultySlug] ?? $facultySlug;
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
