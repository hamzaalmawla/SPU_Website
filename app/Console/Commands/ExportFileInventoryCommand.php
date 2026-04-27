<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ContinuityServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class ExportFileInventoryCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:export-file-inventory
        {--format=json : Output format (json or csv)}
        {--disk=local : Storage disk for file export}
        {--dir=continuity-exports : Export directory}';

    /**
     * @var string
     */
    protected $description = 'Export machine-readable report of legacy file/document continuity state';

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

        $this->info('Exporting file inventory...');

        $inventory = $this->continuityService->getFileInventory();

        $items = $inventory->map(fn ($item): array => [
            'id' => $item->id,
            'legacy_path' => $item->legacyPath,
            'current_path' => $item->currentPath ?? '',
            'media_asset_id' => $item->mediaAssetId ?? '',
            'status' => $item->status,
        ])->all();

        $statusCounts = $inventory->groupBy(fn ($item): string => $item->status)->map->count();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'total' => count($items),
            'summary' => [
                'mapped' => $statusCounts->get('mapped', 0),
                'unmapped' => $statusCounts->get('unmapped', 0),
                'missing' => $statusCounts->get('missing', 0),
            ],
            'items' => $items,
        ];

        $this->outputToConsole($payload);

        $timestamp = now()->format('Ymd_His');
        $filename = "file_inventory_{$timestamp}";

        if ($format === 'csv') {
            $this->writeCsv($disk, "{$dir}/{$filename}.csv", $items);
        } else {
            $this->writeJson($disk, "{$dir}/{$filename}.json", $payload);
        }

        $this->info("Exported {$payload['total']} file inventory entries.");
        $this->line("Disk: {$disk}");
        $this->line("File: {$dir}/{$filename}.{$format}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputToConsole(array $payload): void
    {
        $this->line("Mapped: {$payload['summary']['mapped']}");
        $this->line("Unmapped: {$payload['summary']['unmapped']}");
        $this->line("Missing: {$payload['summary']['missing']}");
        $this->newLine();

        if ($payload['total'] === 0) {
            $this->warn('No file inventory entries found.');

            return;
        }

        $this->table(
            ['ID', 'Legacy Path', 'Current Path', 'Media Asset ID', 'Status'],
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
}
