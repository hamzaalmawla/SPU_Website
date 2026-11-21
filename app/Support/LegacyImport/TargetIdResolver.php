<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use App\Models\Shared\MigrationLog;

class TargetIdResolver
{
    public function resolve(string $sourceTable, int|string $sourceId, string $targetTable): ?int
    {
        return MigrationLog::query()
            ->where('source_table', $sourceTable)
            ->where('source_id', is_numeric($sourceId) ? (int) $sourceId : null)
            ->where('target_table', $targetTable)
            ->where('status', 'success')
            ->latest('id')
            ->value('target_id');
    }

    public function remember(
        string $module,
        string $batchName,
        string $sourceTable,
        int|string|null $sourceId,
        string $targetTable,
        int|string|null $targetId,
        ?string $message = null,
    ): void {
        MigrationLog::query()->create([
            'module' => $module,
            'batch_name' => $batchName,
            'source_table' => $sourceTable,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'target_table' => $targetTable,
            'target_id' => is_numeric($targetId) ? (int) $targetId : null,
            'status' => 'success',
            'message' => $message,
            'metadata' => null,
        ]);
    }
}
