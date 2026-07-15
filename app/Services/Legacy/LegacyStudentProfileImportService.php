<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyStudentProfileImportResultDTO;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyStudentProfileImportService implements LegacyStudentProfileImportServiceInterface
{
    private const LANES = [
        'alumni' => [
            'module' => 'alumni',
            'source_table' => 'jx_graduated_students',
            'target_table' => 'alumni',
            'approval' => 'phase6-alumni',
        ],
        'honor_students' => [
            'module' => 'honor_students',
            'source_table' => 'jx_good_students',
            'target_table' => 'honor_students',
            'approval' => 'phase6-honor-students',
        ],
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(string $lane, bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false): LegacyStudentProfileImportResultDTO
    {
        $lane = $this->normalizeLane($lane);
        $definition = self::LANES[$lane];

        if ($write && $approval !== $definition['approval']) {
            throw new InvalidArgumentException('Importing '.$lane.' requires --approve='.$definition['approval'].'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-'.$lane.'-'.now()->format('Ymd_His');
        $rows = $this->oldDatabase->table($definition['source_table'])->orderBy('id')->get()->all();
        $seenDuplicateKeys = [];
        $importableRows = 0;
        $importedRows = 0;
        $skippedRows = 0;
        $duplicateSkippedRows = 0;
        $skipReasonCounts = [];

        foreach ($rows as $row) {
            $sourceId = $this->integerValue($row, 'id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
                $skippedRows++;

                continue;
            }

            $row = $this->applySourceOverrides($lane, $sourceId, $row);

            if ($this->alreadyProcessed($definition['module'], $definition['source_table'], $definition['target_table'], $sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_processed');
                $skippedRows++;

                continue;
            }

            $cleaned = $this->cleanedRowService->cleanRow($definition['module'], $definition['source_table'], $row);

            if (! $cleaned->canImportPublicly) {
                $this->countSkip($skipReasonCounts, 'cleaning_blocked');
                $skippedRows++;
                $this->writeSkip($write, $definition['module'], $batch, $definition['source_table'], $sourceId, $definition['target_table'], 'Cleaning blocked this student profile row.');

                continue;
            }

            $names = $this->names($cleaned, $row);

            if ($names === []) {
                $this->countSkip($skipReasonCounts, 'missing_name');
                $skippedRows++;
                $this->writeSkip($write, $definition['module'], $batch, $definition['source_table'], $sourceId, $definition['target_table'], 'Skipped student profile row without AR/EN name.');

                continue;
            }

            $facultyId = $this->facultyId($this->integerValue($row, 'department_id'));

            if ($facultyId === null) {
                $this->countSkip($skipReasonCounts, 'missing_faculty_mapping');
                $skippedRows++;
                $this->writeSkip($write, $definition['module'], $batch, $definition['source_table'], $sourceId, $definition['target_table'], 'Skipped student profile row without current faculty mapping.');

                continue;
            }

            $duplicateKeys = $this->duplicateKeys($lane, $row, $names);
            $duplicateKey = $this->firstDuplicateKey($duplicateKeys, $seenDuplicateKeys);

            if ($duplicateKey !== null) {
                $this->countSkip($skipReasonCounts, 'duplicate_source_row');
                $skippedRows++;
                $duplicateSkippedRows++;
                $this->writeSkip($write, $definition['module'], $batch, $definition['source_table'], $sourceId, $definition['target_table'], 'Skipped duplicate student profile source row.', [
                    'canonical_source_id' => $seenDuplicateKeys[$duplicateKey],
                    'duplicate_key' => $duplicateKey,
                ]);

                continue;
            }

            foreach ($duplicateKeys as $duplicateKey) {
                $seenDuplicateKeys[$duplicateKey] = $sourceId;
            }

            $importableRows++;

            if (! $write) {
                continue;
            }

            $targetId = $lane === 'alumni'
                ? $this->writeAlumni($row, $facultyId, $names, $enable)
                : $this->writeHonorStudent($row, $facultyId, $names, $enable);
            $this->writeSuccess($definition['module'], $batch, $definition['source_table'], $sourceId, $definition['target_table'], $targetId, $row, $names, $enable);
            $importedRows++;
        }

        if ($importedRows > 0 && ! $this->cacheService->flushTags(['facilities', 'public-pages', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        return new LegacyStudentProfileImportResultDTO(
            lane: $lane,
            written: $write,
            batch: $batch,
            enabledOnImport: $enable,
            scannedRows: count($rows),
            importableRows: $importableRows,
            importedRows: $importedRows,
            skippedRows: $skippedRows,
            duplicateSkippedRows: $duplicateSkippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function normalizeLane(string $lane): string
    {
        $lane = trim($lane);

        if (! isset(self::LANES[$lane])) {
            throw new InvalidArgumentException('Unsupported student profile lane: '.$lane.'. Expected alumni or honor_students.');
        }

        return $lane;
    }

    private function applySourceOverrides(string $lane, int $sourceId, object $row): object
    {
        $overrides = config("legacy_student_profile_overrides.{$lane}.{$sourceId}", []);

        if (! is_array($overrides) || $overrides === []) {
            return $row;
        }

        $overrides = array_intersect_key($overrides, array_flip(['ar_name', 'en_name', 'grade']));

        return (object) array_replace((array) $row, $overrides);
    }

    private function alreadyProcessed(string $module, string $sourceTable, string $targetTable, int $sourceId): bool
    {
        return MigrationLog::query()
            ->where('module', $module)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('target_table', $targetTable)
            ->whereIn('status', ['success', 'skipped'])
            ->exists();
    }

    /** @return array{ar?: string, en?: string} */
    private function names(LegacyCleanedRowDTO $cleaned, object $row): array
    {
        $ar = $this->stringValue($cleaned->values['ar_name'] ?? $this->rawValue($row, 'ar_name'));
        $en = $this->stringValue($cleaned->values['en_name'] ?? $this->rawValue($row, 'en_name'));
        $names = [];

        if ($ar !== null) {
            $names['ar'] = $ar;
        }

        if ($en !== null) {
            $names['en'] = $en;
        }

        return $names;
    }

    private function facultyId(?int $legacyFacultyCode): ?int
    {
        if ($legacyFacultyCode === null) {
            return null;
        }

        $definition = config('legacy_student_taxonomy.faculty_code_map.'.$legacyFacultyCode);
        $slug = is_array($definition) ? ($definition['canonical_slug'] ?? null) : null;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $faculty = Faculty::query()->where('slug', $slug)->first();

        return $faculty instanceof Faculty ? (int) $faculty->getKey() : null;
    }

    /** @param array{ar?: string, en?: string} $names @return list<string> */
    private function duplicateKeys(string $lane, object $row, array $names): array
    {
        $department = (string) ($this->integerValue($row, 'department_id') ?? '');
        $section = (string) ($this->integerValue($row, 'section_id') ?? '');
        $arName = mb_strtolower($names['ar'] ?? '');
        $enName = mb_strtolower($names['en'] ?? '');

        if ($lane === 'honor_students') {
            return $arName !== ''
                ? ['ar|'.$department.'|'.$section.'|'.$arName.'|'.(string) ($this->integerValue($row, 'date_year') ?? '')]
                : [];
        }

        return array_values(array_filter([
            $arName !== '' ? 'ar|'.$department.'|'.$section.'|'.$arName : null,
            $enName !== '' ? 'en|'.$department.'|'.$section.'|'.$enName : null,
        ]));
    }

    /** @param list<string> $duplicateKeys @param array<string, int> $seenDuplicateKeys */
    private function firstDuplicateKey(array $duplicateKeys, array $seenDuplicateKeys): ?string
    {
        foreach ($duplicateKeys as $duplicateKey) {
            if (isset($seenDuplicateKeys[$duplicateKey])) {
                return $duplicateKey;
            }
        }

        return null;
    }

    /** @param array{ar?: string, en?: string} $names */
    private function writeAlumni(object $row, int $facultyId, array $names, bool $enable): int
    {
        return DB::transaction(function () use ($row, $facultyId, $names, $enable): int {
            $alumni = Alumni::query()->create([
                'student_identifier' => null,
                'email' => null,
                'phone' => null,
                'faculty_id' => $facultyId,
                'department_id' => null,
                'degree' => null,
                'graduation_year' => $this->integerValue($row, 'date_year') ?? $this->integerValue($row, 'year'),
                'country_id' => null,
                'city_id' => null,
                'photo_media_id' => null,
                'is_featured' => false,
                'is_enabled' => $enable && $this->visible($row),
            ]);

            $this->writeTranslations($alumni, $names);

            return (int) $alumni->getKey();
        });
    }

    /** @param array{ar?: string, en?: string} $names */
    private function writeHonorStudent(object $row, int $facultyId, array $names, bool $enable): int
    {
        return DB::transaction(function () use ($row, $facultyId, $names, $enable): int {
            $honorStudent = HonorStudent::query()->create([
                'student_identifier' => null,
                'faculty_id' => $facultyId,
                'department_id' => null,
                'academic_year' => $this->academicYear($row),
                'gpa' => $this->gpa($row),
                'photo_media_id' => null,
                'sort_order' => $this->integerValue($row, 's_order') ?? $this->integerValue($row, 'record_order') ?? $this->integerValue($row, 'id') ?? 0,
                'is_enabled' => $enable && $this->visible($row),
            ]);

            $this->writeTranslations($honorStudent, $names);

            return (int) $honorStudent->getKey();
        });
    }

    /** @param Alumni|HonorStudent $record @param array{ar?: string, en?: string} $names */
    private function writeTranslations(Alumni|HonorStudent $record, array $names): void
    {
        $fallback = $names['ar'] ?? $names['en'] ?? null;

        if ($fallback === null) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $name = $names[$locale] ?? $fallback;

            if ($record instanceof Alumni) {
                AlumniTranslation::query()->create([
                    'alumni_id' => (int) $record->getKey(),
                    'locale' => $locale,
                    'full_name' => $name,
                ]);

                continue;
            }

            HonorStudentTranslation::query()->create([
                'honor_student_id' => (int) $record->getKey(),
                'locale' => $locale,
                'full_name' => $name,
            ]);
        }
    }

    private function academicYear(object $row): string
    {
        $dateYear = $this->integerValue($row, 'date_year');
        $year = $this->integerValue($row, 'year');

        if ($dateYear !== null && $year !== null && $year > 0) {
            return $dateYear.' / '.$year;
        }

        if ($dateYear !== null) {
            return (string) $dateYear;
        }

        if ($year !== null && $year > 0) {
            return 'year '.$year;
        }

        return 'unknown';
    }

    private function gpa(object $row): ?float
    {
        $value = $this->rawValue($row, 'grade');

        if (is_string($value)) {
            $value = trim(str_replace('%', '', $value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        $gpa = round((float) $value, 2);

        return $gpa >= 0 && $gpa <= 99.99 ? $gpa : null;
    }

    private function visible(object $row): bool
    {
        $value = $this->rawValue($row, 'is_visible');

        return $value === null || (string) $value === '1' || $value === 1 || $value === true;
    }

    private function integerValue(object $row, string $key): ?int
    {
        $value = $this->rawValue($row, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function rawValue(object $row, string $key): mixed
    {
        return property_exists($row, $key) ? $row->{$key} : null;
    }

    /** @param array<string, int> $skipReasonCounts */
    private function countSkip(array &$skipReasonCounts, string $reason): void
    {
        $skipReasonCounts[$reason] = ($skipReasonCounts[$reason] ?? 0) + 1;
    }

    /** @param array<string, mixed> $metadata */
    private function writeSkip(bool $write, string $module, string $batch, string $sourceTable, int $sourceId, string $targetTable, string $message, array $metadata = []): void
    {
        if (! $write) {
            return;
        }

        MigrationLog::query()->create([
            'module' => $module,
            'batch_name' => $batch,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_table' => $targetTable,
            'target_id' => null,
            'status' => 'skipped',
            'message' => $message,
            'metadata' => $metadata + ['phase' => 'phase6', 'db_first' => true],
        ]);
    }

    /** @param array{ar?: string, en?: string} $names */
    private function writeSuccess(string $module, string $batch, string $sourceTable, int $sourceId, string $targetTable, int $targetId, object $row, array $names, bool $enable): void
    {
        MigrationLog::query()->create([
            'module' => $module,
            'batch_name' => $batch,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported DB-first legacy student profile without media attachment.',
            'metadata' => [
                'phase' => 'phase6',
                'db_first' => true,
                'enabled_on_import' => $enable,
                'legacy_department_id' => $this->integerValue($row, 'department_id'),
                'legacy_section_id' => $this->integerValue($row, 'section_id'),
                'legacy_grade' => $this->stringValue($this->rawValue($row, 'grade')),
                'legacy_photo' => $this->stringValue($this->rawValue($row, 'photo')),
                'legacy_post_date' => $this->stringValue($this->rawValue($row, 'post_date')),
                'locales' => array_keys($names),
            ],
        ]);
    }
}
