<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportTableInventoryDTO;
use Illuminate\Console\Command;

final class LegacyImportDryRunCommand extends Command
{
    protected $signature = 'legacy-import:dry-run
        {module : Configured legacy import module}
        {--batch= : Optional batch name to record for this dry run}
        {--json : Output machine-readable JSON}';

    protected $description = 'Run a read-only legacy import dry run summary for one module.';

    public function __construct(
        private readonly LegacyImportInspectionServiceInterface $inspectionService,
        private readonly LegacyImportBatchServiceInterface $batchService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');
        $result = $this->inspectionService->dryRun($module);
        $batchName = $this->option('batch');
        $batchName = is_string($batchName) && $batchName !== '' ? $batchName : null;
        $batch = $this->batchService->recordDryRun($result, $batchName);
        $payload = array_merge($this->toArray($result), [
            'batch_name' => $batch->batchName,
            'batch_status' => $batch->status,
        ]);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result->status === 'unknown_module' ? self::INVALID : self::SUCCESS;
        }

        $this->info('Legacy Import Dry Run ['.$result->module.']');

        if ($result->warnings !== []) {
            foreach ($result->warnings as $warning) {
                $this->warn($warning);
            }
        }

        $this->table(
            ['Source Table', 'Exists', 'Rows', 'Error'],
            $result->sourceTables
                ->map(fn (LegacyImportTableInventoryDTO $table): array => [
                    $table->table,
                    $table->exists ? 'yes' : 'no',
                    (string) ($table->rowCount ?? ''),
                    $table->error ?? '',
                ])
                ->all(),
        );

        $this->line('Status: '.$result->status);
        $this->line('Batch: '.$batch->batchName.' ['.$batch->status.']');
        $this->line('Can run: '.($result->canRun ? 'yes' : 'no'));
        $this->line('Estimated source rows: '.$result->estimatedSourceRows);
        $this->line('Target tables: '.implode(', ', $result->targetTables));

        return $result->status === 'unknown_module' ? self::INVALID : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyImportDryRunDTO $result): array
    {
        return [
            'module' => $result->module,
            'enabled' => $result->enabled,
            'can_run' => $result->canRun,
            'status' => $result->status,
            'estimated_source_rows' => $result->estimatedSourceRows,
            'source_tables' => $result->sourceTables
                ->map(fn (LegacyImportTableInventoryDTO $table): array => [
                    'table' => $table->table,
                    'exists' => $table->exists,
                    'row_count' => $table->rowCount,
                    'error' => $table->error,
                ])
                ->all(),
            'target_tables' => $result->targetTables,
            'warnings' => $result->warnings,
        ];
    }
}
