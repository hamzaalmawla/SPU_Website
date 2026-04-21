<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyHonorStudentsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'honor_students';
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

            $legacyFacultyId = $this->normalizedInteger($this->rowValue($row, $facultyKeys));
            $facultyId = $this->resolveLegacyFacultyId($legacyFacultyId);

            $gpaRaw = $this->rowValue($row, ['gpa', 'grade', 'average', 'mark']);
            $gpa = null;

            if (is_numeric($gpaRaw)) {
                $gpa = round((float) $gpaRaw, 2);

                if ($gpa < 0 || $gpa > 99.99) {
                    $gpa = null;
                }
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['order', 'sort_order', 'record_order', 'rank'])) ?? 0;
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_active', 'active', 'is_enabled']), true);

            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['created_at', 'date_added', 'reg_date']));

            try {
                $honorId = DB::table('honor_students')->insertGetId([
                    'student_identifier' => $studentId,
                    'faculty_id' => $facultyId,
                    'department_id' => null,
                    'academic_year' => $academicYear,
                    'gpa' => $gpa,
                    'photo_media_id' => null,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => $createdAt?->toDateTimeString() ?? now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_good_students', $sourceId, 'honor_students', $honorId,
                    'success', 'Imported honor student.',
                    ['academic_year' => $academicYear, 'gpa' => $gpa, 'faculty_id' => $facultyId],
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
