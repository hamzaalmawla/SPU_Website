<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyGeneratedUrlInventoryServiceInterface;
use App\DTOs\Legacy\LegacyGeneratedUrlInventoryResultDTO;
use Illuminate\Console\Command;

final class LegacyImportGeneratedUrlInventoryCommand extends Command
{
    protected $signature = 'legacy-import:generated-url-inventory
        {table? : Optional configured legacy source table}
        {--limit= : Optional per-table row limit}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/generated-url-inventory : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Generate evidence-backed legacy URL candidates from legacy DB rows without creating redirects.';

    public function __construct(
        private readonly LegacyGeneratedUrlInventoryServiceInterface $generatedUrlInventoryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $table = $this->argument('table');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $result = $this->generatedUrlInventoryService->export(
            table: is_string($table) ? $table : null,
            limit: $limit,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Generated Legacy URL Inventory');
        $this->line('Table: '.($result->table ?? 'all'));
        $this->line('Source rows: '.$result->sourceRows);
        $this->line('Generated URL rows: '.$result->generatedRows);
        $this->line('Resolved rows: '.$result->resolvedRows);
        $this->line('Unresolved/backlog rows: '.$result->unresolvedRows);

        if ($result->sourceCounts !== []) {
            $this->table(['Source', 'Rows'], collect($result->sourceCounts)->map(
                fn (int $count, string $source): array => [$source, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyGeneratedUrlInventoryResultDTO $result): array
    {
        return [
            'table' => $result->table,
            'disk' => $result->disk,
            'source_rows' => $result->sourceRows,
            'generated_rows' => $result->generatedRows,
            'resolved_rows' => $result->resolvedRows,
            'unresolved_rows' => $result->unresolvedRows,
            'source_counts' => $result->sourceCounts,
            'status_counts' => $result->statusCounts,
            'warnings' => $result->warnings,
            'paths' => $result->paths,
        ];
    }
}
