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
            $translations = array_filter([
                'ar' => $this->cleanedString($row, ['ar_name', 'name_ar', 'full_name_ar', 'name']),
                'en' => $this->cleanedString($row, ['en_name', 'name_en', 'full_name_en']),
            ], static fn (?string $value): bool => $value !== null && $value !== '');

            if ($translations === []) {
                $this->reject($module, 'jx_graduated_students', $sourceId, 'missing_translation', 'Alumni row has no usable AR/EN full name.');
                $this->logSkip($module, $batch, 'jx_graduated_students', $sourceId, 'alumni', 'Skipped alumni row without AR/EN full name.');
                $skipped++;

                continue;
            }

            if ($email !== null && $email !== '') {
                $validatedEmail = $this->emailValidator()->normalize($email);
                $email = $validatedEmail;
            }

            $legacyFacultyId = $this->normalizedInteger($this->rowValue($row, $facultyKeys));
            $facultyId = $this->resolveLegacyStudentFacultyId($legacyFacultyId);

            if ($facultyId === null) {
                $this->reject($module, 'jx_graduated_students', $sourceId, 'missing_parent', 'Could not resolve student faculty from legacy department code.', [
                    'legacy_faculty_code' => $legacyFacultyId,
                ]);
                $this->logSkip($module, $batch, 'jx_graduated_students', $sourceId, 'alumni', 'Missing student faculty mapping.');
                $skipped++;

                continue;
            }

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
            $isEnabled = $this->normalizedLegacyVisibility($row, true);

            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['post_date', 'created_at', 'date_added', 'reg_date']));

            try {
                $alumniId = DB::transaction(function () use ($row, $studentId, $email, $facultyId, $degree, $graduationYear, $countryId, $cityId, $isFeatured, $isEnabled, $createdAt, $translations): int {
                    $timestamp = now();
                    $photoMediaId = $this->legacyMediaAssetId(
                        $this->cleanedString($row, 'photo'),
                        'students/alumni',
                        $translations['ar'] ?? null,
                        $translations['en'] ?? null,
                    );

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
                        'photo_media_id' => $photoMediaId,
                        'is_featured' => $isFeatured,
                        'is_enabled' => $isEnabled,
                        'created_at' => $createdAt?->toDateTimeString() ?? $timestamp->toDateTimeString(),
                        'updated_at' => $timestamp,
                    ]);

                    DB::table('alumni_translations')->insert(array_map(
                        static fn (string $locale, string $fullName): array => [
                            'alumni_id' => $alumniId,
                            'locale' => $locale,
                            'full_name' => $fullName,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ],
                        array_keys($translations),
                        array_values($translations),
                    ));

                    return $alumniId;
                });

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_graduated_students', $sourceId, 'alumni', $alumniId,
                    'success', 'Imported alumni record.',
                    [
                        'graduation_year' => $graduationYear,
                        'faculty_id' => $facultyId,
                        'section_id' => $this->normalizedInteger($this->rowValue($row, 'section_id')),
                        'grade' => $this->rowValue($row, 'grade'),
                        'locales' => array_keys($translations),
                    ],
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
