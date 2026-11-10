<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface;
use App\DTOs\Legacy\LegacyUrlContinuityInventoryResultDTO;
use Illuminate\Console\Command;

final class LegacyImportUrlContinuityInventoryCommand extends Command
{
    protected $signature = 'legacy-import:url-continuity-inventory
        {module? : Optional module filter for review/mapping rows}
        {--without-files : Exclude legacy file inventory rows}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/url-continuity : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export Phase 5 URL continuity inventory without creating redirects.';

    public function __construct(
        private readonly LegacyUrlContinuityInventoryServiceInterface $urlContinuityInventoryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $result = $this->urlContinuityInventoryService->export(
            module: is_string($module) ? $module : null,
            includeFiles: ! (bool) $this->option('without-files'),
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy URL Continuity Inventory');
        $this->line('Module: '.($result->module ?? 'all'));
        $this->line('Rows: '.$result->rowCount);
        $this->line('Resolved rows: '.$result->resolvedRows);
        $this->line('Unresolved/backlog rows: '.$result->unresolvedRows);
        $this->line('File rows: '.$result->fileRows);

        if ($result->statusCounts !== []) {
            $this->table(['Status', 'Rows'], collect($result->statusCounts)->map(
                fn (int $count, string $status): array => [$status, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyUrlContinuityInventoryResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'disk' => $result->disk,
            'row_count' => $result->rowCount,
            'resolved_rows' => $result->resolvedRows,
            'unresolved_rows' => $result->unresolvedRows,
            'file_rows' => $result->fileRows,
            'status_counts' => $result->statusCounts,
            'source_counts' => $result->sourceCounts,
            'paths' => $result->paths,
        ];
    }
}
