<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCareerLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\DTOs\Legacy\LegacyCareerLinkImportResultDTO;
use App\Models\Career\CareerLink;
use App\Models\Career\CareerLinkTranslation;
use App\Models\Shared\MigrationLog;
use App\Services\Legacy\Concerns\HandlesPrivateReviewPackets;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyCareerLinkImportService implements LegacyCareerLinkImportServiceInterface
{
    use HandlesPrivateReviewPackets;

    private const APPROVAL_TOKEN = 'legacy-career-links-import';

    private const SOURCE_TABLE = 'jx_job_sites';

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['source_table', 'source_id', 'approval_decision', 'approved_target'];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyContentCleaningServiceInterface $cleaner,
    ) {}

    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyCareerLinkImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing career links requires --approve='.self::APPROVAL_TOKEN.'.');
        }
        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'legacy-career-links-'.now()->format('Ymd_His');
        if ($input === null || trim($input) === '') {
            if ($write) {
                throw new InvalidArgumentException('Importing career links requires an approved private candidate CSV input.');
            }

            return new LegacyCareerLinkImportResultDTO(false, $batch, 0, 0, 0, 0, 0, []);
        }

        $input = trim($input);
        [$scanned, $approved, $skipped, $reasons] = $this->approvedRows($input, $disk);
        $checksum = hash('sha256', Storage::disk($disk)->get($input));
        $sourceRows = $approved === [] ? collect() : $this->oldDatabase->table(self::SOURCE_TABLE)->whereIn('id', $approved)
            ->get(['id', 'ar_name', 'en_name', 'url', 'photo', 'is_visible', 'record_order', 'ar_data', 'en_data'])->keyBy('id');
        $prepared = [];
        $approvedUrlCounts = [];
        foreach ($approved as $sourceId) {
            $row = $sourceRows->get($sourceId);
            if (! is_object($row)) {
                $this->skip($reasons, $skipped, 'missing_source');

                continue;
            }
            $url = $this->safeUrl($row->url);
            if ($url === null) {
                $this->skip($reasons, $skipped, 'invalid_or_unsafe_url');

                continue;
            }
            if ((int) $row->is_visible !== 1) {
                $this->skip($reasons, $skipped, 'hidden_source');

                continue;
            }
            $arTitle = $this->plainText($this->cleaner, $row->ar_name);
            $enTitle = $this->plainText($this->cleaner, $row->en_name);
            if ($arTitle === null && $enTitle === null) {
                $this->skip($reasons, $skipped, 'missing_both_titles');

                continue;
            }
            $key = mb_strtolower($url);
            $approvedUrlCounts[$key] = ($approvedUrlCounts[$key] ?? 0) + 1;
            $prepared[] = compact('sourceId', 'row', 'url', 'key', 'arTitle', 'enTitle');
        }
        $current = CareerLink::withTrashed()->pluck('url')->mapWithKeys(fn (mixed $url): array => [mb_strtolower(trim((string) $url)) => true])->all();
        $eligible = [];
        foreach ($prepared as $item) {
            if (($approvedUrlCounts[$item['key']] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_approved_url');
            } elseif (isset($current[$item['key']])) {
                $this->skip($reasons, $skipped, 'current_url_conflict');
            } elseif ($this->alreadyMapped($item['sourceId'])) {
                $this->skip($reasons, $skipped, 'already_mapped');
            } else {
                $eligible[] = $item;
            }
        }

        $imported = 0;
        $translations = 0;
        if ($write) {
            foreach ($eligible as $item) {
                $translations += $this->writeLink($item, $batch, $disk, $input, $checksum);
                $imported++;
            }
        }
        ksort($reasons);

        return new LegacyCareerLinkImportResultDTO($write, $batch, $scanned, count($eligible), $imported, $translations, $skipped, $reasons);
    }

    /** @return array{0: int, 1: list<int>, 2: int, 3: array<string, int>} */
    private function approvedRows(string $input, string $disk): array
    {
        if (! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('Career link candidate CSV ['.$input.'] does not exist on disk ['.$disk.'].');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, Storage::disk($disk)->get($input));
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Career link candidate CSV is empty.');
        }
        $headers = array_map(static fn (mixed $value): string => trim((string) $value), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            throw new InvalidArgumentException('Input is not a valid career link candidate CSV.'.($missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ' Duplicate headers are not allowed.'));
        }
        $rows = [];
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
            $rows[] = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
        }
        fclose($stream);
        $counts = [];
        foreach ($rows as $row) {
            if (ctype_digit($row['source_id']) && (int) $row['source_id'] > 0) {
                $counts[(int) $row['source_id']] = ($counts[(int) $row['source_id']] ?? 0) + 1;
            }
        }
        $approved = [];
        foreach ($rows as $row) {
            $id = ctype_digit($row['source_id']) ? (int) $row['source_id'] : 0;
            if ($id > 0 && ($counts[$id] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_source_id');
            } elseif (Str::lower($row['approval_decision']) !== 'import') {
                $this->skip($reasons, $skipped, $row['approval_decision'] === '' ? 'blank_approval_decision' : 'approval_decision_not_import');
            } elseif (Str::lower($row['approved_target']) !== 'career_links') {
                $this->skip($reasons, $skipped, $row['approved_target'] === '' ? 'blank_approved_target' : 'approved_target_not_career_links');
            } elseif ($row['source_table'] !== self::SOURCE_TABLE) {
                $this->skip($reasons, $skipped, 'source_table_mismatch');
            } elseif ($id < 1) {
                $this->skip($reasons, $skipped, 'invalid_source_id');
            } else {
                $approved[] = $id;
            }
        }

        return [$scanned, $approved, $skipped, $reasons];
    }

    /** @param array{sourceId: int, row: object, url: string, key: string, arTitle: ?string, enTitle: ?string} $item */
    private function writeLink(array $item, string $batch, string $disk, string $input, string $checksum): int
    {
        return DB::transaction(function () use ($item, $batch, $disk, $input, $checksum): int {
            $row = $item['row'];
            $link = CareerLink::query()->create([
                'url' => $item['url'],
                'legacy_photo_path' => is_scalar($row->photo) && trim((string) $row->photo) !== '' ? trim((string) $row->photo) : null,
                'is_external' => true,
                'sort_order' => is_numeric($row->record_order) ? max(0, (int) $row->record_order) : $item['sourceId'], 'is_enabled' => false,
            ]);
            $count = 0;
            foreach (['ar' => $item['arTitle'], 'en' => $item['enTitle']] as $locale => $title) {
                if ($title === null) {
                    continue;
                }
                CareerLinkTranslation::query()->create([
                    'career_link_id' => $link->getKey(), 'locale' => $locale, 'title' => $title,
                    'description' => $this->plainText($this->cleaner, $locale === 'ar' ? $row->ar_data : $row->en_data),
                ]);
                $count++;
            }
            MigrationLog::query()->create([
                'module' => 'legacy_career_links', 'batch_name' => $batch, 'source_table' => self::SOURCE_TABLE,
                'source_id' => $item['sourceId'], 'target_table' => 'career_links', 'target_id' => $link->getKey(), 'status' => 'success',
                'message' => 'Imported approved external career link as disabled archival review data.',
                'metadata' => [
                    'approval_packet' => ['disk' => $disk, 'path' => $input, 'sha256' => $checksum],
                    'legacy_photo_path' => is_scalar($row->photo) && trim((string) $row->photo) !== '' ? trim((string) $row->photo) : null,
                    'legacy_visibility' => (int) $row->is_visible, 'media_imported' => false,
                    'locales' => array_keys(array_filter(['ar' => $item['arTitle'], 'en' => $item['enTitle']], static fn (?string $title): bool => $title !== null)),
                    'enabled_on_import' => false,
                ],
            ]);

            return $count;
        });
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $url = trim((string) $value);
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    private function alreadyMapped(int $sourceId): bool
    {
        return MigrationLog::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $sourceId)->where('status', 'success')->exists();
    }

    /** @param array<string, int> $reasons */
    private function skip(array &$reasons, int &$skipped, string $reason): void
    {
        $skipped++;
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    }
}
