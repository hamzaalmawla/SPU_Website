<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportTableInventoryDTO;
use Illuminate\Console\Command;

final class LegacyImportInventoryCommand extends Command
{
    protected $signature = 'legacy-import:inventory {module?} {--json : Output machine-readable JSON}';

    protected $description = 'Inspect configured legacy import modules and source table availability without importing data.';

    public function __construct(
        private readonly LegacyImportInspectionServiceInterface $inspectionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && $module !== '' ? $module : null;
        $results = $this->inspectionService->inventory($module);

        if ($results->isEmpty()) {
            $this->error('No configured legacy import module found'.($module !== null ? ': '.$module : '.'));

            return self::INVALID;
        }

        $rows = $results->map(fn (LegacyImportDryRunDTO $result): array => $this->toArray($result))->all();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Import Inventory');
        $this->table(
            ['Module', 'Enabled', 'Status', 'Sources', 'Estimated Rows', 'Targets'],
            array_map(static fn (array $row): array => [
                $row['module'],
                $row['enabled'] ? 'yes' : 'no',
                $row['status'],
                collect($row['source_tables'])->map(static fn (array $source): string => $source['table'].':'.($source['row_count'] ?? ($source['exists'] ? '0' : 'missing')))->implode(', '),
                $row['estimated_source_rows'],
                implode(', ', $row['target_tables']),
            ], $rows),
        );

        return self::SUCCESS;
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
