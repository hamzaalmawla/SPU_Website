<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyAlumniSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'alumni';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_graduated_students');
        $facultyKeys = $this->legacyAvailableColumns('jx_graduated_students', ['department_id', 'faculty_id', 'category_id', 'cat_id']);
        $graduationYearKeys = $this->legacyAvailableColumns('jx_graduated_students', ['graduation_year', 'date_year', 'grad_year', 'year']);

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_graduated_students.');

            return;
        }

        $this->command?->info("Starting alumni import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_graduated_students', $sourceId, 'alumni')) {
                $skipped++;

                continue;
            }

            $studentId = $this->cleanedString($row, ['student_id', 'student_number', 'student_identifier', 'number']);
            $email = $this->cleanedString($row, ['email', 'mail']);

            if ($email !== null && $email !== '') {
                $validatedEmail = $this->emailValidator()->normalize($email);
                $email = $validatedEmail;
            }

            $legacyFacultyId = $this->normalizedInteger($this->rowValue($row, $facultyKeys));
            $facultyId = $this->resolveLegacyFacultyId($legacyFacultyId);

            $legacyCountryId = $this->normalizedInteger($this->rowValue($row, ['country_id', 'jx_country_id']));
            $countryId = null;

            if ($legacyCountryId !== null) {
                $countryId = $this->targetIdResolver()->resolve('jx_countries', $legacyCountryId, 'countries');
            }

            $legacyCityId = $this->normalizedInteger($this->rowValue($row, ['city_id', 'jx_city_id']));
            $cityId = null;

            if ($legacyCityId !== null) {
                $cityId = $this->targetIdResolver()->resolve('jx_cities', $legacyCityId, 'cities');
            }

            $graduationYear = $this->normalizedInteger($this->rowValue($row, $graduationYearKeys));
            $degree = $this->cleanedString($row, ['degree', 'qualification', 'specialization', 'ar_specialization', 'en_specialization']);
            $isFeatured = $this->normalizedBoolean($this->rowValue($row, ['is_featured', 'featured']), false);
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_active', 'active', 'is_enabled']), true);

            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['created_at', 'date_added', 'reg_date']));

            try {
                $alumniId = DB::table('alumni')->insertGetId([
                    'student_identifier' => $studentId,
                    'email' => $email,
                    'phone' => $this->cleanedString($row, ['phone', 'mobile', 'tel']),
                    'faculty_id' => $facultyId,
                    'department_id' => null,
                    'degree' => $degree,
                    'graduation_year' => $graduationYear,
                    'country_id' => $countryId,
                    'city_id' => $cityId,
                    'photo_media_id' => null,
                    'is_featured' => $isFeatured,
                    'is_enabled' => $isEnabled,
                    'created_at' => $createdAt?->toDateTimeString() ?? now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_graduated_students', $sourceId, 'alumni', $alumniId,
                    'success', 'Imported alumni record.',
                    ['graduation_year' => $graduationYear, 'faculty_id' => $facultyId],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_graduated_students', $sourceId, 'unknown_mapping', $e->getMessage(), [
                    'email' => $email,
                ]);
                $this->logSkip($module, $batch, 'jx_graduated_students', $sourceId, 'alumni', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Alumni import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
