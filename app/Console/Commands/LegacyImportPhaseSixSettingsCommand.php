<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixSettingsImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixSettingsCommand extends Command
{
    protected $signature = 'legacy-import:phase6-settings
        {--input= : Safe mappings CSV from legacy-import:phase6-settings-mapping}
        {--write : Persist current settings and migration logs}
        {--approve= : Required approval token for write mode}
        {--disk=local : Storage disk}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Dry-run or import reviewed Phase 6 legacy settings safe mappings.';

    public function __construct(
        private readonly LegacyPhaseSixSettingsImportServiceInterface $settingsImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->settingsImportService->import(
            inputPath: is_string($this->option('input')) ? (string) $this->option('input') : null,
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            disk: (string) $this->option('disk'),
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Settings Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Input: '.$result->inputPath);
        $this->line('Batch: '.$result->batch);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Duplicate collapsed rows: '.$result->duplicateCollapsedRows);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyPhaseSixSettingsImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'disk' => $result->disk,
            'input_path' => $result->inputPath,
            'batch' => $result->batch,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'duplicate_collapsed_rows' => $result->duplicateCollapsedRows,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
