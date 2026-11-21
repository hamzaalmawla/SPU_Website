<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyHonorStudentsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'honor_students';

        if (! $this->shouldRunModule($module)) {
            return;
        }

        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_good_students');
        $academicYearKeys = $this->legacyAvailableColumns('jx_good_students', ['academic_year', 'date_year', 'study_year', 'semester', 'year']);
        $facultyKeys = $this->legacyAvailableColumns('jx_good_students', ['department_id', 'faculty_id', 'category_id', 'cat_id']);

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_good_students.');

            return;
        }

        $this->command?->info("Starting honor students import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_good_students', $sourceId, 'honor_students')) {
                $skipped++;

                continue;
            }

            $academicYear = $this->cleanedString($row, $academicYearKeys);

            if ($academicYear === null || $academicYear === '') {
                $academicYear = 'unknown';
            }

            $studentId = $this->cleanedString($row, ['student_id', 'student_number', 'student_identifier', 'number']);
            $translations = array_filter([
                'ar' => $this->cleanedString($row, ['ar_name', 'name_ar', 'full_name_ar', 'name']),
                'en' => $this->cleanedString($row, ['en_name', 'name_en', 'full_name_en']),
            ], static fn (?string $value): bool => $value !== null && $value !== '');

            if ($translations === []) {
                $this->reject($module, 'jx_good_students', $sourceId, 'missing_translation', 'Honor student row has no usable AR/EN full name.');
                $this->logSkip($module, $batch, 'jx_good_students', $sourceId, 'honor_students', 'Skipped honor student row without AR/EN full name.');
                $skipped++;

                continue;
            }

            $legacyFacultyId = $this->normalizedInteger($this->rowValue($row, $facultyKeys));
            $facultyId = $this->resolveLegacyStudentFacultyId($legacyFacultyId);

            if ($facultyId === null) {
                $this->reject($module, 'jx_good_students', $sourceId, 'missing_parent', 'Could not resolve student faculty from legacy department code.', [
                    'legacy_faculty_code' => $legacyFacultyId,
                ]);
                $this->logSkip($module, $batch, 'jx_good_students', $sourceId, 'honor_students', 'Missing student faculty mapping.');
                $skipped++;

                continue;
            }

            $gpaRaw = $this->rowValue($row, ['gpa', 'grade', 'average', 'mark']);
            $gpa = null;

            if (is_numeric($gpaRaw)) {
                $gpa = round((float) $gpaRaw, 2);

                if ($gpa < 0 || $gpa > 99.99) {
                    $gpa = null;
                }
            }

            $studyYear = $this->normalizedInteger($this->rowValue($row, 'year'));
            $academicDateYear = $this->normalizedInteger($this->rowValue($row, 'date_year'));

            if ($academicDateYear !== null && $studyYear !== null && $studyYear > 0) {
                $academicYear = $academicDateYear.' / '.$studyYear;
            } elseif ($academicDateYear !== null) {
                $academicYear = (string) $academicDateYear;
            } elseif ($studyYear !== null && $studyYear > 0) {
                $academicYear = 'year '.$studyYear;
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['s_order', 'record_order', 'sort_order', 'order', 'rank'])) ?? ($sourceId ?? 0);
            $isEnabled = $this->normalizedLegacyVisibility($row, true);

            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['post_date', 'created_at', 'date_added', 'reg_date']));

            try {
                $honorId = DB::transaction(function () use ($row, $studentId, $facultyId, $academicYear, $gpa, $sortOrder, $isEnabled, $createdAt, $translations): int {
                    $timestamp = now();
                    $photoMediaId = $this->legacyMediaAssetId(
                        $this->cleanedString($row, 'photo'),
                        'students/honor',
                        $translations['ar'] ?? null,
                        $translations['en'] ?? null,
                    );

                    $honorId = DB::table('honor_students')->insertGetId([
                        'student_identifier' => $studentId,
                        'faculty_id' => $facultyId,
                        'department_id' => null,
                        'academic_year' => $academicYear,
                        'gpa' => $gpa,
                        'photo_media_id' => $photoMediaId,
                        'sort_order' => $sortOrder,
                        'is_enabled' => $isEnabled,
                        'created_at' => $createdAt?->toDateTimeString() ?? $timestamp->toDateTimeString(),
                        'updated_at' => $timestamp,
                    ]);

                    DB::table('honor_student_translations')->insert(array_map(
                        static fn (string $locale, string $fullName): array => [
                            'honor_student_id' => $honorId,
                            'locale' => $locale,
                            'full_name' => $fullName,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ],
                        array_keys($translations),
                        array_values($translations),
                    ));

                    return $honorId;
                });

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_good_students', $sourceId, 'honor_students', $honorId,
                    'success', 'Imported honor student.',
                    [
                        'academic_year' => $academicYear,
                        'gpa' => $gpa,
                        'faculty_id' => $facultyId,
                        'section_id' => $this->normalizedInteger($this->rowValue($row, 'section_id')),
                        'study_year' => $studyYear,
                        'date_year' => $academicDateYear,
                        'locales' => array_keys($translations),
                    ],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_good_students', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_good_students', $sourceId, 'honor_students', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Honor students import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
