<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyPublicStaffImportServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyPublicStaffImportResultDTO;
use App\Enums\PublicationStatus;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyPublicStaffImportService implements LegacyPublicStaffImportServiceInterface
{
    private const APPROVAL_TOKEN = 'public-staff-import';

    private const SOURCE_TABLE = 'jx_councils';

    /** @var array<int, string> */
    private const FACULTY_SERVICE_SLUGS = [
        3 => 'medicine', 4 => 'medicine', 5 => 'dentistry', 6 => 'dentistry',
        7 => 'pharmacy', 8 => 'pharmacy', 9 => 'ai-engineering', 10 => 'ai-engineering',
        11 => 'petroleum', 12 => 'petroleum', 13 => 'business', 14 => 'business',
    ];

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'source_table', 'source_id', 'service_type', 'candidate_faculty_slug', 'approval_decision', 'approved_target',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
        private readonly SlugServiceInterface $slugService,
    ) {}

    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPublicStaffImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing public staff requires --approve='.self::APPROVAL_TOKEN.'.');
        }
        if ($input === null || trim($input) === '') {
            if ($write) {
                throw new InvalidArgumentException('Importing public staff requires an approved review packet CSV input.');
            }

            return new LegacyPublicStaffImportResultDTO(false, $this->batch($batch), 0, 0, 0, 0, 0, []);
        }

        $input = trim($input);
        [$scanned, $approvedRows, $skipped, $reasons] = $this->approvedPacketRows($input, $disk);
        $checksum = hash('sha256', Storage::disk($disk)->get($input));
        $sourceRows = $approvedRows === [] ? collect() : $this->oldDatabase->table(self::SOURCE_TABLE)
            ->whereIn('id', array_keys($approvedRows))->orderBy('id')->get()->keyBy('id');
        [$emailCounts, $arNameCounts, $enNameCounts] = $this->approvedIdentityCounts($sourceRows->all());
        $facultyIds = $this->facultyIds();
        $currentEmails = $this->currentEmails();
        $batch = $this->batch($batch);
        $importable = 0;
        $imported = 0;
        $translationsCreated = 0;

        foreach ($approvedRows as $sourceId => $packetRow) {
            $row = $sourceRows->get($sourceId);
            if (! is_object($row)) {
                $this->skip($reasons, $skipped, 'missing_source');

                continue;
            }

            $sourceService = $this->integerValue($row, 'service_type');
            if ($sourceService !== $packetRow['service_type']) {
                $this->skip($reasons, $skipped, 'source_service_mismatch');

                continue;
            }
            if ($this->alreadyMapped($sourceId)) {
                $this->skip($reasons, $skipped, 'already_mapped');

                continue;
            }

            $email = $this->normalizedValidEmail($this->rawValue($row, 'email'));
            $arName = $this->normalizedUsableName($this->rawValue($row, 'ar_name'));
            $enName = $this->normalizedUsableName($this->rawValue($row, 'en_name'));
            if ($email !== null && ($emailCounts[$email] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_approved_email');

                continue;
            }
            if (($arName !== null && ($arNameCounts[$arName] ?? 0) > 1) || ($enName !== null && ($enNameCounts[$enName] ?? 0) > 1)) {
                $this->skip($reasons, $skipped, 'duplicate_approved_name');

                continue;
            }
            if ($email !== null && isset($currentEmails[$email])) {
                $this->skip($reasons, $skipped, 'current_email_conflict');

                continue;
            }

            $facultyId = $facultyIds[$packetRow['faculty_slug']] ?? null;
            if ($facultyId === null) {
                $this->skip($reasons, $skipped, 'missing_faculty_target');

                continue;
            }

            $cleaned = $this->cleanedRowService->cleanRow('faculty_members', self::SOURCE_TABLE, $row, [
                'ar_data' => 'auto_accept_sanitized_html', 'en_data' => 'auto_accept_sanitized_html',
                'email' => 'auto_approve_cleaned',
            ]);
            $translations = $this->translations($row, $cleaned);
            if ($translations === []) {
                $this->skip($reasons, $skipped, 'missing_usable_name');

                continue;
            }

            $importable++;
            if (! $write) {
                continue;
            }

            $translationsCreated += $this->writeMember(
                row: $row,
                cleaned: $cleaned,
                translations: $translations,
                facultyId: $facultyId,
                sourceId: $sourceId,
                service: $sourceService,
                batch: $batch,
                disk: $disk,
                input: $input,
                checksum: $checksum,
            );
            $imported++;
        }

        ksort($reasons);

        return new LegacyPublicStaffImportResultDTO($write, $batch, $scanned, $importable, $imported, $translationsCreated, $skipped, $reasons);
    }

    /** @return array{0: int, 1: array<int, array{service_type: int, faculty_slug: string}>, 2: int, 3: array<string, int>} */
    private function approvedPacketRows(string $input, string $disk): array
    {
        if (! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('Public staff review packet CSV ['.$input.'] does not exist on disk ['.$disk.'].');
        }
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new InvalidArgumentException('Public staff review packet CSV could not be opened.');
        }
        fwrite($stream, Storage::disk($disk)->get($input));
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Public staff review packet CSV is empty.');
        }
        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            $detail = $missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ' Duplicate headers are not allowed.';
            throw new InvalidArgumentException('Input is not a valid public staff review packet CSV.'.$detail);
        }

        $packetRows = [];
        $scanned = 0;
        $skipped = 0;
        $reasons = [];
        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }
            $scanned++;
            if (count($values) !== count($headers)) {
                $this->skip($reasons, $skipped, 'malformed_packet_row');

                continue;
            }
            /** @var array<string, string> $packetRow */
            $packetRow = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
            $packetRows[] = $packetRow;
        }
        fclose($stream);

        $idCounts = [];
        foreach ($packetRows as $row) {
            if (ctype_digit($row['source_id']) && (int) $row['source_id'] > 0) {
                $idCounts[(int) $row['source_id']] = ($idCounts[(int) $row['source_id']] ?? 0) + 1;
            }
        }

        $approved = [];
        foreach ($packetRows as $row) {
            $decision = Str::lower(trim($row['approval_decision']));
            $target = Str::lower(trim($row['approved_target']));
            if (ctype_digit($row['source_id']) && ($idCounts[(int) $row['source_id']] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_source_id');

                continue;
            }
            if ($decision !== 'import') {
                $this->skip($reasons, $skipped, $decision === '' ? 'blank_approval_decision' : 'approval_decision_not_import');

                continue;
            }
            if ($target !== 'faculty_members') {
                $this->skip($reasons, $skipped, $target === '' ? 'blank_approved_target' : 'approved_target_not_faculty_members');

                continue;
            }
            if ($row['source_table'] !== self::SOURCE_TABLE) {
                $this->skip($reasons, $skipped, 'source_table_mismatch');

                continue;
            }
            if (! ctype_digit($row['source_id']) || (int) $row['source_id'] < 1) {
                $this->skip($reasons, $skipped, 'invalid_source_id');

                continue;
            }
            if (! ctype_digit($row['service_type'])) {
                $this->skip($reasons, $skipped, 'invalid_service_type');

                continue;
            }
            $service = (int) $row['service_type'];
            if ($service <= 2) {
                $this->skip($reasons, $skipped, 'central_service_not_importable');

                continue;
            }
            $expectedSlug = self::FACULTY_SERVICE_SLUGS[$service] ?? null;
            if ($expectedSlug === null) {
                $this->skip($reasons, $skipped, 'invalid_service_type');

                continue;
            }
            if ($row['candidate_faculty_slug'] !== $expectedSlug) {
                $this->skip($reasons, $skipped, 'faculty_mapping_mismatch');

                continue;
            }
            $approved[(int) $row['source_id']] = ['service_type' => $service, 'faculty_slug' => $expectedSlug];
        }

        return [$scanned, $approved, $skipped, $reasons];
    }

    /** @param array<int, object> $rows @return array{array<string, int>, array<string, int>, array<string, int>} */
    private function approvedIdentityCounts(array $rows): array
    {
        $emails = [];
        $arNames = [];
        $enNames = [];
        foreach ($rows as $row) {
            $this->increment($emails, $this->normalizedValidEmail($this->rawValue($row, 'email')));
            $this->increment($arNames, $this->normalizedUsableName($this->rawValue($row, 'ar_name')));
            $this->increment($enNames, $this->normalizedUsableName($this->rawValue($row, 'en_name')));
        }

        return [$emails, $arNames, $enNames];
    }

    /** @return array<string, int> */
    private function facultyIds(): array
    {
        return DB::table('faculties')->whereIn('slug', array_values(array_unique(self::FACULTY_SERVICE_SLUGS)))
            ->pluck('id', 'slug')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array<string, true> */
    private function currentEmails(): array
    {
        $emails = [];
        foreach (DB::table('faculty_members')->whereNotNull('email')->pluck('email') as $value) {
            $email = $this->normalizedValidEmail($value);
            if ($email !== null) {
                $emails[$email] = true;
            }
        }

        return $emails;
    }

    private function alreadyMapped(int $sourceId): bool
    {
        return MigrationLog::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $sourceId)->where('status', 'success')->exists();
    }

    /** @return array<string, array{name: string, position: ?string, bio: ?string, specializations: ?array}> */
    private function translations(object $row, LegacyCleanedRowDTO $cleaned): array
    {
        $translations = [];
        foreach (['ar', 'en'] as $locale) {
            $name = $this->usableName($cleaned->values[$locale.'_name'] ?? $this->rawValue($row, $locale.'_name'));
            if ($name === null) {
                continue;
            }
            $translations[$locale] = [
                'name' => $name,
                'position' => $this->text($cleaned->values[$locale.'_position'] ?? $this->rawValue($row, $locale.'_position')),
                'bio' => $this->text($cleaned->values[$locale.'_data'] ?? null),
                'specializations' => $this->specializations($cleaned->values[$locale.'_specialization'] ?? $this->rawValue($row, $locale.'_specialization')),
            ];
        }

        return $translations;
    }

    /** @param array<string, array{name: string, position: ?string, bio: ?string, specializations: ?array}> $translations */
    private function writeMember(object $row, LegacyCleanedRowDTO $cleaned, array $translations, int $facultyId, int $sourceId, int $service, string $batch, string $disk, string $input, string $checksum): int
    {
        return DB::transaction(function () use ($row, $cleaned, $translations, $facultyId, $sourceId, $service, $batch, $disk, $input, $checksum): int {
            $first = reset($translations);
            $member = FacultyMember::query()->create([
                'slug' => $this->slugService->generate($first['name'], FacultyMember::class),
                'faculty_id' => $facultyId, 'department_id' => null,
                'email' => $this->normalizedValidEmail($cleaned->values['email'] ?? null),
                'phone' => $this->text($this->rawValue($row, 'phone')) ?? $this->text($this->rawValue($row, 'mobile')),
                'photo_media_id' => null, 'cv_media_id' => null,
                'sort_order' => $this->integerValue($row, 'council_order') ?? $sourceId,
                'is_enabled' => false, 'publication_status' => PublicationStatus::Draft->value, 'published_at' => null,
            ]);
            foreach ($translations as $locale => $translation) {
                FacultyMemberTranslation::query()->create([
                    'faculty_member_id' => (int) $member->getKey(), 'locale' => $locale,
                    'full_name' => $translation['name'], 'title' => null, 'position' => $translation['position'],
                    'bio' => $translation['bio'], 'specializations' => $translation['specializations'],
                ]);
            }
            MigrationLog::query()->create([
                'module' => 'public_faculty_members', 'batch_name' => $batch, 'source_table' => self::SOURCE_TABLE,
                'source_id' => $sourceId, 'target_table' => 'faculty_members', 'target_id' => (int) $member->getKey(),
                'status' => 'success', 'message' => 'Imported approved public staff row as a disabled draft with deferred media.',
                'metadata' => [
                    'service_type' => $service, 'legacy_visibility' => $this->rawValue($row, 'is_visible'),
                    'legacy_academic_rank' => $this->rawValue($row, 'academic_rank'),
                    'legacy_photo' => $this->text($this->rawValue($row, 'photo')),
                    'legacy_cv' => $this->text($this->rawValue($row, 'cv')),
                    'legacy_ar_cv' => $this->text($this->rawValue($row, 'ar_cv')),
                    'legacy_raw_email' => $this->text($this->rawValue($row, 'email')),
                    'approval_packet' => ['disk' => $disk, 'path' => $input, 'sha256' => $checksum],
                    'media_deferred' => true, 'locales' => array_keys($translations), 'enabled_on_import' => false,
                ],
            ]);

            return count($translations);
        });
    }

    /** @return list<string>|null */
    private function specializations(mixed $value): ?array
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }
        $items = preg_split('/[,;|،\r\n]+/u', $value) ?: [];
        $items = array_values(array_unique(array_filter(array_map('trim', $items))));

        return $items !== [] ? $items : null;
    }

    private function batch(?string $batch): string
    {
        return $batch !== null && trim($batch) !== '' ? trim($batch) : 'public-staff-'.now()->format('Ymd_His');
    }

    private function usableName(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value !== null && ! in_array(Str::lower($value), ['under construction', 'under construction...'], true) ? $value : null;
    }

    private function normalizedUsableName(mixed $value): ?string
    {
        $value = $this->usableName($value);

        return $value !== null ? Str::lower((string) preg_replace('/\s+/u', ' ', strip_tags($value))) : null;
    }

    private function normalizedValidEmail(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? Str::lower($value) : null;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function integerValue(object $row, string $field): ?int
    {
        $value = $this->rawValue($row, $field);

        return is_numeric($value) ? (int) $value : null;
    }

    private function rawValue(object $row, string $field): mixed
    {
        return property_exists($row, $field) ? $row->{$field} : null;
    }

    /** @param array<string, int> $counts */
    private function increment(array &$counts, ?string $value): void
    {
        if ($value !== null) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
    }

    /** @param array<string, int> $reasons */
    private function skip(array &$reasons, int &$skipped, string $reason): void
    {
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        $skipped++;
    }
}
