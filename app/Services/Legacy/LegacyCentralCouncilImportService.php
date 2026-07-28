<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCentralCouncilImportServiceInterface;
use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\DTOs\Legacy\LegacyCentralCouncilImportResultDTO;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\Models\Faculty\Council;
use App\Models\Faculty\CouncilTranslation;
use App\Models\Person\CouncilMember;
use App\Models\Person\CouncilMemberTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyCentralCouncilImportService implements LegacyCentralCouncilImportServiceInterface
{
    private const APPROVAL_TOKEN = 'central-councils-import';

    private const SOURCE_TABLE = 'jx_councils';

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'source_table', 'source_id', 'service_type', 'candidate_target_module', 'candidate_faculty_slug',
        'approval_decision', 'approved_target',
    ];

    /** @var array<int, array{slug: string, type: string, order: int, ar: string, en: string}> */
    private const COUNCILS = [
        1 => ['slug' => 'university-board', 'type' => 'board', 'order' => 1, 'ar' => 'مجلس الأمناء', 'en' => 'Board of Trustees'],
        2 => ['slug' => 'university-council', 'type' => 'university_council', 'order' => 2, 'ar' => 'مجلس الجامعة', 'en' => 'University Council'],
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
    ) {}

    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyCentralCouncilImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing central councils requires --approve='.self::APPROVAL_TOKEN.'.');
        }
        if ($input === null || trim($input) === '') {
            if ($write) {
                throw new InvalidArgumentException('Importing central councils requires an approved review packet CSV input.');
            }

            return new LegacyCentralCouncilImportResultDTO(false, $this->batch($batch), 0, 0, 0, 0, 0, 0, 0, []);
        }

        $input = trim($input);
        [$scanned, $approvedRows, $skipped, $reasons] = $this->approvedPacketRows($input, $disk);
        $packet = Storage::disk($disk)->get($input);
        $checksum = hash('sha256', $packet);
        $sourceRows = $approvedRows === [] ? collect() : $this->oldDatabase->table(self::SOURCE_TABLE)
            ->whereIn('id', array_keys($approvedRows))->orderBy('id')->get()->keyBy('id');
        $batch = $this->batch($batch);
        $importable = 0;
        $imported = 0;
        $councilsCreated = 0;
        $membersCreated = 0;
        $translationsCreated = 0;

        foreach ($approvedRows as $sourceId => $packetRow) {
            $row = $sourceRows->get($sourceId);
            if (! is_object($row)) {
                $this->skip($reasons, $skipped, 'missing_source');

                continue;
            }
            $service = $this->integerValue($row, 'service_type');
            if ($service !== $packetRow['service_type']) {
                $this->skip($reasons, $skipped, 'source_service_mismatch');

                continue;
            }
            if ($this->alreadyMapped($sourceId)) {
                $this->skip($reasons, $skipped, 'already_mapped');

                continue;
            }

            $context = self::COUNCILS[$service];
            $existingCouncil = Council::withTrashed()->where('slug', $context['slug'])->first();
            if ($existingCouncil !== null && ($existingCouncil->trashed() || $existingCouncil->type !== $context['type'] || $existingCouncil->is_enabled)) {
                $this->skip($reasons, $skipped, 'manual_council_conflict');

                continue;
            }

            $cleaned = $this->cleanedRowService->cleanRow('faculty_members', self::SOURCE_TABLE, $row, [
                'ar_data' => 'auto_accept_sanitized_html',
                'en_data' => 'auto_accept_sanitized_html',
            ]);
            $translations = $this->translations($cleaned);
            if ($translations === []) {
                $this->skip($reasons, $skipped, 'missing_usable_name');

                continue;
            }

            $importable++;
            if (! $write) {
                continue;
            }

            $written = $this->writeMember($row, $cleaned, $translations, $sourceId, $service, $batch, $disk, $input, $checksum);
            $imported++;
            $membersCreated++;
            $councilsCreated += $written['councils'];
            $translationsCreated += $written['translations'];
        }

        ksort($reasons);

        return new LegacyCentralCouncilImportResultDTO(
            $write, $batch, $scanned, $importable, $imported, $councilsCreated, $membersCreated,
            $translationsCreated, $skipped, $reasons,
        );
    }

    /** @return array{0: int, 1: array<int, array{service_type: int}>, 2: int, 3: array<string, int>} */
    private function approvedPacketRows(string $input, string $disk): array
    {
        if (! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('Central council review packet CSV ['.$input.'] does not exist on disk ['.$disk.'].');
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new InvalidArgumentException('Central council review packet CSV could not be opened.');
        }
        fwrite($stream, Storage::disk($disk)->get($input));
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Central council review packet CSV is empty.');
        }
        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing !== [] || in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            $detail = $missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ' Headers must be non-blank and unique.';
            throw new InvalidArgumentException('Input is not a valid central council review packet CSV.'.$detail);
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
            $decision = Str::lower($row['approval_decision']);
            $target = Str::lower($row['approved_target']);
            if (ctype_digit($row['source_id']) && ($idCounts[(int) $row['source_id']] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_source_id');

                continue;
            }
            if ($decision !== 'import') {
                $this->skip($reasons, $skipped, $decision === '' ? 'blank_approval_decision' : 'approval_decision_not_import');

                continue;
            }
            if ($target !== 'council_members') {
                $this->skip($reasons, $skipped, $target === '' ? 'blank_approved_target' : 'approved_target_not_council_members');

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
            if (! ctype_digit($row['service_type']) || ! isset(self::COUNCILS[(int) $row['service_type']])) {
                $this->skip($reasons, $skipped, 'invalid_service_type');

                continue;
            }
            if ($row['candidate_target_module'] !== 'councils') {
                $this->skip($reasons, $skipped, 'target_module_mismatch');

                continue;
            }
            if ($row['candidate_faculty_slug'] !== '') {
                $this->skip($reasons, $skipped, 'faculty_scope_mismatch');

                continue;
            }
            $approved[(int) $row['source_id']] = ['service_type' => (int) $row['service_type']];
        }

        return [$scanned, $approved, $skipped, $reasons];
    }

    /** @return array<string, array{name: string, position: ?string, bio: ?string}> */
    private function translations(LegacyCleanedRowDTO $cleaned): array
    {
        $translations = [];
        foreach (['ar', 'en'] as $locale) {
            $name = $this->usableName($cleaned->values[$locale.'_name'] ?? null);
            if ($name === null) {
                continue;
            }
            $translations[$locale] = [
                'name' => $name,
                'position' => $this->text($cleaned->values[$locale.'_position'] ?? null),
                'bio' => $this->text($cleaned->values[$locale.'_data'] ?? null),
            ];
        }

        return $translations;
    }

    /**
     * @param  array<string, array{name: string, position: ?string, bio: ?string}>  $translations
     * @return array{councils: int, translations: int}
     */
    private function writeMember(object $row, LegacyCleanedRowDTO $cleaned, array $translations, int $sourceId, int $service, string $batch, string $disk, string $input, string $checksum): array
    {
        return DB::transaction(function () use ($row, $cleaned, $translations, $sourceId, $service, $batch, $disk, $input, $checksum): array {
            $context = self::COUNCILS[$service];
            $council = Council::query()->where('slug', $context['slug'])->lockForUpdate()->first();
            $councilsCreated = 0;
            $translationCount = 0;
            if ($council === null) {
                $council = Council::query()->create([
                    'slug' => $context['slug'], 'type' => $context['type'], 'sort_order' => $context['order'], 'is_enabled' => false,
                ]);
                $councilsCreated = 1;
            }
            foreach (['ar' => $context['ar'], 'en' => $context['en']] as $locale => $name) {
                $translation = CouncilTranslation::query()->updateOrCreate(
                    ['council_id' => (int) $council->getKey(), 'locale' => $locale],
                    ['name' => $name, 'description' => null],
                );
                $translationCount += $translation->wasRecentlyCreated ? 1 : 0;
            }

            $member = CouncilMember::query()->create([
                'council_id' => (int) $council->getKey(), 'faculty_member_id' => null,
                'sort_order' => $this->integerValue($row, 'council_order') ?? $sourceId, 'is_enabled' => false,
            ]);
            foreach ($translations as $locale => $translation) {
                CouncilMemberTranslation::query()->create([
                    'council_member_id' => (int) $member->getKey(), 'locale' => $locale,
                    'full_name' => $translation['name'], 'position' => $translation['position'], 'bio' => $translation['bio'],
                ]);
                $translationCount++;
            }
            MigrationLog::query()->create([
                'module' => 'central_council_members', 'batch_name' => $batch, 'source_table' => self::SOURCE_TABLE,
                'source_id' => $sourceId, 'target_table' => 'council_members', 'target_id' => (int) $member->getKey(),
                'status' => 'success', 'message' => 'Imported approved central council member as disabled review/archive data.',
                'metadata' => [
                    'service_type' => $service,
                    'legacy_service' => $this->rawValue($row, 'service_type'),
                    'legacy_visibility' => $this->rawValue($row, 'is_visible'),
                    'legacy_academic_rank' => $this->rawValue($row, 'academic_rank'),
                    'legacy_email' => $this->text($cleaned->values['email'] ?? $this->rawValue($row, 'email')),
                    'legacy_phone' => $this->text($this->rawValue($row, 'phone')),
                    'legacy_mobile' => $this->text($this->rawValue($row, 'mobile')),
                    'legacy_photo' => $this->text($this->rawValue($row, 'photo')),
                    'legacy_cv' => $this->text($this->rawValue($row, 'cv')),
                    'legacy_ar_cv' => $this->text($this->rawValue($row, 'ar_cv')),
                    'approval_packet' => ['disk' => $disk, 'path' => $input, 'sha256' => $checksum],
                    'locales' => array_keys($translations), 'enabled_on_import' => false, 'faculty_identity_linked' => false,
                ],
            ]);

            return ['councils' => $councilsCreated, 'translations' => $translationCount];
        });
    }

    private function alreadyMapped(int $sourceId): bool
    {
        return MigrationLog::query()->where('module', 'central_council_members')->where('source_table', self::SOURCE_TABLE)
            ->where('source_id', $sourceId)->where('target_table', 'council_members')->where('status', 'success')->exists();
    }

    private function batch(?string $batch): string
    {
        return $batch !== null && trim($batch) !== '' ? trim($batch) : 'central-councils-'.now()->format('Ymd_His');
    }

    private function usableName(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value !== null && ! in_array(Str::lower($value), ['under construction', 'under construction...'], true) ? $value : null;
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

    /** @param array<string, int> $reasons */
    private function skip(array &$reasons, int &$skipped, string $reason): void
    {
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        $skipped++;
    }
}
