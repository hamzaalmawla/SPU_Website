<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyFacultyMembersSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'faculty_members';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_members');
        $sourceTable = 'jx_members';
        $facultyKeys = $this->legacyAvailableColumns('jx_members', ['category_id', 'cat_id', 'jx_member_category_id']);

        if ($rows->isEmpty()) {
            $rows = $this->legacyRows('jx_councils1');
            $sourceTable = 'jx_councils1';
            $this->command?->warn('No rows found in jx_members. Falling back to jx_councils1 for faculty members.');
        }

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found for faculty members import.');

            return;
        }

        $this->command?->info("Starting faculty members import from {$sourceTable}: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported($sourceTable, $sourceId, 'faculty_members')) {
                $skipped++;

                continue;
            }

            $nameAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'full_name_ar', 'full_name']);
            $nameEn = $this->cleanedString($row, ['en_name', 'name_en', 'full_name_en', 'full_name']);

            if (($nameAr === null || $nameAr === '') && ($nameEn === null || $nameEn === '')) {
                $this->reject($module, $sourceTable, $sourceId, 'unknown_mapping', 'Faculty member has no usable name.');
                $this->logSkip($module, $batch, $sourceTable, $sourceId, 'faculty_members', 'No name found.');
                $skipped++;

                continue;
            }

            $email = $this->cleanedString($row, ['email', 'mail']);

            if ($email !== null && $email !== '') {
                $email = $this->emailValidator()->normalize($email);
            }

            $facultyId = null;

            if ($sourceTable === 'jx_members') {
                $legacyCategoryId = $this->normalizedInteger($this->rowValue($row, $facultyKeys));
                $facultyId = $this->resolveLegacyFacultyId($legacyCategoryId);
            } else {
                $serviceType = $this->normalizedInteger($this->rowValue($row, 'service_type'));
                $facultyId = $this->resolveLegacyFacultyIdByServiceType($serviceType);

                if ($facultyId === null) {
                    $this->logSkip(
                        $module,
                        $batch,
                        $sourceTable,
                        $sourceId,
                        'faculty_members',
                        'Skipped row outside mapped faculty service types.',
                        ['service_type' => $serviceType],
                    );
                    $skipped++;

                    continue;
                }
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['council_order', 'order', 'sort_order', 'record_order'])) ?? 0;
            $isEnabled = $this->normalizedBoolean($this->rowValue($row, ['is_visible', 'is_active', 'active', 'is_enabled']), true);

            try {
                $memberId = DB::table('faculty_members')->insertGetId([
                    'faculty_id' => $facultyId,
                    'department_id' => null,
                    'email' => $email,
                    'phone' => $this->cleanedString($row, ['phone', 'mobile', 'tel']),
                    'photo_media_id' => null,
                    'cv_media_id' => null,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => $this->dateNormalizer()->normalize($this->rowValue($row, ['created_at', 'added_date', 'reg_date', 'date_added']))?->toDateTimeString() ?? now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($nameAr !== null && $nameAr !== '') {
                    $translations[] = [
                        'faculty_member_id' => $memberId,
                        'locale' => 'ar',
                        'full_name' => $nameAr,
                        'title' => $this->cleanedString($row, ['ar_title', 'title_ar']),
                        'position' => $this->cleanedString($row, ['ar_position', 'position_ar']),
                        'bio' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['ar_data', 'ar_bio', 'bio_ar', 'ar_description'], '')
                        ) ?: null,
                        'specializations' => $this->decodeJson(
                            (string) $this->rowValue($row, ['ar_specializations', 'specializations_ar'], '')
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($nameEn !== null && $nameEn !== '') {
                    $translations[] = [
                        'faculty_member_id' => $memberId,
                        'locale' => 'en',
                        'full_name' => $nameEn,
                        'title' => $this->cleanedString($row, ['en_title', 'title_en']),
                        'position' => $this->cleanedString($row, ['en_position', 'position_en']),
                        'bio' => $this->htmlSanitizer()->sanitize(
                            (string) $this->rowValue($row, ['en_data', 'en_bio', 'bio_en', 'en_description'], '')
                        ) ?: null,
                        'specializations' => $this->decodeJson(
                            (string) $this->rowValue($row, ['en_specializations', 'specializations_en'], '')
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('faculty_member_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module,
                    $batch,
                    $sourceTable,
                    $sourceId,
                    'faculty_members',
                    $memberId,
                    'success',
                    'Imported faculty member with translations.',
                    ['email' => $email, 'faculty_id' => $facultyId],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, $sourceTable, $sourceId, 'unknown_mapping', $e->getMessage(), ['email' => $email]);
                $this->logSkip($module, $batch, $sourceTable, $sourceId, 'faculty_members', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Faculty members import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
