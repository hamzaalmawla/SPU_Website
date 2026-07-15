<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyFacultyProfileImportServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyFacultyProfileImportResultDTO;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyFacultyProfileImportService implements LegacyFacultyProfileImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-faculty-members';

    private const SOURCE_TABLE = 'jx_councils1';

    /** @var array<int, string> */
    private const FACULTY_SERVICE_TYPE_SLUGS = [
        4 => 'medicine',
        6 => 'dentistry',
        8 => 'pharmacy',
        10 => 'ai-engineering',
        12 => 'petroleum',
        14 => 'business',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
        private readonly SlugServiceInterface $slugService,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false): LegacyFacultyProfileImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 faculty members requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-faculty-members-'.now()->format('Ymd_His');
        $rows = $this->oldDatabase->table(self::SOURCE_TABLE)->orderBy('id')->get()->all();
        $importableRows = 0;
        $importedRows = 0;
        $skippedRows = 0;
        $skipReasonCounts = [];

        foreach ($rows as $row) {
            $sourceId = $this->integerValue($row, 'id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
                $skippedRows++;

                continue;
            }

            if ($this->alreadyProcessed($sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_processed');
                $skippedRows++;

                continue;
            }

            $serviceType = $this->integerValue($row, 'service_type');
            $facultyId = $this->facultyId($serviceType);

            if ($facultyId === null) {
                $this->countSkip($skipReasonCounts, 'deferred_non_faculty_staff_row');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Deferred council/leadership row for later person/council split.', [
                    'legacy_service_type' => $serviceType,
                ]);

                continue;
            }

            $cleaned = $this->cleanedRowService->cleanRow('faculty_members', self::SOURCE_TABLE, $row, [
                'ar_data' => 'auto_accept_sanitized_html',
                'en_data' => 'auto_accept_sanitized_html',
                'cv' => 'auto_approve_cleaned',
            ]);

            if (! $cleaned->canImportPublicly) {
                $this->countSkip($skipReasonCounts, 'cleaning_blocked');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Cleaning blocked this faculty profile row.', [
                    'blocked_fields' => $cleaned->blockedFields,
                    'legacy_service_type' => $serviceType,
                ]);

                continue;
            }

            $names = $this->names($cleaned, $row);

            if ($names === []) {
                $this->countSkip($skipReasonCounts, 'missing_name');
                $skippedRows++;
                $this->writeSkip($write, $batch, $sourceId, 'Skipped faculty profile row without AR/EN name.', [
                    'legacy_service_type' => $serviceType,
                ]);

                continue;
            }

            $importableRows++;

            if (! $write) {
                continue;
            }

            $targetId = $this->writeFacultyMember($row, $cleaned, $facultyId, $names, $enable);
            $this->writeSuccess($batch, $sourceId, $targetId, $row, $names, $enable);
            $importedRows++;
        }

        return new LegacyFacultyProfileImportResultDTO(
            written: $write,
            batch: $batch,
            enabledOnImport: $enable,
            scannedRows: count($rows),
            importableRows: $importableRows,
            importedRows: $importedRows,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function alreadyProcessed(int $sourceId): bool
    {
        return MigrationLog::query()
            ->where('module', 'faculty_members')
            ->where('source_table', self::SOURCE_TABLE)
            ->where('source_id', $sourceId)
            ->where('target_table', 'faculty_members')
            ->whereIn('status', ['success', 'skipped'])
            ->exists();
    }

    private function facultyId(?int $serviceType): ?int
    {
        $slug = $serviceType !== null ? (self::FACULTY_SERVICE_TYPE_SLUGS[$serviceType] ?? null) : null;

        if ($slug === null) {
            return null;
        }

        $faculty = Faculty::query()->where('slug', $slug)->first();

        return $faculty instanceof Faculty ? (int) $faculty->getKey() : null;
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

    /** @param array{ar?: string, en?: string} $names */
    private function writeFacultyMember(object $row, LegacyCleanedRowDTO $cleaned, int $facultyId, array $names, bool $enable): int
    {
        return DB::transaction(function () use ($row, $cleaned, $facultyId, $names, $enable): int {
            $member = FacultyMember::query()->create([
                'slug' => $this->slugService->generate($names['ar'] ?? $names['en'] ?? 'faculty-member', FacultyMember::class),
                'faculty_id' => $facultyId,
                'department_id' => null,
                'email' => $this->email($cleaned),
                'phone' => $this->stringValue($this->rawValue($row, 'phone')) ?? $this->stringValue($this->rawValue($row, 'mobile')),
                'photo_media_id' => null,
                'cv_media_id' => null,
                'sort_order' => $this->integerValue($row, 'council_order') ?? $this->integerValue($row, 'id') ?? 0,
                'is_enabled' => $enable && $this->visible($row),
            ]);

            $this->writeTranslations($member, $row, $cleaned, $names);

            return (int) $member->getKey();
        });
    }

    private function email(LegacyCleanedRowDTO $cleaned): ?string
    {
        $email = $this->stringValue($cleaned->values['email'] ?? null);

        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** @param array{ar?: string, en?: string} $names */
    private function writeTranslations(FacultyMember $member, object $row, LegacyCleanedRowDTO $cleaned, array $names): void
    {
        $fallback = $names['ar'] ?? $names['en'] ?? null;

        if ($fallback === null) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $name = $names[$locale] ?? $fallback;
            $bioKey = $locale.'_data';
            $positionKey = $locale.'_position';
            $specializationKey = $locale.'_specialization';

            FacultyMemberTranslation::query()->create([
                'faculty_member_id' => (int) $member->getKey(),
                'locale' => $locale,
                'full_name' => $name,
                'title' => null,
                'position' => $this->stringValue($cleaned->values[$positionKey] ?? $this->rawValue($row, $positionKey)),
                'bio' => $this->stringValue($cleaned->values[$bioKey] ?? $this->rawValue($row, $bioKey)),
                'specializations' => $this->specializations($cleaned->values[$specializationKey] ?? $this->rawValue($row, $specializationKey)),
            ]);
        }
    }

    /** @return list<string>|null */
    private function specializations(mixed $value): ?array
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            return null;
        }

        $items = preg_split('/[,;|،\r\n]+/u', $value) ?: [];
        $items = array_values(array_unique(array_filter(array_map('trim', $items))));

        return $items !== [] ? $items : null;
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
    private function writeSkip(bool $write, string $batch, int $sourceId, string $message, array $metadata = []): void
    {
        if (! $write) {
            return;
        }

        MigrationLog::query()->create([
            'module' => 'faculty_members',
            'batch_name' => $batch,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $sourceId,
            'target_table' => 'faculty_members',
            'target_id' => null,
            'status' => 'skipped',
            'message' => $message,
            'metadata' => $metadata + ['phase' => 'phase6', 'db_first' => true],
        ]);
    }

    /** @param array{ar?: string, en?: string} $names */
    private function writeSuccess(string $batch, int $sourceId, int $targetId, object $row, array $names, bool $enable): void
    {
        MigrationLog::query()->create([
            'module' => 'faculty_members',
            'batch_name' => $batch,
            'source_table' => self::SOURCE_TABLE,
            'source_id' => $sourceId,
            'target_table' => 'faculty_members',
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported DB-first legacy faculty profile without media attachments.',
            'metadata' => [
                'phase' => 'phase6',
                'db_first' => true,
                'enabled_on_import' => $enable,
                'legacy_service_type' => $this->integerValue($row, 'service_type'),
                'legacy_academic_rank' => $this->integerValue($row, 'academic_rank'),
                'legacy_photo' => $this->stringValue($this->rawValue($row, 'photo')),
                'legacy_cv' => $this->stringValue($this->rawValue($row, 'cv')),
                'legacy_email' => $this->stringValue($this->rawValue($row, 'email')),
                'locales' => array_keys($names),
            ],
        ]);
    }
}
