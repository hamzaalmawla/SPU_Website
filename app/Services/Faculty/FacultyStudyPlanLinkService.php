<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use Illuminate\Support\Facades\Storage;

final class FacultyStudyPlanLinkService implements FacultyStudyPlanLinkServiceInterface
{
    /** @var array<string, string> */
    private const FACULTY_ALIASES = [
        'ai' => 'artificial-intelligence',
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    public function optionsForDepartmentsTarget(string $targetKey): array
    {
        $facultySlug = $this->facultySlugFromDepartmentsTarget($targetKey);

        return $facultySlug === null ? [] : $this->optionsForFaculty($facultySlug);
    }

    public function enrichDepartmentItems(string $facultySlug, string $locale, array $items): array
    {
        $departments = $this->effectiveDepartments($facultySlug);
        $validIds = array_fill_keys(array_keys($departments), true);
        $studyPlanUrl = '/'.$locale.'/facilities/'.$this->canonicalFacultySlug($facultySlug).'/study-plan';

        return array_map(function (array $item) use ($departments, $validIds, $locale, $studyPlanUrl): array {
            $explicitId = trim((string) ($item['studyPlanDepartmentId'] ?? ''));
            $departmentId = isset($validIds[$explicitId])
                ? $explicitId
                : $this->exactDepartmentMatch((string) ($item['title'] ?? ''), $locale, $departments);

            return [
                ...$item,
                'studyPlanDepartmentId' => $departmentId,
                'studyPlanUrl' => $departmentId !== null
                    ? $studyPlanUrl.'?department='.rawurlencode($departmentId)
                    : $studyPlanUrl,
            ];
        }, $items);
    }

    public function validationErrors(string $targetKey, array $payload): array
    {
        $facultySlug = $this->facultySlugFromDepartmentsTarget($targetKey);

        if ($facultySlug === null) {
            return [];
        }

        $validIds = array_fill_keys(array_keys($this->effectiveDepartments($facultySlug)), true);
        $errors = [];
        $localeMappings = [];

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];
            $mappings = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $slug = trim((string) ($item['slug'] ?? ''));
                $departmentId = trim((string) ($item['studyPlanDepartmentId'] ?? ''));

                if ($slug !== '' && $departmentId !== '') {
                    $mappings[$slug] = $departmentId;
                }

                if ($departmentId !== '' && ! isset($validIds[$departmentId])) {
                    $errors[$locale][] = 'Every selected Study Plan tab must exist in the currently published study plan.';
                }
            }

            $localeMappings[$locale] = $mappings;
            ksort($localeMappings[$locale]);
        }

        if (($localeMappings['ar'] ?? []) !== ($localeMappings['en'] ?? [])) {
            $errors['translations'][] = 'Department Study Plan mappings must match across Arabic and English.';
        }

        return $errors;
    }

    public function studyPlanValidationErrors(string $targetKey, array $payload): array
    {
        $parts = explode('.', $targetKey);

        if (count($parts) !== 3 || $parts[0] !== 'facilities' || $parts[2] !== 'study_plan') {
            return [];
        }

        $candidateIds = [];
        $errors = [];

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $departments = is_array($translation['payload']['plan']['departments'] ?? null)
                ? $translation['payload']['plan']['departments']
                : [];
            $candidateIds[$locale] = array_keys($this->indexedDepartments($departments));
            sort($candidateIds[$locale]);

            foreach ($departments as $department) {
                if (! is_array($department)) {
                    continue;
                }

                foreach (is_array($department['terms'] ?? null) ? $department['terms'] : [] as $term) {
                    if (! is_array($term)) {
                        continue;
                    }

                    foreach (is_array($term['courses'] ?? null) ? $term['courses'] : [] as $course) {
                        if (! is_array($course)) {
                            continue;
                        }

                        $instructor = is_array($course['instructor'] ?? null) ? $course['instructor'] : [];
                        $staffSlug = trim((string) ($instructor['staffSlug'] ?? ''));
                        if ($staffSlug !== '' && preg_match('/^[A-Za-z0-9-]+$/', $staffSlug) !== 1) {
                            $errors[$locale][] = 'Instructor profile slugs may contain only letters, numbers, and hyphens.';
                        }

                        foreach (is_array($course['lessons'] ?? null) ? $course['lessons'] : [] as $lesson) {
                            $material = is_array($lesson) ? trim((string) ($lesson['pdfUrl'] ?? '')) : '';
                            if ($material !== '' && $this->sanitizeCourseMaterialPath($material) === null) {
                                $errors[$locale][] = 'Course materials must reference an existing internal PDF file.';
                            }
                        }
                    }
                }
            }
        }

        if (($candidateIds['ar'] ?? []) !== ($candidateIds['en'] ?? [])) {
            $errors['translations'][] = 'Study Plan tab IDs must match across Arabic and English.';
        }

        $departmentsTarget = 'facilities.'.$this->canonicalFacultySlug($parts[1]).'.departments';
        $publishedDepartments = CmsTargetContent::query()
            ->where('target_key', $departmentsTarget)
            ->where('status', PublicationStatus::Published->value)
            ->first();

        if (! $publishedDepartments instanceof CmsTargetContent || ! is_array($publishedDepartments->payload_json)) {
            return $errors;
        }

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($publishedDepartments->payload_json['translations'][$locale] ?? null)
                ? $publishedDepartments->payload_json['translations'][$locale]
                : [];
            $items = is_array($translation['items'] ?? null) ? $translation['items'] : [];

            foreach ($items as $item) {
                $departmentId = is_array($item) ? trim((string) ($item['studyPlanDepartmentId'] ?? '')) : '';

                if ($departmentId !== '' && ! in_array($departmentId, $candidateIds[$locale] ?? [], true)) {
                    $errors[$locale][] = 'The Study Plan must retain every tab referenced by the published department directory.';
                }
            }
        }

        return $errors;
    }

    public function sanitizeCourseMaterialPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        $decodedPath = rawurldecode(rawurldecode($path));

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || str_contains($decodedPath, '../')
            || str_contains($decodedPath, '/..')
            || parse_url($path, PHP_URL_QUERY) !== null
            || parse_url($path, PHP_URL_FRAGMENT) !== null
            || strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $relativePath = ltrim(substr($path, strlen('/storage/')), '/');

            return $relativePath !== '' && Storage::disk('public')->exists($relativePath) ? $path : null;
        }

        $publicRoot = realpath(public_path());
        $resolvedPath = realpath(public_path(ltrim($path, '/')));

        return is_string($publicRoot)
            && is_string($resolvedPath)
            && str_starts_with(str_replace('\\', '/', $resolvedPath), rtrim(str_replace('\\', '/', $publicRoot), '/').'/')
            && is_file($resolvedPath)
                ? $path
                : null;
    }

    /** @return array<string, string> */
    private function optionsForFaculty(string $facultySlug): array
    {
        $options = [];

        foreach ($this->effectiveDepartments($facultySlug) as $id => $department) {
            $en = trim((string) ($department['nameEn'] ?? ''));
            $ar = trim((string) ($department['nameAr'] ?? ''));
            $options[$id] = $en !== '' && $ar !== '' ? $en.' - '.$ar : ($en !== '' ? $en : ($ar !== '' ? $ar : $id));
        }

        return $options;
    }

    /** @return array<string, array{nameEn: string, nameAr: string}> */
    private function effectiveDepartments(string $facultySlug): array
    {
        $facultySlug = $this->canonicalFacultySlug($facultySlug);
        $targetKey = 'facilities.'.$facultySlug.'.study_plan';
        $published = CmsTargetContent::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Published->value)
            ->first();

        if ($published instanceof CmsTargetContent && is_array($published->payload_json)) {
            $departments = $this->departmentsFromCmsPayload($published->payload_json);

            if ($departments !== []) {
                return $departments;
            }
        }

        $faculty = Faculty::query()
            ->enabled()
            ->where('public_slug', $facultySlug)
            ->first();

        if (! $faculty instanceof Faculty) {
            return [];
        }

        $page = FacultyPage::query()
            ->enabled()
            ->where('faculty_id', $faculty->getKey())
            ->where('slug', 'study-plan')
            ->first();
        if (! $page instanceof FacultyPage || ! is_array($page->payload_json)) {
            return [];
        }

        $plan = is_array($page->payload_json['plan'] ?? null) ? $page->payload_json['plan'] : [];

        return $this->indexedDepartments(is_array($plan['departments'] ?? null) ? $plan['departments'] : []);
    }

    /** @param array<string, mixed> $payload @return array<string, array{nameEn: string, nameAr: string}> */
    private function departmentsFromCmsPayload(array $payload): array
    {
        $localized = [];

        foreach (['en', 'ar'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $departments = is_array($translation['payload']['plan']['departments'] ?? null)
                ? $translation['payload']['plan']['departments']
                : [];

            $localized[$locale] = $this->indexedDepartments($departments);
        }

        $sharedIds = array_intersect(array_keys($localized['en'] ?? []), array_keys($localized['ar'] ?? []));
        $indexed = [];

        foreach ($sharedIds as $id) {
            $english = $localized['en'][$id] ?? ['nameEn' => '', 'nameAr' => ''];
            $arabic = $localized['ar'][$id] ?? ['nameEn' => '', 'nameAr' => ''];
            $indexed[$id] = [
                'nameEn' => $english['nameEn'] !== '' ? $english['nameEn'] : $arabic['nameEn'],
                'nameAr' => $arabic['nameAr'] !== '' ? $arabic['nameAr'] : $english['nameAr'],
            ];
        }

        return $indexed;
    }

    /** @param array<int, mixed> $departments @return array<string, array{nameEn: string, nameAr: string}> */
    private function indexedDepartments(array $departments): array
    {
        $indexed = [];

        foreach ($departments as $department) {
            if (! is_array($department)) {
                continue;
            }

            $id = trim((string) ($department['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $indexed[$id] = [
                'nameEn' => trim((string) ($department['nameEn'] ?? $department['name'] ?? '')),
                'nameAr' => trim((string) ($department['nameAr'] ?? $department['name'] ?? '')),
            ];
        }

        return $indexed;
    }

    /** @param array<string, array{nameEn: string, nameAr: string}> $departments */
    private function exactDepartmentMatch(string $title, string $locale, array $departments): ?string
    {
        $normalizedTitle = $this->normalizedName($title);

        if ($normalizedTitle === '') {
            return null;
        }

        $nameKey = $locale === 'ar' ? 'nameAr' : 'nameEn';
        $matches = [];

        foreach ($departments as $id => $department) {
            if ($this->normalizedName($department[$nameKey]) === $normalizedTitle) {
                $matches[] = $id;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function normalizedName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/\b(?:the|department|dept|of)\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?:^|\s)قسم(?:\s|$)/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function facultySlugFromDepartmentsTarget(string $targetKey): ?string
    {
        $parts = explode('.', $targetKey);

        return count($parts) === 3 && $parts[0] === 'facilities' && $parts[2] === 'departments'
            ? $this->canonicalFacultySlug($parts[1])
            : null;
    }

    private function canonicalFacultySlug(string $slug): string
    {
        return self::FACULTY_ALIASES[$slug] ?? $slug;
    }
}
