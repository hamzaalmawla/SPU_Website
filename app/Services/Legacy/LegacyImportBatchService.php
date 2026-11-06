<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\DTOs\Legacy\LegacyImportBatchDTO;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportTableInventoryDTO;
use App\Models\Legacy\LegacyImportBatch;
use Illuminate\Support\Str;

final class LegacyImportBatchService implements LegacyImportBatchServiceInterface
{
    public function recordDryRun(LegacyImportDryRunDTO $dryRun, ?string $batchName = null): LegacyImportBatchDTO
    {
        $status = $dryRun->canRun ? 'dry_run_ready' : 'dry_run_blocked';
        $batch = LegacyImportBatch::query()->create([
            'batch_name' => $this->batchName($dryRun->module, 'dry-run', $batchName),
            'module' => $dryRun->module,
            'mode' => 'dry_run',
            'status' => $status,
            'estimated_source_rows' => $dryRun->estimatedSourceRows,
            'summary_json' => $this->dryRunSummary($dryRun),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $this->toDto($batch);
    }

    public function recordBlockedRun(string $module, string $reason, ?string $batchName = null, array $summary = []): LegacyImportBatchDTO
    {
        $batch = LegacyImportBatch::query()->create([
            'batch_name' => $this->batchName($module, 'run-blocked', $batchName),
            'module' => $module,
            'mode' => 'run',
            'status' => 'blocked',
            'estimated_source_rows' => 0,
            'summary_json' => array_merge(['reason' => $reason], $summary),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $this->toDto($batch);
    }

    private function batchName(string $module, string $mode, ?string $requested): string
    {
        $base = $requested !== null && trim($requested) !== ''
            ? Str::slug($requested)
            : $module.'-'.$mode.'-'.now()->format('YmdHis');

        $candidate = $base !== '' ? $base : $module.'-'.$mode.'-'.Str::lower(Str::random(8));
        $counter = 2;

        while (LegacyImportBatch::query()->where('batch_name', $candidate)->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /** @return array<string, mixed> */
    private function dryRunSummary(LegacyImportDryRunDTO $dryRun): array
    {
        return [
            'can_run' => $dryRun->canRun,
            'status' => $dryRun->status,
            'source_tables' => $dryRun->sourceTables
                ->map(fn (LegacyImportTableInventoryDTO $table): array => [
                    'table' => $table->table,
                    'exists' => $table->exists,
                    'row_count' => $table->rowCount,
                    'error' => $table->error,
                ])
                ->all(),
            'target_tables' => $dryRun->targetTables,
            'warnings' => $dryRun->warnings,
        ];
    }

    private function toDto(LegacyImportBatch $batch): LegacyImportBatchDTO
    {
        return new LegacyImportBatchDTO(
            id: (int) $batch->getKey(),
            batchName: (string) $batch->batch_name,
            module: (string) $batch->module,
            mode: (string) $batch->mode,
            status: (string) $batch->status,
            estimatedSourceRows: (int) $batch->estimated_source_rows,
            summary: is_array($batch->summary_json) ? $batch->summary_json : null,
            startedAt: $batch->started_at?->toIso8601String(),
            finishedAt: $batch->finished_at?->toIso8601String(),
        );
    }
}
