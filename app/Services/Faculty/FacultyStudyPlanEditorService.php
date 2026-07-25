<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use Illuminate\Support\Str;

final class FacultyStudyPlanEditorService implements FacultyStudyPlanEditorServiceInterface
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    /** @var list<string> */
    private const SHARED_COURSE_FIELDS = ['code', 'credits', 'type', 'required', 'prerequisites'];

    /** @var list<string> */
    private const SHARED_LESSON_FIELDS = ['order', 'type', 'pdfUrl'];

    public function buildWorkspace(array $payload, string $departmentId, string $termId): array
    {
        $departments = [];
        $terms = [];

        foreach (self::LOCALES as $locale) {
            $departments[$locale] = $this->department($payload, $locale, $departmentId);
            $departments[$locale] = is_array($departments[$locale])
                ? $this->canonicalizeLegacyPrerequisites($departments[$locale])
                : null;
            $terms[$locale] = $this->term($departments[$locale], $termId);
        }

        $courses = [];

        foreach ($this->pairedRecords(
            $this->records($terms['ar']['courses'] ?? []),
            $this->records($terms['en']['courses'] ?? []),
        ) as $pair) {
            $courses[] = $this->courseWorkspace($pair);
        }

        $electivePools = [];
        $promotionRequirements = [];

        foreach ($this->pairedRecords(
            $this->records($departments['ar']['electivePools'] ?? []),
            $this->records($departments['en']['electivePools'] ?? []),
        ) as $pair) {
            $electivePools[] = $this->simpleWorkspace($pair, 'pool', ['requiredHours']);
        }

        foreach ($this->pairedRecords(
            $this->records($departments['ar']['promotionRequirements'] ?? []),
            $this->records($departments['en']['promotionRequirements'] ?? []),
        ) as $pair) {
            $promotionRequirements[] = $this->simpleWorkspace($pair, 'promotion', ['fromYear', 'toYear', 'requiredCredits']);
        }

        return [
            'courses' => $courses,
            'electivePools' => $electivePools,
            'promotionRequirements' => $promotionRequirements,
        ];
    }

    public function mergeWorkspace(array $payload, array $workspace, string $departmentId, string $termId): array
    {
        if ($departmentId === '' || $termId === '') {
            return $payload;
        }

        $workspace = $this->prepareWorkspace($workspace);

        foreach (self::LOCALES as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $pagePayload = is_array($translation['payload'] ?? null) ? $translation['payload'] : [];
            $plan = is_array($pagePayload['plan'] ?? null) ? $pagePayload['plan'] : [];
            $departments = $this->records($plan['departments'] ?? []);

            foreach ($departments as $departmentIndex => $department) {
                if ((string) ($department['id'] ?? '') !== $departmentId) {
                    continue;
                }

                $terms = $this->records($department['terms'] ?? []);
                $canonicalDepartment = $this->canonicalizeLegacyPrerequisites($department);
                $canonicalTerm = $this->term($canonicalDepartment, $termId);

                foreach ($terms as $termIndex => $term) {
                    if ((string) ($term['id'] ?? '') !== $termId) {
                        continue;
                    }

                    if (array_key_exists('courses', $workspace)) {
                        $term['courses'] = $this->mergeCourses(
                            $locale,
                            $this->records($canonicalTerm['courses'] ?? $term['courses'] ?? []),
                            $this->records($workspace['courses'] ?? []),
                        );
                    }

                    $terms[$termIndex] = $term;
                }

                $department['terms'] = $terms;

                if (array_key_exists('electivePools', $workspace)) {
                    $department['electivePools'] = $this->mergeSimpleRecords(
                        $locale,
                        'pool',
                        $this->records($department['electivePools'] ?? []),
                        $this->records($workspace['electivePools'] ?? []),
                        ['requiredHours'],
                    );
                }

                if (array_key_exists('promotionRequirements', $workspace)) {
                    $department['promotionRequirements'] = $this->mergeSimpleRecords(
                        $locale,
                        'promotion',
                        $this->records($department['promotionRequirements'] ?? []),
                        $this->records($workspace['promotionRequirements'] ?? []),
                        ['fromYear', 'toYear', 'requiredCredits'],
                    );
                }

                $departments[$departmentIndex] = $department;
            }

            $plan['departments'] = $departments;
            $pagePayload['plan'] = $plan;
            $translation['payload'] = $pagePayload;
            $payload['translations'][$locale] = $translation;
        }

        return $payload;
    }

    public function prepareWorkspace(array $workspace): array
    {
        return $this->normalizeWorkspaceIdentities($workspace);
    }

    public function prerequisiteOptions(array $payload, string $departmentId): array
    {
        $courses = [];

        foreach (self::LOCALES as $locale) {
            $department = $this->department($payload, $locale, $departmentId);

            foreach ($this->records($department['terms'] ?? []) as $term) {
                foreach ($this->records($term['courses'] ?? []) as $course) {
                    $id = trim((string) ($course['id'] ?? ''));

                    if ($id !== '') {
                        $courses[$id][$locale] = $course;
                    }
                }
            }
        }

        $options = [];

        foreach ($courses as $id => $localized) {
            $ar = is_array($localized['ar'] ?? null) ? $localized['ar'] : [];
            $en = is_array($localized['en'] ?? null) ? $localized['en'] : [];
            $code = trim((string) ($en['code'] ?? $ar['code'] ?? ''));
            $titleEn = $this->localizedValue($en, 'title', 'en');
            $titleAr = $this->localizedValue($ar, 'title', 'ar');
            $title = implode(' / ', array_values(array_unique(array_filter([$titleEn, $titleAr]))));
            $options[$id] = implode(' - ', array_filter([$code, $title])) ?: $id;
        }

        return $options;
    }

    public function lessonTypeOptions(array $payload, string $departmentId): array
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $options = [
            'lecture' => __('admin.faculty_workspace.study_plan.lesson_types.lecture'),
            'practical' => __('admin.faculty_workspace.study_plan.lesson_types.practical'),
            'lab' => __('admin.faculty_workspace.study_plan.lesson_types.lab'),
            'reference' => __('admin.faculty_workspace.study_plan.lesson_types.reference'),
            'exam' => __('admin.faculty_workspace.study_plan.lesson_types.exam'),
            'seminar' => __('admin.faculty_workspace.study_plan.lesson_types.seminar'),
        ];

        foreach ([$locale, $locale === 'ar' ? 'en' : 'ar'] as $contentLocale) {
            $translation = is_array($payload['translations'][$contentLocale] ?? null) ? $payload['translations'][$contentLocale] : [];
            $types = is_array($translation['payload']['lessonTypes'] ?? null) ? $translation['payload']['lessonTypes'] : [];

            foreach ($types as $key => $type) {
                if (! is_array($type)) {
                    continue;
                }

                $id = trim((string) ($type['id'] ?? $key));
                $label = $this->localizedValue($type, 'label', $contentLocale);

                if ($id !== '' && (! isset($options[$id]) || $contentLocale === $locale)) {
                    $options[$id] = $label !== '' ? $label : ($options[$id] ?? Str::headline($id));
                }
            }

            $department = $this->department($payload, $contentLocale, $departmentId);

            foreach ($this->records($department['terms'] ?? []) as $term) {
                foreach ($this->records($term['courses'] ?? []) as $course) {
                    foreach ($this->records($course['lessons'] ?? []) as $lesson) {
                        $type = trim((string) ($lesson['type'] ?? ''));

                        if ($type !== '') {
                            $options[$type] ??= Str::headline($type);
                        }
                    }
                }
            }
        }

        return $options;
    }

    public function validationErrors(array $payload, array $allowedDanglingEdges = []): array
    {
        $errors = [];
        $localizedDepartments = [];
        $allowedDanglingEdges = array_fill_keys($allowedDanglingEdges, true);

        foreach (self::LOCALES as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $departments = $this->records($translation['payload']['plan']['departments'] ?? []);
            $localizedDepartments[$locale] = $this->indexById($departments);
            $facultyCourseIds = [];

            foreach ($localizedDepartments[$locale] as $department) {
                foreach ($this->records($department['terms'] ?? []) as $term) {
                    foreach ($this->records($term['courses'] ?? []) as $course) {
                        $courseId = trim((string) ($course['id'] ?? ''));

                        if ($courseId !== '') {
                            $facultyCourseIds[$courseId] = true;
                        }
                    }
                }
            }

            foreach ($localizedDepartments[$locale] as $departmentId => $department) {
                $this->validateDepartment($locale, $departmentId, $department, $facultyCourseIds, $allowedDanglingEdges, $errors);
            }
        }

        $arIds = array_keys($localizedDepartments['ar'] ?? []);
        $enIds = array_keys($localizedDepartments['en'] ?? []);
        sort($arIds);
        sort($enIds);

        if ($arIds !== $enIds) {
            $errors['translations'][] = 'Study Plan department IDs must match across Arabic and English.';
        }

        foreach (array_intersect($arIds, $enIds) as $departmentId) {
            $this->validateCrossLocaleDepartment(
                $departmentId,
                $localizedDepartments['ar'][$departmentId],
                $localizedDepartments['en'][$departmentId],
                $errors,
            );
        }

        return array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors);
    }

    /** @param array{ar: ?array, en: ?array, arIndex: ?int, enIndex: ?int} $pair @return array<string, mixed> */
    private function courseWorkspace(array $pair): array
    {
        $ar = is_array($pair['ar']) ? $pair['ar'] : [];
        $en = is_array($pair['en']) ? $pair['en'] : [];
        $identity = $this->workspaceIdentity($pair, 'course');
        $instructorAr = is_array($ar['instructor'] ?? null) ? $ar['instructor'] : [];
        $instructorEn = is_array($en['instructor'] ?? null) ? $en['instructor'] : [];
        $lessons = [];

        foreach ($this->pairedRecords($this->records($ar['lessons'] ?? []), $this->records($en['lessons'] ?? [])) as $lessonPair) {
            $lessonAr = is_array($lessonPair['ar']) ? $lessonPair['ar'] : [];
            $lessonEn = is_array($lessonPair['en']) ? $lessonPair['en'] : [];
            $lesson = $this->workspaceIdentity($lessonPair, 'lesson');

            foreach (self::SHARED_LESSON_FIELDS as $field) {
                $lesson[$field] = $this->sharedValue($lessonAr, $lessonEn, $field);
            }

            $lesson['titleAr'] = $this->localizedValue($lessonAr, 'title', 'ar');
            $lesson['titleEn'] = $this->localizedValue($lessonEn, 'title', 'en');
            $lesson['descriptionAr'] = $this->localizedValue($lessonAr, 'description', 'ar');
            $lesson['descriptionEn'] = $this->localizedValue($lessonEn, 'description', 'en');
            $lessons[] = $lesson;
        }

        $workspace = $identity;

        foreach (self::SHARED_COURSE_FIELDS as $field) {
            $workspace[$field] = $this->sharedValue($ar, $en, $field);
        }

        $workspace['prerequisites'] = $this->stringList($workspace['prerequisites'] ?? []);
        $workspace['titleAr'] = $this->localizedValue($ar, 'title', 'ar');
        $workspace['titleEn'] = $this->localizedValue($en, 'title', 'en');
        $workspace['descriptionAr'] = $this->localizedValue($ar, 'description', 'ar');
        $workspace['descriptionEn'] = $this->localizedValue($en, 'description', 'en');
        $workspace['instructor'] = [
            'staffSlug' => (string) $this->sharedValue($instructorAr, $instructorEn, 'staffSlug'),
            'nameAr' => $this->localizedValue($instructorAr, 'name', 'ar'),
            'nameEn' => $this->localizedValue($instructorEn, 'name', 'en'),
        ];
        $workspace['lessons'] = $lessons;

        return $workspace;
    }

    /**
     * @param  array{ar: ?array, en: ?array, arIndex: ?int, enIndex: ?int}  $pair
     * @param  list<string>  $sharedFields
     * @return array<string, mixed>
     */
    private function simpleWorkspace(array $pair, string $prefix, array $sharedFields): array
    {
        $ar = is_array($pair['ar']) ? $pair['ar'] : [];
        $en = is_array($pair['en']) ? $pair['en'] : [];
        $workspace = $this->workspaceIdentity($pair, $prefix);

        foreach ($sharedFields as $field) {
            $workspace[$field] = $this->sharedValue($ar, $en, $field);
        }

        $workspace['descriptionAr'] = $this->localizedValue($ar, 'description', 'ar');
        $workspace['descriptionEn'] = $this->localizedValue($en, 'description', 'en');

        return $workspace;
    }

    /** @param array<string, mixed> $workspace @return array<string, mixed> */
    private function normalizeWorkspaceIdentities(array $workspace): array
    {
        foreach ([['courses', 'course'], ['electivePools', 'pool'], ['promotionRequirements', 'promotion']] as [$key, $prefix]) {
            if (! array_key_exists($key, $workspace)) {
                continue;
            }

            $seen = [];
            $records = $this->records($workspace[$key] ?? []);

            foreach ($records as $index => $record) {
                $records[$index] = $this->normalizeRecordIdentity($record, $prefix, $seen);

                if ($key !== 'courses') {
                    continue;
                }

                $lessonSeen = [];
                $lessons = $this->records($record['lessons'] ?? []);

                foreach ($lessons as $lessonIndex => $lesson) {
                    $lessons[$lessonIndex] = $this->normalizeRecordIdentity($lesson, 'lesson', $lessonSeen);
                }

                $records[$index]['lessons'] = $lessons;
            }

            $workspace[$key] = $records;
        }

        return $workspace;
    }

    /** @param array<string, mixed> $record @param array<string, bool> $seen @return array<string, mixed> */
    private function normalizeRecordIdentity(array $record, string $prefix, array &$seen): array
    {
        $originalId = trim((string) ($record['_originalId'] ?? ''));
        $hasOriginalIndex = collect(is_array($record['_originalIndexes'] ?? null) ? $record['_originalIndexes'] : [])
            ->contains(static fn (mixed $index): bool => is_int($index));
        $candidate = trim((string) ($record['id'] ?? ''));

        if ($originalId !== '') {
            $id = $originalId;
        } elseif (($hasOriginalIndex || $this->isGeneratedId($candidate, $prefix)) && $this->isUrlSafeId($candidate) && ! isset($seen[$candidate])) {
            $id = $candidate;
        } else {
            do {
                $id = $this->newId($prefix);
            } while (isset($seen[$id]));
        }

        $seen[$id] = true;
        $record['id'] = $id;

        if ($originalId === '' && $this->isGeneratedId($id, $prefix)) {
            $record['_originalId'] = $id;
        }

        return $record;
    }

    /** @param list<array<string, mixed>> $base @param list<array<string, mixed>> $edited @return list<array<string, mixed>> */
    private function mergeCourses(string $locale, array $base, array $edited): array
    {
        $merged = [];
        $used = [];

        foreach ($edited as $course) {
            $baseCourse = $this->originalRecord($base, $course, $locale, $used);
            $id = trim((string) ($course['id'] ?? ''));
            $result = $baseCourse;

            foreach (self::SHARED_COURSE_FIELDS as $field) {
                if (array_key_exists($field, $course)) {
                    $result[$field] = $field === 'prerequisites'
                        ? $this->stringList($course[$field])
                        : $course[$field];
                }
            }

            $result['id'] = $id !== '' ? $id : $this->newId('course');
            $result = $this->mergeLocalizedValues($result, $course, $locale, ['title', 'description']);
            $instructor = is_array($result['instructor'] ?? null) ? $result['instructor'] : [];
            $editedInstructor = is_array($course['instructor'] ?? null) ? $course['instructor'] : [];
            $instructor['staffSlug'] = (string) ($editedInstructor['staffSlug'] ?? '');
            $instructor = $this->mergeLocalizedValues($instructor, $editedInstructor, $locale, ['name']);
            $result['instructor'] = $instructor;
            $result['lessons'] = $this->mergeLessons(
                $locale,
                $this->records($baseCourse['lessons'] ?? []),
                $this->records($course['lessons'] ?? []),
            );
            unset($result['opensCourseIds']);
            $merged[] = $result;
        }

        return $merged;
    }

    /** @param list<array<string, mixed>> $base @param list<array<string, mixed>> $edited @return list<array<string, mixed>> */
    private function mergeLessons(string $locale, array $base, array $edited): array
    {
        $merged = [];
        $used = [];

        foreach ($edited as $lesson) {
            $result = $this->originalRecord($base, $lesson, $locale, $used);

            foreach (self::SHARED_LESSON_FIELDS as $field) {
                if (array_key_exists($field, $lesson)) {
                    $result[$field] = $lesson[$field];
                }
            }

            $result['id'] = (string) ($lesson['id'] ?? $this->newId('lesson'));
            $merged[] = $this->mergeLocalizedValues($result, $lesson, $locale, ['title', 'description']);
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $edited
     * @param  list<string>  $sharedFields
     * @return list<array<string, mixed>>
     */
    private function mergeSimpleRecords(string $locale, string $prefix, array $base, array $edited, array $sharedFields): array
    {
        $merged = [];
        $used = [];

        foreach ($edited as $record) {
            $result = $this->originalRecord($base, $record, $locale, $used);

            foreach ($sharedFields as $field) {
                if (array_key_exists($field, $record)) {
                    $result[$field] = $record[$field];
                }
            }

            $result['id'] = (string) ($record['id'] ?? $this->newId($prefix));
            $merged[] = $this->mergeLocalizedValues($result, $record, $locale, ['description']);
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  array<string, mixed>  $edited
     * @param  array<int, bool>  $used
     * @return array<string, mixed>
     */
    private function originalRecord(array $base, array $edited, string $locale, array &$used): array
    {
        $originalId = trim((string) ($edited['_originalId'] ?? ''));

        if ($originalId !== '') {
            foreach ($base as $index => $record) {
                if (! isset($used[$index]) && (string) ($record['id'] ?? '') === $originalId) {
                    $used[$index] = true;

                    return $record;
                }
            }
        }

        $indexes = is_array($edited['_originalIndexes'] ?? null) ? $edited['_originalIndexes'] : [];
        $index = $indexes[$locale] ?? null;

        if (is_int($index) && isset($base[$index]) && ! isset($used[$index])) {
            $used[$index] = true;

            return $base[$index];
        }

        return [];
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $edited @param list<string> $fields @return array<string, mixed> */
    private function mergeLocalizedValues(array $base, array $edited, string $locale, array $fields): array
    {
        foreach ($fields as $field) {
            $ar = (string) ($edited[$field.'Ar'] ?? '');
            $en = (string) ($edited[$field.'En'] ?? '');
            $base[$field] = $locale === 'ar' ? $ar : $en;
            $base[$field.'Ar'] = $ar;
            $base[$field.'En'] = $en;
        }

        return $base;
    }

    /** @param array<string, mixed> $department @return array<string, mixed> */
    private function canonicalizeLegacyPrerequisites(array $department): array
    {
        $terms = $this->records($department['terms'] ?? []);
        $locations = [];
        $legacyTargets = [];
        $legacyEdges = [];

        foreach ($terms as $termIndex => $term) {
            $courses = $this->records($term['courses'] ?? []);

            foreach ($courses as $courseIndex => $course) {
                $id = trim((string) ($course['id'] ?? ''));

                if ($id !== '') {
                    $locations[$id] = [$termIndex, $courseIndex];

                    if (! array_key_exists('prerequisites', $course)) {
                        $legacyTargets[$id] = true;
                    }

                    foreach ($this->stringList($course['opensCourseIds'] ?? []) as $targetId) {
                        $legacyEdges[] = [$id, $targetId];
                    }
                }

                unset($course['opensCourseIds']);
                $courses[$courseIndex] = $course;
            }

            $terms[$termIndex]['courses'] = $courses;
        }

        foreach ($legacyEdges as [$sourceId, $targetId]) {
            if ($targetId === $sourceId || ! isset($legacyTargets[$targetId], $locations[$targetId])) {
                continue;
            }

            [$targetTerm, $targetCourse] = $locations[$targetId];
            $targetCourses = $this->records($terms[$targetTerm]['courses'] ?? []);
            $targetRecord = $targetCourses[$targetCourse] ?? [];
            $targetRecord['prerequisites'] = array_values(array_unique([
                ...$this->stringList($targetRecord['prerequisites'] ?? []),
                $sourceId,
            ]));
            $targetCourses[$targetCourse] = $targetRecord;
            $terms[$targetTerm]['courses'] = $targetCourses;
        }

        $department['terms'] = $terms;

        return $department;
    }

    /**
     * @param  list<array<string, mixed>>  $ar
     * @param  list<array<string, mixed>>  $en
     * @return list<array{ar: ?array, en: ?array, arIndex: ?int, enIndex: ?int}>
     */
    private function pairedRecords(array $ar, array $en): array
    {
        $enById = [];
        $usedEn = [];
        $pairs = [];

        foreach ($en as $index => $record) {
            $id = trim((string) ($record['id'] ?? ''));

            if ($id !== '') {
                $enById[$id][] = $index;
            }
        }

        foreach ($ar as $index => $record) {
            $id = trim((string) ($record['id'] ?? ''));
            $enIndex = null;

            foreach ($enById[$id] ?? [] as $candidateIndex) {
                if (! isset($usedEn[$candidateIndex])) {
                    $enIndex = $candidateIndex;
                    break;
                }
            }

            if ($enIndex === null && ! isset($usedEn[$index]) && isset($en[$index])) {
                $enId = trim((string) ($en[$index]['id'] ?? ''));

                if ($id === '' || $enId === '') {
                    $enIndex = $index;
                }
            }

            if (is_int($enIndex)) {
                $usedEn[$enIndex] = true;
            }

            $pairs[] = [
                'ar' => $record,
                'en' => is_int($enIndex) ? $en[$enIndex] : null,
                'arIndex' => $index,
                'enIndex' => $enIndex,
            ];
        }

        foreach ($en as $index => $record) {
            if (! isset($usedEn[$index])) {
                $pairs[] = ['ar' => null, 'en' => $record, 'arIndex' => null, 'enIndex' => $index];
            }
        }

        return $pairs;
    }

    /** @param array{ar: ?array, en: ?array, arIndex: ?int, enIndex: ?int} $pair @return array<string, mixed> */
    private function workspaceIdentity(array $pair, string $prefix): array
    {
        $arId = trim((string) ($pair['ar']['id'] ?? ''));
        $enId = trim((string) ($pair['en']['id'] ?? ''));
        $id = $arId !== '' ? $arId : ($enId !== '' ? $enId : $this->newId($prefix));

        return [
            'id' => $id,
            '_originalId' => $arId !== '' ? $arId : $enId,
            '_originalIndexes' => ['ar' => $pair['arIndex'], 'en' => $pair['enIndex']],
        ];
    }

    /** @param array<string, mixed> $ar @param array<string, mixed> $en */
    private function sharedValue(array $ar, array $en, string $field): mixed
    {
        if (array_key_exists($field, $en)) {
            return $en[$field];
        }

        return $ar[$field] ?? null;
    }

    /** @param array<string, mixed> $record */
    private function localizedValue(array $record, string $field, string $locale): string
    {
        $suffix = ucfirst($locale);
        $otherSuffix = $locale === 'ar' ? 'En' : 'Ar';

        return trim((string) ($record[$field] ?? $record[$field.$suffix] ?? $record[$field.$otherSuffix] ?? ''));
    }

    /** @param array<string, mixed>|null $department @return array<string, mixed>|null */
    private function term(?array $department, string $termId): ?array
    {
        foreach ($this->records($department['terms'] ?? []) as $term) {
            if ((string) ($term['id'] ?? '') === $termId) {
                return $term;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function department(array $payload, string $locale, string $departmentId): ?array
    {
        $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];

        foreach ($this->records($translation['payload']['plan']['departments'] ?? []) as $department) {
            if ((string) ($department['id'] ?? '') === $departmentId) {
                return $department;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $records): array
    {
        return array_values(array_filter(is_array($records) ? $records : [], 'is_array'));
    }

    /** @param list<array<string, mixed>> $records @return array<string, array<string, mixed>> */
    private function indexById(array $records): array
    {
        $indexed = [];

        foreach ($records as $record) {
            $id = trim((string) ($record['id'] ?? ''));

            if ($id !== '' && ! isset($indexed[$id])) {
                $indexed[$id] = $record;
            }
        }

        return $indexed;
    }

    /** @param array<string, mixed> $department @param array<string, bool> $facultyCourseIds @param array<string, bool> $allowedDanglingEdges @param array<string, array<int, string>> $errors */
    private function validateDepartment(string $locale, string $departmentId, array $department, array $facultyCourseIds, array $allowedDanglingEdges, array &$errors): void
    {
        $courseIds = [];
        $courses = [];
        $lessonIds = [];
        $poolIds = [];
        $promotionIds = [];

        foreach ($this->records($department['terms'] ?? []) as $term) {
            foreach ($this->records($term['courses'] ?? []) as $course) {
                $id = trim((string) ($course['id'] ?? ''));

                if (! $this->isUrlSafeId($id)) {
                    $errors[$locale][] = 'Study Plan course IDs must be lowercase URL-safe identifiers.';
                } elseif (isset($courseIds[$id])) {
                    $errors[$locale][] = 'Course IDs must be unique within each department.';
                } else {
                    $courseIds[$id] = true;
                    $courses[$id] = $course;
                }

                if ($this->localizedValue($course, 'title', $locale) === '') {
                    $errors[$locale][] = 'Every course requires a localized title.';
                }

                foreach ($this->records($course['lessons'] ?? []) as $lesson) {
                    $lessonId = trim((string) ($lesson['id'] ?? ''));

                    if (! $this->isUrlSafeId($lessonId)) {
                        $errors[$locale][] = 'Study Plan lesson IDs must be lowercase URL-safe identifiers.';
                    } elseif (isset($lessonIds[$lessonId])) {
                        $errors[$locale][] = 'Lesson IDs must be unique within each department.';
                    } else {
                        $lessonIds[$lessonId] = true;
                    }

                    if ($this->localizedValue($lesson, 'title', $locale) === '') {
                        $errors[$locale][] = 'Every lesson requires a localized title.';
                    }
                }
            }
        }

        foreach ($courses as $courseId => $course) {
            foreach ($this->stringList($course['prerequisites'] ?? []) as $prerequisiteId) {
                if ($prerequisiteId === $courseId) {
                    $errors[$locale][] = 'A course cannot be its own prerequisite.';
                } elseif (! isset($facultyCourseIds[$prerequisiteId]) && ! isset($allowedDanglingEdges[$courseId.'|'.$prerequisiteId])) {
                    $errors[$locale][] = 'Every prerequisite must reference a course in the faculty Study Plan.';
                }
            }
        }

        if ($this->hasCycle($courses)) {
            $errors[$locale][] = 'Study Plan prerequisites cannot contain a cycle.';
        }

        foreach ($this->records($department['electivePools'] ?? []) as $pool) {
            $this->validateOptionalRecordId(
                trim((string) ($pool['id'] ?? '')),
                $poolIds,
                $locale,
                'Elective pool',
                $errors,
            );
        }

        foreach ($this->records($department['promotionRequirements'] ?? []) as $requirement) {
            $this->validateOptionalRecordId(
                trim((string) ($requirement['id'] ?? '')),
                $promotionIds,
                $locale,
                'Promotion requirement',
                $errors,
            );
        }
    }

    /** @param array<string, bool> $ids @param array<string, array<int, string>> $errors */
    private function validateOptionalRecordId(string $id, array &$ids, string $locale, string $label, array &$errors): void
    {
        // Missing IDs remain publish-compatible with untouched seeded data and are generated when edited.
        if ($id === '') {
            return;
        }

        if (! $this->isUrlSafeId($id)) {
            $errors[$locale][] = "{$label} IDs must be lowercase URL-safe identifiers.";
        } elseif (isset($ids[$id])) {
            $errors[$locale][] = "{$label} IDs must be unique within each department.";
        } else {
            $ids[$id] = true;
        }
    }

    /** @param array<string, mixed> $ar @param array<string, mixed> $en @param array<string, array<int, string>> $errors */
    private function validateCrossLocaleDepartment(string $departmentId, array $ar, array $en, array &$errors): void
    {
        $arTerms = $this->termsById($ar);
        $enTerms = $this->termsById($en);

        if (array_keys($arTerms) !== array_keys($enTerms)) {
            $errors['translations'][] = "Term IDs must match across Arabic and English in department {$departmentId}.";
        } else {
            foreach ($arTerms as $termId => $arTerm) {
                $arTermCourseIds = array_keys($this->indexById($this->records($arTerm['courses'] ?? [])));
                $enTermCourseIds = array_keys($this->indexById($this->records($enTerms[$termId]['courses'] ?? [])));
                sort($arTermCourseIds);
                sort($enTermCourseIds);

                if ($arTermCourseIds !== $enTermCourseIds) {
                    $errors['translations'][] = "Course IDs must match by term across Arabic and English in department {$departmentId}.";
                }
            }
        }

        $arCourses = $this->coursesById($ar);
        $enCourses = $this->coursesById($en);

        if (array_keys($arCourses) !== array_keys($enCourses)) {
            $errors['translations'][] = "Course IDs must match across Arabic and English in department {$departmentId}.";

            return;
        }

        foreach ($arCourses as $id => $arCourse) {
            $enCourse = $enCourses[$id];

            foreach (self::SHARED_COURSE_FIELDS as $field) {
                $arValue = $field === 'prerequisites' ? $this->sortedStringList($arCourse[$field] ?? []) : ($arCourse[$field] ?? null);
                $enValue = $field === 'prerequisites' ? $this->sortedStringList($enCourse[$field] ?? []) : ($enCourse[$field] ?? null);

                if ($arValue !== $enValue) {
                    $errors['translations'][] = 'Shared course values must match across Arabic and English.';
                }
            }

            $arInstructor = is_array($arCourse['instructor'] ?? null) ? $arCourse['instructor'] : [];
            $enInstructor = is_array($enCourse['instructor'] ?? null) ? $enCourse['instructor'] : [];

            if (($arInstructor['staffSlug'] ?? '') !== ($enInstructor['staffSlug'] ?? '')) {
                $errors['translations'][] = 'Instructor profile links must match across Arabic and English.';
            }

            $arLessons = $this->indexById($this->records($arCourse['lessons'] ?? []));
            $enLessons = $this->indexById($this->records($enCourse['lessons'] ?? []));

            if (array_keys($arLessons) !== array_keys($enLessons)) {
                $errors['translations'][] = 'Lesson IDs must match across Arabic and English.';

                continue;
            }

            foreach ($arLessons as $lessonId => $arLesson) {
                foreach (self::SHARED_LESSON_FIELDS as $field) {
                    if (($arLesson[$field] ?? null) !== ($enLessons[$lessonId][$field] ?? null)) {
                        $errors['translations'][] = 'Shared lesson values must match across Arabic and English.';
                    }
                }
            }
        }

        $this->validateSharedRecordCollection($ar, $en, 'electivePools', ['requiredHours'], $errors);
        $this->validateSharedRecordCollection($ar, $en, 'promotionRequirements', ['fromYear', 'toYear', 'requiredCredits'], $errors);
    }

    /** @param array<string, mixed> $ar @param array<string, mixed> $en @param list<string> $fields @param array<string, array<int, string>> $errors */
    private function validateSharedRecordCollection(array $ar, array $en, string $key, array $fields, array &$errors): void
    {
        $arRecords = $this->records($ar[$key] ?? []);
        $enRecords = $this->records($en[$key] ?? []);

        if (count($arRecords) !== count($enRecords)) {
            $errors['translations'][] = 'Study Plan shared records must match across Arabic and English.';

            return;
        }

        foreach ($arRecords as $index => $arRecord) {
            $enRecord = $enRecords[$index];

            if (($arRecord['id'] ?? '') !== ($enRecord['id'] ?? '')) {
                $errors['translations'][] = 'Study Plan shared record IDs must match across Arabic and English.';
            }

            foreach ($fields as $field) {
                if (($arRecord[$field] ?? null) !== ($enRecord[$field] ?? null)) {
                    $errors['translations'][] = 'Study Plan academic values must match across Arabic and English.';
                }
            }
        }
    }

    /** @param array<string, mixed> $department @return array<string, array<string, mixed>> */
    private function coursesById(array $department): array
    {
        $courses = [];

        foreach ($this->records($department['terms'] ?? []) as $term) {
            foreach ($this->records($term['courses'] ?? []) as $course) {
                $id = trim((string) ($course['id'] ?? ''));

                if ($id !== '' && ! isset($courses[$id])) {
                    $courses[$id] = $course;
                }
            }
        }

        ksort($courses);

        return $courses;
    }

    /** @param array<string, mixed> $department @return array<string, array<string, mixed>> */
    private function termsById(array $department): array
    {
        $terms = [];

        foreach ($this->records($department['terms'] ?? []) as $term) {
            $id = trim((string) ($term['id'] ?? ''));

            if ($id !== '' && ! isset($terms[$id])) {
                $terms[$id] = $term;
            }
        }

        ksort($terms);

        return $terms;
    }

    /** @param array<string, array<string, mixed>> $courses */
    private function hasCycle(array $courses): bool
    {
        $visiting = [];
        $visited = [];
        $visit = function (string $id) use (&$visit, &$visiting, &$visited, $courses): bool {
            if (isset($visiting[$id])) {
                return true;
            }

            if (isset($visited[$id])) {
                return false;
            }

            $visiting[$id] = true;

            foreach ($this->stringList($courses[$id]['prerequisites'] ?? []) as $prerequisiteId) {
                if (isset($courses[$prerequisiteId]) && $visit($prerequisiteId)) {
                    return true;
                }
            }

            unset($visiting[$id]);
            $visited[$id] = true;

            return false;
        };

        foreach (array_keys($courses) as $id) {
            if ($visit($id)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function stringList(mixed $items): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($items) ? $items : [],
        ), static fn (string $item): bool => $item !== ''));
    }

    /** @return list<string> */
    private function sortedStringList(mixed $items): array
    {
        $items = $this->stringList($items);
        sort($items);

        return $items;
    }

    private function isUrlSafeId(string $id): bool
    {
        return $id !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) === 1;
    }

    private function isGeneratedId(string $id, string $prefix): bool
    {
        return preg_match('/^'.preg_quote($prefix, '/').'-[0-9a-hjkmnp-tv-z]{26}$/', $id) === 1;
    }

    private function newId(string $prefix): string
    {
        return $prefix.'-'.Str::lower((string) Str::ulid());
    }
}
