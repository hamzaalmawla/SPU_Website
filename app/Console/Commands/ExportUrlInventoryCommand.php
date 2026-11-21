<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\Models\Legacy\LegacyRecordSnapshot;
use App\Models\Shared\MigrationLog;
use App\Models\Shared\MigrationRejection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class ExportUrlInventoryCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:export-url-inventory
        {--format=json : Output format (json or csv)}
        {--disk=local : Storage disk for file export}
        {--dir=continuity-exports : Export directory}';

    /**
     * @var string
     */
    protected $description = 'Export machine-readable inventory of legacy public URL candidates';

    public function __construct(
        private readonly ContinuityServiceInterface $continuityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $disk = (string) $this->option('disk');
        $dir = rtrim((string) $this->option('dir'), '/');

        $this->info('Exporting URL inventory...');

        $exactRedirects = $this->continuityService->getExactRedirects();
        $patternRules = $this->continuityService->getPatternRules();

        $items = [];

        foreach ($exactRedirects as $rule) {
            $items[] = [
                'source_type' => 'exact_redirect',
                'legacy_path' => $rule->legacyPath,
                'expected_destination' => $rule->destinationUrl,
                'locale' => $rule->locale ?? '',
                'status' => $rule->isActive ? 'active' : 'inactive',
            ];
        }

        foreach ($patternRules as $rule) {
            $items[] = [
                'source_type' => 'pattern_rule',
                'legacy_path' => $rule->pattern,
                'expected_destination' => $rule->replacement,
                'locale' => '',
                'status' => $rule->isActive ? 'active' : 'inactive',
            ];
        }

        foreach ($this->legacyCandidateRows() as $candidate) {
            $items[] = $candidate;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'total' => count($items),
            'items' => $items,
        ];

        $this->outputToConsole($payload);

        $timestamp = now()->format('Ymd_His');
        $filename = "url_inventory_{$timestamp}";

        if ($format === 'csv') {
            $this->writeCsv($disk, "{$dir}/{$filename}.csv", $items);
        } else {
            $this->writeJson($disk, "{$dir}/{$filename}.json", $payload);
        }

        $this->info("Exported {$payload['total']} URL inventory entries.");
        $this->line("Disk: {$disk}");
        $this->line("File: {$dir}/{$filename}.{$format}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputToConsole(array $payload): void
    {
        if ($payload['total'] === 0) {
            $this->warn('No URL inventory entries found.');

            return;
        }

        $this->table(
            ['Source Type', 'Legacy Path', 'Expected Destination', 'Locale', 'Status'],
            $payload['items'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $disk, string $path, array $payload): void
    {
        Storage::disk($disk)->put(
            $path,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeCsv(string $disk, string $path, array $rows): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            $this->error('Unable to create CSV stream.');

            return;
        }

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (mixed $value): string => is_array($value) || is_object($value)
                        ? (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) ($value ?? ''),
                    $row,
                ));
            }
        }

        rewind($handle);
        Storage::disk($disk)->put($path, (string) stream_get_contents($handle));
        fclose($handle);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function legacyCandidateRows(): array
    {
        $items = [];

        foreach (LegacyRecordSnapshot::query()->orderBy('id')->get() as $snapshot) {
            $candidatePath = $snapshot->legacy_key
                ?? (is_array($snapshot->payload_json) ? ($snapshot->payload_json['legacy_path'] ?? null) : null)
                ?? $snapshot->payload_text;

            if (! is_string($candidatePath) || trim($candidatePath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'snapshot:'.$snapshot->module,
                'legacy_path' => $candidatePath,
                'expected_destination' => '',
                'locale' => $snapshot->locale ?? '',
                'status' => $snapshot->classification,
            ];
        }

        foreach (MigrationLog::query()->whereNotNull('metadata')->orderBy('id')->get() as $log) {
            $legacyPath = is_array($log->metadata) ? ($log->metadata['legacy_path'] ?? null) : null;

            if (! is_string($legacyPath) || trim($legacyPath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'migration_log:'.$log->module,
                'legacy_path' => $legacyPath,
                'expected_destination' => is_string($log->metadata['destination_url'] ?? null) ? $log->metadata['destination_url'] : '',
                'locale' => is_string($log->metadata['locale'] ?? null) ? $log->metadata['locale'] : '',
                'status' => $log->status,
            ];
        }

        foreach (MigrationRejection::query()->whereNotNull('raw_summary')->orderBy('id')->get() as $rejection) {
            $legacyPath = is_array($rejection->raw_summary) ? ($rejection->raw_summary['legacy_path'] ?? null) : null;

            if (! is_string($legacyPath) || trim($legacyPath) === '') {
                continue;
            }

            $items[] = [
                'source_type' => 'rejection:'.$rejection->module,
                'legacy_path' => $legacyPath,
                'expected_destination' => '',
                'locale' => is_string($rejection->raw_summary['locale'] ?? null) ? $rejection->raw_summary['locale'] : '',
                'status' => $rejection->reason_code,
            ];
        }

        return $items;
    }
}
