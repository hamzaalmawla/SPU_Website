<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ContinuityServiceInterface;
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
}
