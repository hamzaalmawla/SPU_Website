<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Faculty\FacultyStudyPlanImportResultDTO;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\User\User;
use InvalidArgumentException;

final class FacultyStudyPlanImportService implements FacultyStudyPlanImportServiceInterface
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly FacultyStudyPlanEditorServiceInterface $studyPlanEditorService,
    ) {}

    public function import(string $facultySlug, array $source, string $actor, bool $write, bool $replaceEdited = false): FacultyStudyPlanImportResultDTO
    {
        $targetKey = 'facilities.'.$facultySlug.'.study_plan';
        $userId = $this->actorId($actor);
        $sourceDepartments = array_values(array_filter(
            is_array($source['departments'] ?? null) ? $source['departments'] : [],
            static fn (mixed $department): bool => is_array($department),
        ));

        if ($sourceDepartments === []) {
            throw new InvalidArgumentException('The source file contains no departments.');
        }

        if (! $replaceEdited && $this->hasEditorHistory($targetKey)) {
            return new FacultyStudyPlanImportResultDTO(
                targetKey: $targetKey,
                departmentCount: count($sourceDepartments),
                courseCount: 0,
                coursesByDepartment: [],
                written: false,
                draftVersion: null,
                errors: [],
                valid: true,
                skipped: true,
                skipReason: 'Editors have already saved or published this plan, so it was left exactly as it is.',
            );
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $userId);
        $payload = is_array($draftPayload) ? $draftPayload : $this->facultyPageService->getEditablePayload($targetKey);
        $expectedVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId);

        $counts = [];

        foreach (self::LOCALES as $locale) {
            $existing = $this->existingDepartments($payload, $locale);
            $fallback = $existing !== [] ? reset($existing) : [];
            $departments = [];

            foreach ($sourceDepartments as $department) {
                $departments[] = $this->localizedDepartment($department, $locale, $existing[(string) ($department['id'] ?? '')] ?? $fallback);
            }

            $payload['translations'][$locale]['payload']['plan']['departments'] = $departments;

            if ($locale === 'en') {
                foreach ($departments as $department) {
                    $counts[(string) $department['id']] = $this->courseCount($department);
                }
            }
        }

        $errors = $this->studyPlanEditorService->validationErrors($payload);
        $draftVersion = null;

        if ($write && $errors === []) {
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $payload, $userId, $expectedVersion);
            $draftVersion = $draft->version;
        }

        return new FacultyStudyPlanImportResultDTO(
            targetKey: $targetKey,
            departmentCount: count($sourceDepartments),
            courseCount: array_sum($counts),
            coursesByDepartment: $counts,
            written: $draftVersion !== null,
            draftVersion: $draftVersion,
            errors: $errors,
            valid: $errors === [],
        );
    }

    /**
     * A plan nobody has touched has no CMS rows at all; it only exists as seeded page data.
     * Any draft (including superseded, scheduled or unpublished ones) or published row means
     * an editor has worked on it.
     */
    private function hasEditorHistory(string $targetKey): bool
    {
        return CmsDraft::query()->where('target_key', $targetKey)->exists()
            || CmsTargetContent::query()->where('target_key', $targetKey)->exists();
    }

    private function actorId(string $actor): int
    {
        $query = User::query();
        $user = ctype_digit($actor) ? $query->find((int) $actor) : $query->where('email', $actor)->first();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('No user matches "'.$actor.'". Pass a user id or email with --user.');
        }

        return (int) $user->getKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function existingDepartments(array $payload, string $locale): array
    {
        $departments = $payload['translations'][$locale]['payload']['plan']['departments'] ?? [];
        $indexed = [];

        foreach (is_array($departments) ? $departments : [] as $department) {
            if (is_array($department) && isset($department['id'])) {
                $indexed[(string) $department['id']] = $department;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $department
     * @param  array<string, mixed>  $template  An existing department whose elective pools and promotion rules are reused when the source has none.
     * @return array<string, mixed>
     */
    private function localizedDepartment(array $department, string $locale, array $template): array
    {
        $terms = [];
        $row = 0;

        foreach (is_array($department['terms'] ?? null) ? $department['terms'] : [] as $term) {
            $courses = [];
            $row = 0;

            foreach (is_array($term['courses'] ?? null) ? $term['courses'] : [] as $course) {
                $courses[] = $this->localizedCourse($course, $locale, ++$row);
            }

            $terms[] = [
                'id' => $term['id'],
                'labelAr' => (string) ($term['labelAr'] ?? $term['id']),
                'labelEn' => (string) ($term['labelEn'] ?? $term['id']),
                'label' => (string) ($term[$locale === 'ar' ? 'labelAr' : 'labelEn'] ?? $term['id']),
                'courses' => $courses,
            ];
        }

        $localized = [
            'id' => (string) $department['id'],
            'nameAr' => (string) ($department['nameAr'] ?? ''),
            'nameEn' => (string) ($department['nameEn'] ?? ''),
            'name' => (string) ($department[$locale === 'ar' ? 'nameAr' : 'nameEn'] ?? ''),
            'summaryAr' => (string) ($department['summaryAr'] ?? $template['summaryAr'] ?? ''),
            'summaryEn' => (string) ($department['summaryEn'] ?? $template['summaryEn'] ?? ''),
            'totalCredits' => $this->credits($department['totalCredits'] ?? 0),
            'terms' => $terms,
            'electivePools' => $department['electivePools'] ?? $template['electivePools'] ?? [],
            'promotionRequirements' => $department['promotionRequirements'] ?? $template['promotionRequirements'] ?? [],
        ];
        $localized['summary'] = $locale === 'ar' ? $localized['summaryAr'] : $localized['summaryEn'];

        return $localized;
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    private function localizedCourse(array $course, string $locale, int $row): array
    {
        $titleAr = (string) ($course['titleAr'] ?? '');
        $titleEn = (string) ($course['titleEn'] ?? '');
        $descriptionAr = (string) ($course['descriptionAr'] ?? '');
        $descriptionEn = (string) ($course['descriptionEn'] ?? '');

        return [
            'id' => (string) $course['id'],
            'code' => (string) ($course['code'] ?? ''),
            'titleAr' => $titleAr,
            'titleEn' => $titleEn,
            'title' => $locale === 'ar' ? $titleAr : $titleEn,
            'term' => (int) ($course['term'] ?? 0),
            'row' => $row,
            'type' => (string) ($course['type'] ?? 'faculty'),
            'required' => (bool) ($course['required'] ?? true),
            'forced' => (bool) ($course['forced'] ?? false),
            'credits' => $this->credits($course['credits'] ?? 0),
            'prerequisites' => array_values(array_map('strval', is_array($course['prerequisites'] ?? null) ? $course['prerequisites'] : [])),
            'prerequisiteCredits' => (int) ($course['prerequisiteCredits'] ?? 0),
            'instructor' => $course['instructor'] ?? null,
            'hours' => [
                'theory' => (int) ($course['hours']['theory'] ?? 0),
                'practical' => (int) ($course['hours']['practical'] ?? 0),
            ],
            'descriptionAr' => $descriptionAr,
            'descriptionEn' => $descriptionEn,
            'description' => $locale === 'ar' ? $descriptionAr : $descriptionEn,
            'lessons' => is_array($course['lessons'] ?? null) ? $course['lessons'] : [],
        ];
    }

    /** Credit values such as 4.5 are real, so they must not be cast to int. */
    private function credits(mixed $value): int|float
    {
        $number = is_numeric($value) ? $value + 0 : 0;

        return is_float($number) && floor($number) === $number ? (int) $number : $number;
    }

    /** @param array<string, mixed> $department */
    private function courseCount(array $department): int
    {
        $count = 0;

        foreach (is_array($department['terms'] ?? null) ? $department['terms'] : [] as $term) {
            $count += count(is_array($term['courses'] ?? null) ? $term['courses'] : []);
        }

        return $count;
    }
}
