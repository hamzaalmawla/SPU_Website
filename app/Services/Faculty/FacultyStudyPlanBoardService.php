<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use Closure;
use InvalidArgumentException;

final class FacultyStudyPlanBoardService implements FacultyStudyPlanBoardServiceInterface
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    /** @var list<string> */
    private const TYPES = ['university', 'faculty', 'specialization'];

    public function brokenPrerequisiteEdges(array $payload): array
    {
        $known = array_flip($this->allCourseIds($payload));
        $edges = [];

        foreach (self::LOCALES as $locale) {
            foreach ($this->departments($payload, $locale) as $department) {
                foreach ($this->records($department['terms'] ?? []) as $term) {
                    foreach ($this->records($term['courses'] ?? []) as $course) {
                        foreach ($this->stringList($course['prerequisites'] ?? []) as $prerequisiteId) {
                            if (! isset($known[$prerequisiteId])) {
                                $edges[] = $course['id'].'|'.$prerequisiteId;
                            }
                        }
                    }
                }
            }
        }

        return array_values(array_unique($edges));
    }

    public function board(array $payload, string $locale, string $departmentId): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $departments = $this->departments($payload, $locale);

        if ($departments === []) {
            return [
                'departments' => [],
                'departmentId' => '',
                'department' => ['id' => '', 'nameAr' => '', 'nameEn' => '', 'totalCredits' => 0, 'calculatedCredits' => 0],
                'terms' => [],
                'prerequisiteOptions' => [],
                'warnings' => [],
            ];
        }

        $department = null;

        foreach ($departments as $candidate) {
            if ((string) ($candidate['id'] ?? '') === $departmentId) {
                $department = $candidate;
                break;
            }
        }

        $department ??= $departments[0];
        $termOf = [];
        $byId = [];

        foreach ($this->records($department['terms'] ?? []) as $term) {
            foreach ($this->records($term['courses'] ?? []) as $course) {
                $termOf[(string) $course['id']] = (string) $term['id'];
                $byId[(string) $course['id']] = $course;
            }
        }

        $terms = [];
        $warnings = [];
        $calculated = 0;

        foreach ($this->records($department['terms'] ?? []) as $term) {
            $cards = [];
            $termCredits = 0;

            foreach ($this->records($term['courses'] ?? []) as $course) {
                $id = (string) $course['id'];
                $prerequisites = $this->stringList($course['prerequisites'] ?? []);
                $courseWarnings = [];

                foreach ($prerequisites as $prerequisiteId) {
                    if (! isset($termOf[$prerequisiteId])) {
                        continue;
                    }

                    if ((int) $termOf[$prerequisiteId] >= (int) $term['id']) {
                        $label = (string) ($byId[$prerequisiteId]['code'] ?? $prerequisiteId);
                        $courseWarnings[] = $course['code'].' needs '.$label.', which is not in an earlier term.';
                    }
                }

                $credits = $this->number($course['credits'] ?? 0);
                $termCredits += $credits;

                if ($course['required'] ?? true) {
                    $calculated += $credits;
                }

                $warnings = [...$warnings, ...$courseWarnings];
                $cards[] = [
                    'id' => $id,
                    'code' => (string) ($course['code'] ?? ''),
                    'title' => $this->text($course, 'title', $locale),
                    'credits' => $credits,
                    'type' => (string) ($course['type'] ?? 'faculty'),
                    'required' => (bool) ($course['required'] ?? true),
                    'prerequisites' => $prerequisites,
                    'prerequisiteCredits' => (int) ($course['prerequisiteCredits'] ?? 0),
                    'lessonCount' => count($this->records($course['lessons'] ?? [])),
                    'warning' => $courseWarnings !== [],
                ];
            }

            $terms[] = [
                'id' => (string) $term['id'],
                'label' => $this->text($term, 'label', $locale) ?: (string) $term['id'],
                'credits' => $termCredits,
                'courses' => $cards,
            ];
        }

        $options = [];

        foreach ($terms as $term) {
            foreach ($term['courses'] as $card) {
                $options[$card['id']] = $card['code'].' - '.$card['title'];
            }
        }

        return [
            'departments' => array_map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => $this->text($item, 'name', $locale) ?: (string) $item['id'],
            ], $departments),
            'departmentId' => (string) $department['id'],
            'department' => [
                'id' => (string) $department['id'],
                'nameAr' => (string) ($department['nameAr'] ?? ''),
                'nameEn' => (string) ($department['nameEn'] ?? ''),
                'totalCredits' => $this->number($department['totalCredits'] ?? 0),
                'calculatedCredits' => $calculated,
            ],
            'terms' => $terms,
            'prerequisiteOptions' => $options,
            'warnings' => $warnings,
        ];
    }

    public function courseForm(array $payload, string $departmentId, string $courseId): ?array
    {
        $found = [];

        foreach (self::LOCALES as $locale) {
            $department = $this->department($payload, $locale, $departmentId);

            foreach ($this->records($department['terms'] ?? []) as $term) {
                foreach ($this->records($term['courses'] ?? []) as $course) {
                    if ((string) $course['id'] === $courseId) {
                        $found[$locale] = ['course' => $course, 'term' => (string) $term['id']];
                    }
                }
            }
        }

        if (! isset($found['en']) && ! isset($found['ar'])) {
            return null;
        }

        $en = $found['en']['course'] ?? $found['ar']['course'];
        $ar = $found['ar']['course'] ?? $en;
        $lessons = [];

        foreach ($this->records($en['lessons'] ?? []) as $index => $lesson) {
            $arLesson = $this->records($ar['lessons'] ?? [])[$index] ?? $lesson;
            $lessons[] = [
                'id' => (string) ($lesson['id'] ?? ''),
                'type' => (string) ($lesson['type'] ?? 'lecture'),
                'titleEn' => $this->text($lesson, 'title', 'en'),
                'titleAr' => $this->text($arLesson, 'title', 'ar'),
                'pdfUrl' => (string) ($lesson['pdfUrl'] ?? ''),
            ];
        }

        return [
            'id' => $courseId,
            'originalId' => $courseId,
            'termId' => (string) ($found['en']['term'] ?? $found['ar']['term']),
            'code' => (string) ($en['code'] ?? ''),
            'titleAr' => $this->text($ar, 'title', 'ar'),
            'titleEn' => $this->text($en, 'title', 'en'),
            'descriptionAr' => $this->text($ar, 'description', 'ar'),
            'descriptionEn' => $this->text($en, 'description', 'en'),
            'credits' => $this->number($en['credits'] ?? 0),
            'theory' => $this->number($en['hours']['theory'] ?? 0),
            'practical' => $this->number($en['hours']['practical'] ?? 0),
            'type' => (string) ($en['type'] ?? 'specialization'),
            'required' => (bool) ($en['required'] ?? true),
            'prerequisites' => $this->stringList($en['prerequisites'] ?? []),
            'prerequisiteCredits' => (int) ($en['prerequisiteCredits'] ?? 0),
            'lessons' => $lessons,
            'specificationUrl' => (string) ($en['specificationUrl'] ?? ''),
            ...$this->teacherFormFields($en['teachers'] ?? null),
        ];
    }

    public function emptyCourseForm(string $termId): array
    {
        return [
            'id' => '',
            'originalId' => '',
            'termId' => $termId,
            'code' => '',
            'titleAr' => '',
            'titleEn' => '',
            'descriptionAr' => '',
            'descriptionEn' => '',
            'credits' => 3,
            'theory' => 2,
            'practical' => 2,
            'type' => 'specialization',
            'required' => true,
            'prerequisites' => [],
            'prerequisiteCredits' => 0,
            'lessons' => [],
            'specificationUrl' => '',
            ...$this->teacherFormFields(null),
        ];
    }

    public function saveCourse(array $payload, string $departmentId, array $form): array
    {
        $clean = $this->cleanCourseForm($form);
        $originalId = trim((string) ($form['originalId'] ?? ''));
        $id = $originalId !== '' ? $originalId : $this->uniqueId($this->slug($clean['code']), $this->allCourseIds($payload));
        $departmentCourseIds = $this->departmentCourseIds($payload, $departmentId);

        if ($originalId !== '' && ! in_array($originalId, $departmentCourseIds, true)) {
            throw new InvalidArgumentException('That course no longer exists. Reload the page.');
        }

        foreach ($clean['prerequisites'] as $prerequisiteId) {
            if ($prerequisiteId === $id) {
                throw new InvalidArgumentException('A course cannot be its own prerequisite.');
            }

            if (! in_array($prerequisiteId, $departmentCourseIds, true)) {
                throw new InvalidArgumentException('Every prerequisite must be a course of this department.');
            }
        }

        $payload = $this->mapDepartment($payload, $departmentId, function (array $department, string $locale) use ($clean, $id, $form): array {
            $terms = $this->records($department['terms'] ?? []);
            $existing = [];
            $fromTerm = null;

            foreach ($terms as $termIndex => $term) {
                foreach ($this->records($term['courses'] ?? []) as $courseIndex => $course) {
                    if ((string) $course['id'] === $id) {
                        $existing = $course;
                        $fromTerm = $termIndex;
                        unset($terms[$termIndex]['courses'][$courseIndex]);
                        $terms[$termIndex]['courses'] = array_values($this->records($terms[$termIndex]['courses']));
                    }
                }
            }

            $targetIndex = null;

            foreach ($terms as $termIndex => $term) {
                if ((string) $term['id'] === $clean['termId']) {
                    $targetIndex = $termIndex;
                }
            }

            if ($targetIndex === null) {
                throw new InvalidArgumentException('Choose a term that exists in this department.');
            }

            $record = $existing;
            $record['id'] = $id;
            $record['code'] = $clean['code'];
            $record['titleAr'] = $clean['titleAr'];
            $record['titleEn'] = $clean['titleEn'];
            $record['title'] = $locale === 'ar' ? $clean['titleAr'] : $clean['titleEn'];
            $record['descriptionAr'] = $clean['descriptionAr'];
            $record['descriptionEn'] = $clean['descriptionEn'];
            $record['description'] = $locale === 'ar' ? $clean['descriptionAr'] : $clean['descriptionEn'];
            $record['term'] = (int) $clean['termId'];
            $record['type'] = $clean['type'];
            $record['required'] = $clean['required'];
            $record['forced'] = (bool) ($existing['forced'] ?? false);
            $record['credits'] = $clean['credits'];
            $record['prerequisites'] = $clean['prerequisites'];
            $record['prerequisiteCredits'] = $clean['prerequisiteCredits'];
            $record['instructor'] = $existing['instructor'] ?? null;
            $record['hours'] = ['theory' => $clean['theory'], 'practical' => $clean['practical']];
            // Who teaches each part: a faculty profile id, never a copy of the name,
            // so the course page shows the profile as it is now.
            $record['teachers'] = $clean['teachers'] ?? $this->storedTeachers($existing['teachers'] ?? null);

            if (array_key_exists('lessons', $form)) {
                $record['lessons'] = $this->lessonsFromForm($form['lessons'], $this->records($existing['lessons'] ?? []), $id, $locale, $this->lessonIds($department, $id));
            } else {
                $record['lessons'] = $this->records($existing['lessons'] ?? []);
            }

            // One course specification PDF per course, shared by both languages.
            $specificationUrl = array_key_exists('specificationUrl', $form)
                ? trim((string) $form['specificationUrl'])
                : trim((string) ($existing['specificationUrl'] ?? ''));
            unset($record['specificationUrl']);
            if ($specificationUrl !== '') {
                $record['specificationUrl'] = $specificationUrl;
            }

            if ($fromTerm === $targetIndex && isset($existing['row'])) {
                array_splice($terms[$targetIndex]['courses'], max(0, min((int) $existing['row'] - 1, count($terms[$targetIndex]['courses']))), 0, [$record]);
            } else {
                $terms[$targetIndex]['courses'][] = $record;
            }

            $department['terms'] = $this->withRows($terms);

            return $department;
        });

        return $payload;
    }

    public function deleteCourse(array $payload, string $departmentId, string $courseId): array
    {
        if (! in_array($courseId, $this->departmentCourseIds($payload, $departmentId), true)) {
            throw new InvalidArgumentException('That course no longer exists. Reload the page.');
        }

        return $this->removeCourseIds($payload, $departmentId, [$courseId]);
    }

    public function moveCourse(array $payload, string $departmentId, string $courseId, string $termId): array
    {
        $form = $this->courseForm($payload, $departmentId, $courseId);

        if ($form === null) {
            throw new InvalidArgumentException('That course no longer exists. Reload the page.');
        }

        $form['termId'] = $termId;
        unset($form['lessons']);

        return $this->saveCourse($payload, $departmentId, $form);
    }

    public function addTerm(array $payload, string $departmentId): array
    {
        return $this->mapDepartment($payload, $departmentId, function (array $department): array {
            $terms = $this->records($department['terms'] ?? []);
            $next = 1;

            foreach ($terms as $term) {
                $next = max($next, (int) $term['id'] + 1);
            }

            if ($next > 16) {
                throw new InvalidArgumentException('A study plan can have at most 16 terms.');
            }

            $terms[] = ['id' => $next, 'labelAr' => (string) $next, 'labelEn' => (string) $next, 'label' => (string) $next, 'courses' => []];
            $department['terms'] = $terms;

            return $department;
        });
    }

    public function removeTerm(array $payload, string $departmentId, string $termId): array
    {
        return $this->mapDepartment($payload, $departmentId, function (array $department) use ($termId): array {
            $terms = $this->records($department['terms'] ?? []);

            foreach ($terms as $index => $term) {
                if ((string) $term['id'] !== $termId) {
                    continue;
                }

                if ($this->records($term['courses'] ?? []) !== []) {
                    throw new InvalidArgumentException('Move or delete the courses in this term first.');
                }

                unset($terms[$index]);
            }

            $department['terms'] = array_values($terms);

            return $department;
        });
    }

    public function departmentForm(array $payload, string $departmentId): array
    {
        $en = $this->department($payload, 'en', $departmentId);
        $ar = $this->department($payload, 'ar', $departmentId) ?: $en;
        $pools = [];

        foreach ($this->records($en['electivePools'] ?? []) as $index => $pool) {
            $arPool = $this->records($ar['electivePools'] ?? [])[$index] ?? $pool;
            $pools[] = [
                'id' => (string) ($pool['id'] ?? ''),
                'requiredHours' => $this->number($pool['requiredHours'] ?? 0),
                'descriptionAr' => $this->text($arPool, 'description', 'ar'),
                'descriptionEn' => $this->text($pool, 'description', 'en'),
            ];
        }

        $rules = [];

        foreach ($this->records($en['promotionRequirements'] ?? []) as $index => $rule) {
            $arRule = $this->records($ar['promotionRequirements'] ?? [])[$index] ?? $rule;
            $rules[] = [
                'fromYear' => $this->number($rule['fromYear'] ?? 0),
                'toYear' => $this->number($rule['toYear'] ?? 0),
                'requiredCredits' => $this->number($rule['requiredCredits'] ?? 0),
                'descriptionAr' => $this->text($arRule, 'description', 'ar'),
                'descriptionEn' => $this->text($rule, 'description', 'en'),
            ];
        }

        return [
            'nameAr' => (string) ($ar['nameAr'] ?? ''),
            'nameEn' => (string) ($en['nameEn'] ?? ''),
            'totalCredits' => $this->number($en['totalCredits'] ?? 0),
            'electivePools' => $pools,
            'promotionRequirements' => $rules,
        ];
    }

    public function saveDepartment(array $payload, string $departmentId, array $fields): array
    {
        $nameAr = trim((string) ($fields['nameAr'] ?? ''));
        $nameEn = trim((string) ($fields['nameEn'] ?? ''));

        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException('Enter the department name in both Arabic and English.');
        }

        $total = $fields['totalCredits'] ?? null;

        if ($total !== null && $total !== '' && (! is_numeric($total) || (float) $total < 0 || (float) $total > 400)) {
            throw new InvalidArgumentException('Total credits must be a number between 0 and 400.');
        }

        $pools = array_key_exists('electivePools', $fields) ? $this->cleanPools($fields['electivePools']) : null;
        $rules = array_key_exists('promotionRequirements', $fields) ? $this->cleanPromotionRules($fields['promotionRequirements']) : null;

        return $this->mapDepartment($payload, $departmentId, function (array $department, string $locale) use ($nameAr, $nameEn, $total, $pools, $rules): array {
            if ($pools !== null) {
                $department['electivePools'] = array_map(static fn (array $pool): array => [
                    'id' => $pool['id'],
                    'requiredHours' => $pool['requiredHours'],
                    'descriptionAr' => $pool['descriptionAr'],
                    'descriptionEn' => $pool['descriptionEn'],
                    'description' => $locale === 'ar' ? $pool['descriptionAr'] : $pool['descriptionEn'],
                ], $pools);
            }

            if ($rules !== null) {
                $department['promotionRequirements'] = array_map(static fn (array $rule): array => [
                    'fromYear' => $rule['fromYear'],
                    'toYear' => $rule['toYear'],
                    'requiredCredits' => $rule['requiredCredits'],
                    'descriptionAr' => $rule['descriptionAr'],
                    'descriptionEn' => $rule['descriptionEn'],
                    'description' => $locale === 'ar' ? $rule['descriptionAr'] : $rule['descriptionEn'],
                ], $rules);
            }

            $department['nameAr'] = $nameAr;
            $department['nameEn'] = $nameEn;
            $department['name'] = $locale === 'ar' ? $nameAr : $nameEn;

            if ($total !== null && $total !== '') {
                $department['totalCredits'] = $this->number($total);
            }

            return $department;
        });
    }

    public function addDepartment(array $payload, array $fields): array
    {
        $nameAr = trim((string) ($fields['nameAr'] ?? ''));
        $nameEn = trim((string) ($fields['nameEn'] ?? ''));

        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException('Enter the department name in both Arabic and English.');
        }

        $existingIds = array_map(static fn (array $department): string => (string) $department['id'], $this->departments($payload, 'en'));
        $id = $this->uniqueId($this->slug($nameEn) ?: 'department', $existingIds);
        $template = $this->departments($payload, 'en')[0]['terms'] ?? [];
        $termIds = $template !== [] ? array_map(static fn (array $term): mixed => $term['id'], $this->records($template)) : range(1, 10);

        foreach (self::LOCALES as $locale) {
            $terms = array_map(static fn (mixed $termId): array => [
                'id' => $termId, 'labelAr' => (string) $termId, 'labelEn' => (string) $termId, 'label' => (string) $termId, 'courses' => [],
            ], $termIds);
            $departments = $this->departments($payload, $locale);
            $departments[] = [
                'id' => $id,
                'nameAr' => $nameAr,
                'nameEn' => $nameEn,
                'name' => $locale === 'ar' ? $nameAr : $nameEn,
                'summaryAr' => '',
                'summaryEn' => '',
                'summary' => '',
                'totalCredits' => 0,
                'terms' => $terms,
                'electivePools' => [],
                'promotionRequirements' => [],
            ];
            $payload['translations'][$locale]['payload']['plan']['departments'] = $departments;
        }

        return $payload;
    }

    public function removeDepartment(array $payload, string $departmentId): array
    {
        $departments = $this->departments($payload, 'en');

        if (count($departments) < 2) {
            throw new InvalidArgumentException('A study plan needs at least one department.');
        }

        $courseIds = $this->departmentCourseIds($payload, $departmentId);

        foreach (self::LOCALES as $locale) {
            $kept = array_values(array_filter(
                $this->departments($payload, $locale),
                static fn (array $department): bool => (string) $department['id'] !== $departmentId,
            ));
            $payload['translations'][$locale]['payload']['plan']['departments'] = $kept;
        }

        return $this->stripPrerequisites($payload, $courseIds);
    }

    public function addLessons(array $payload, string $departmentId, string $courseId, array $lessons): array
    {
        $form = $this->courseForm($payload, $departmentId, $courseId);

        if ($form === null) {
            throw new InvalidArgumentException('That course no longer exists. Reload the page.');
        }

        foreach ($lessons as $lesson) {
            $form['lessons'][] = [
                'id' => '',
                'type' => 'lecture',
                'titleAr' => $lesson['titleAr'],
                'titleEn' => $lesson['titleEn'],
                'pdfUrl' => $lesson['pdfUrl'],
            ];
        }

        return $this->saveCourse($payload, $departmentId, $form);
    }

    public function setSpecification(array $payload, string $departmentId, string $courseId, string $pdfUrl): array
    {
        $form = $this->courseForm($payload, $departmentId, $courseId);

        if ($form === null) {
            throw new InvalidArgumentException('That course no longer exists. Reload the page.');
        }

        $form['specificationUrl'] = $pdfUrl;

        return $this->saveCourse($payload, $departmentId, $form);
    }

    /** @return list<array{id: string, requiredHours: int|float, descriptionAr: string, descriptionEn: string}> */
    private function cleanPools(mixed $pools): array
    {
        $clean = [];
        $taken = [];

        foreach ($this->records($pools) as $pool) {
            $hours = $pool['requiredHours'] ?? 0;

            if ($hours === '') {
                $hours = 0;
            }

            if (! is_numeric($hours) || (float) $hours < 0 || (float) $hours > 200) {
                throw new InvalidArgumentException('Required hours must be a number between 0 and 200.');
            }

            $id = $this->slug((string) ($pool['id'] ?? '')) ?: 'pool';
            $id = $this->uniqueId($id, $taken);
            $taken[] = $id;
            $clean[] = [
                'id' => $id,
                'requiredHours' => $this->number($hours),
                'descriptionAr' => trim((string) ($pool['descriptionAr'] ?? '')),
                'descriptionEn' => trim((string) ($pool['descriptionEn'] ?? '')),
            ];
        }

        return $clean;
    }

    /** @return list<array{fromYear: int|float, toYear: int|float, requiredCredits: int|float, descriptionAr: string, descriptionEn: string}> */
    private function cleanPromotionRules(mixed $rules): array
    {
        $clean = [];

        foreach ($this->records($rules) as $rule) {
            foreach (['fromYear', 'toYear', 'requiredCredits'] as $field) {
                $value = $rule[$field] ?? null;

                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 400) {
                    throw new InvalidArgumentException('Promotion rules need numeric years and credits.');
                }
            }

            $clean[] = [
                'fromYear' => $this->number($rule['fromYear']),
                'toYear' => $this->number($rule['toYear']),
                'requiredCredits' => $this->number($rule['requiredCredits']),
                'descriptionAr' => trim((string) ($rule['descriptionAr'] ?? '')),
                'descriptionEn' => trim((string) ($rule['descriptionEn'] ?? '')),
            ];
        }

        return $clean;
    }

    /**
     * The theory and practical teacher fields of the course form.
     *
     * @return array{theoryTeacherId: string, theoryTeacherEnabled: bool, practicalTeacherId: string, practicalTeacherEnabled: bool}
     */
    private function teacherFormFields(mixed $stored): array
    {
        $teachers = $this->storedTeachers($stored);

        return [
            'theoryTeacherId' => $teachers['theory']['memberId'] !== null ? (string) $teachers['theory']['memberId'] : '',
            'theoryTeacherEnabled' => $teachers['theory']['enabled'],
            'practicalTeacherId' => $teachers['practical']['memberId'] !== null ? (string) $teachers['practical']['memberId'] : '',
            'practicalTeacherEnabled' => $teachers['practical']['enabled'],
        ];
    }

    /**
     * A course's teachers as stored, with both parts present. A part is
     * shown by default; switching it off hides it on the course page.
     *
     * @return array{theory: array{memberId: int|null, enabled: bool}, practical: array{memberId: int|null, enabled: bool}}
     */
    private function storedTeachers(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $teachers = [];
        foreach (['theory', 'practical'] as $part) {
            $entry = is_array($stored[$part] ?? null) ? $stored[$part] : [];
            $memberId = $entry['memberId'] ?? null;
            $teachers[$part] = [
                'memberId' => is_numeric($memberId) && (int) $memberId > 0 ? (int) $memberId : null,
                'enabled' => (bool) ($entry['enabled'] ?? true),
            ];
        }

        return $teachers;
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function cleanCourseForm(array $form): array
    {
        $code = trim((string) ($form['code'] ?? ''));
        $titleAr = trim((string) ($form['titleAr'] ?? ''));
        $titleEn = trim((string) ($form['titleEn'] ?? ''));

        if ($code === '' || mb_strlen($code) > 80) {
            throw new InvalidArgumentException('Enter a course code (up to 80 characters).');
        }

        if ($titleAr === '' || $titleEn === '') {
            throw new InvalidArgumentException('Enter the course title in both Arabic and English.');
        }

        if (mb_strlen($titleAr) > 180 || mb_strlen($titleEn) > 180) {
            throw new InvalidArgumentException('Course titles can be at most 180 characters.');
        }

        foreach (['credits', 'theory', 'practical'] as $field) {
            $value = $form[$field] ?? 0;

            if ($value === '') {
                $form[$field] = 0;
            } elseif (! is_numeric($value) || (float) $value < 0 || (float) $value > 40) {
                throw new InvalidArgumentException('Credits and hours must be numbers between 0 and 40.');
            }
        }

        $credits = $form['prerequisiteCredits'] ?? 0;

        if ($credits === '') {
            $credits = 0;
        }

        if (! is_numeric($credits) || (float) $credits < 0 || (float) $credits > 400) {
            throw new InvalidArgumentException('Completed credits must be a number between 0 and 400.');
        }

        $type = (string) ($form['type'] ?? 'specialization');

        $teachers = null;
        if (array_key_exists('theoryTeacherId', $form) || array_key_exists('practicalTeacherId', $form)) {
            $teachers = [];
            foreach (['theory', 'practical'] as $part) {
                $memberId = trim((string) ($form[$part.'TeacherId'] ?? ''));
                if ($memberId !== '' && (! ctype_digit($memberId) || (int) $memberId < 1)) {
                    throw new InvalidArgumentException('Choose the teacher from the list.');
                }
                $teachers[$part] = [
                    'memberId' => $memberId === '' ? null : (int) $memberId,
                    'enabled' => filter_var($form[$part.'TeacherEnabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        return [
            'teachers' => $teachers,
            'code' => $code,
            'titleAr' => $titleAr,
            'titleEn' => $titleEn,
            'descriptionAr' => trim((string) ($form['descriptionAr'] ?? '')),
            'descriptionEn' => trim((string) ($form['descriptionEn'] ?? '')),
            'credits' => $this->number($form['credits'] ?? 0),
            'theory' => $this->number($form['theory'] ?? 0),
            'practical' => $this->number($form['practical'] ?? 0),
            'type' => in_array($type, self::TYPES, true) ? $type : 'specialization',
            'required' => filter_var($form['required'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'prerequisites' => $this->stringList($form['prerequisites'] ?? []),
            'prerequisiteCredits' => (int) $credits,
            'termId' => trim((string) ($form['termId'] ?? '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<string>  $usedIds
     * @return list<array<string, mixed>>
     */
    private function lessonsFromForm(mixed $formLessons, array $existing, string $courseId, string $locale, array $usedIds): array
    {
        $byId = [];

        foreach ($existing as $lesson) {
            $byId[(string) ($lesson['id'] ?? '')] = $lesson;
        }

        $lessons = [];

        foreach ($this->records($formLessons) as $index => $lesson) {
            $titleAr = trim((string) ($lesson['titleAr'] ?? ''));
            $titleEn = trim((string) ($lesson['titleEn'] ?? ''));

            if ($titleAr === '' && $titleEn === '') {
                throw new InvalidArgumentException('Every lecture needs a title.');
            }

            $titleAr = $titleAr !== '' ? $titleAr : $titleEn;
            $titleEn = $titleEn !== '' ? $titleEn : $titleAr;
            $id = trim((string) ($lesson['id'] ?? ''));

            if ($id === '' || ! isset($byId[$id])) {
                $id = $this->uniqueId($courseId.'-lecture-'.($index + 1), $usedIds);
                $usedIds[] = $id;
            }

            $base = $byId[$id] ?? [];
            $lessons[] = [
                'id' => $id,
                'order' => $index + 1,
                'type' => (string) ($lesson['type'] ?? $base['type'] ?? 'lecture') ?: 'lecture',
                'pdfUrl' => trim((string) ($lesson['pdfUrl'] ?? '')),
                'titleAr' => $titleAr,
                'titleEn' => $titleEn,
                'title' => $locale === 'ar' ? $titleAr : $titleEn,
                'descriptionAr' => (string) ($base['descriptionAr'] ?? ''),
                'descriptionEn' => (string) ($base['descriptionEn'] ?? ''),
                'description' => (string) ($base[$locale === 'ar' ? 'descriptionAr' : 'descriptionEn'] ?? ''),
            ];
        }

        return $lessons;
    }

    /** @return list<string> */
    private function lessonIds(array $department, string $exceptCourseId): array
    {
        $ids = [];

        foreach ($this->records($department['terms'] ?? []) as $term) {
            foreach ($this->records($term['courses'] ?? []) as $course) {
                foreach ($this->records($course['lessons'] ?? []) as $lesson) {
                    $ids[] = (string) ($lesson['id'] ?? '');
                }
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $courseIds
     * @return array<string, mixed>
     */
    private function removeCourseIds(array $payload, string $departmentId, array $courseIds): array
    {
        $payload = $this->mapDepartment($payload, $departmentId, function (array $department) use ($courseIds): array {
            $terms = $this->records($department['terms'] ?? []);

            foreach ($terms as $index => $term) {
                $terms[$index]['courses'] = array_values(array_filter(
                    $this->records($term['courses'] ?? []),
                    static fn (array $course): bool => ! in_array((string) $course['id'], $courseIds, true),
                ));
            }

            $department['terms'] = $this->withRows($terms);

            return $department;
        });

        return $this->stripPrerequisites($payload, $courseIds);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $courseIds
     * @return array<string, mixed>
     */
    private function stripPrerequisites(array $payload, array $courseIds): array
    {
        foreach (self::LOCALES as $locale) {
            $departments = $this->departments($payload, $locale);

            foreach ($departments as $d => $department) {
                foreach ($this->records($department['terms'] ?? []) as $t => $term) {
                    foreach ($this->records($term['courses'] ?? []) as $c => $course) {
                        $departments[$d]['terms'][$t]['courses'][$c]['prerequisites'] = array_values(array_diff($this->stringList($course['prerequisites'] ?? []), $courseIds));
                    }
                }
            }

            $payload['translations'][$locale]['payload']['plan']['departments'] = $departments;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(array<string, mixed>, string): array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function mapDepartment(array $payload, string $departmentId, Closure $change): array
    {
        foreach (self::LOCALES as $locale) {
            $departments = $this->departments($payload, $locale);
            $found = false;

            foreach ($departments as $index => $department) {
                if ((string) ($department['id'] ?? '') === $departmentId) {
                    $departments[$index] = $change($department, $locale);
                    $found = true;
                }
            }

            if (! $found) {
                throw new InvalidArgumentException('That department no longer exists. Reload the page.');
            }

            $payload['translations'][$locale]['payload']['plan']['departments'] = $departments;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function departments(array $payload, string $locale): array
    {
        return $this->records($payload['translations'][$locale]['payload']['plan']['departments'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function department(array $payload, string $locale, string $departmentId): array
    {
        foreach ($this->departments($payload, $locale) as $department) {
            if ((string) ($department['id'] ?? '') === $departmentId) {
                return $department;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function departmentCourseIds(array $payload, string $departmentId): array
    {
        $ids = [];

        foreach ($this->records($this->department($payload, 'en', $departmentId)['terms'] ?? []) as $term) {
            foreach ($this->records($term['courses'] ?? []) as $course) {
                $ids[] = (string) $course['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function allCourseIds(array $payload): array
    {
        $ids = [];

        foreach (self::LOCALES as $locale) {
            foreach ($this->departments($payload, $locale) as $department) {
                foreach ($this->records($department['terms'] ?? []) as $term) {
                    foreach ($this->records($term['courses'] ?? []) as $course) {
                        $ids[] = (string) $course['id'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return list<array<string, mixed>>
     */
    private function withRows(array $terms): array
    {
        foreach ($terms as $t => $term) {
            $courses = array_values($this->records($term['courses'] ?? []));

            foreach ($courses as $c => $course) {
                $courses[$c]['row'] = $c + 1;
                $courses[$c]['term'] = (int) $term['id'];
            }

            $terms[$t]['courses'] = $courses;
        }

        return array_values($terms);
    }

    /** @param list<string> $taken */
    private function uniqueId(string $base, array $taken): string
    {
        $base = $base !== '' ? $base : 'course';
        $id = $base;
        $suffix = 2;

        while (in_array($id, $taken, true)) {
            $id = $base.'-'.$suffix++;
        }

        return $id;
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }

    /** @param array<string, mixed> $item */
    private function text(array $item, string $key, string $locale): string
    {
        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        $other = $locale === 'ar' ? 'En' : 'Ar';

        return (string) ($item[$key.$suffix] ?? $item[$key] ?? $item[$key.$other] ?? '');
    }

    private function number(mixed $value): int|float
    {
        $number = is_numeric($value) ? $value + 0 : 0;

        return is_float($number) && floor($number) === $number ? (int) $number : $number;
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value), static fn (string $item): bool => $item !== '')));
    }
}
