<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use App\Models\MigrationLog;
use App\Models\MigrationRejection;

class MigrationLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $module,
        string $batchName,
        string $sourceTable,
        int|string|null $sourceId,
        string $targetTable,
        int|string|null $targetId,
        string $status,
        ?string $message = null,
        ?array $metadata = null,
    ): MigrationLog {
        return MigrationLog::query()->create([
            'module' => $module,
            'batch_name' => $batchName,
            'source_table' => $sourceTable,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'target_table' => $targetTable,
            'target_id' => is_numeric($targetId) ? (int) $targetId : null,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $rawSummary
     */
    public function reject(
        string $module,
        string $sourceTable,
        int|string|null $sourceId,
        string $reasonCode,
        string $reasonMessage,
        ?array $rawSummary = null,
    ): MigrationRejection {
        return MigrationRejection::query()->create([
            'module' => $module,
            'source_table' => $sourceTable,
            'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'raw_summary' => $rawSummary,
        ]);
    }
}
