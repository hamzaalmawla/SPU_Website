<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyStagingSummaryServiceInterface;
use App\DTOs\Legacy\LegacyStagingSummaryResultDTO;
use Illuminate\Console\Command;

final class LegacyImportStagingSummaryCommand extends Command
{
    protected $signature = 'legacy-import:staging-summary
        {module? : Optional module filter}
        {--status= : Optional review_status filter}
        {--sample-limit=5 : Samples per module/status/classification}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/staging-summary : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export a grouped summary from legacy_review_items for editor-friendly staging review.';

    public function __construct(
        private readonly LegacyStagingSummaryServiceInterface $stagingSummaryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $result = $this->stagingSummaryService->export(
            module: is_string($module) ? $module : null,
            reviewStatus: is_string($this->option('status')) ? (string) $this->option('status') : null,
            sampleLimit: (int) $this->option('sample-limit'),
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Staging Summary');
        $this->line('Module: '.($result->module ?? 'all'));
        $this->line('Review status: '.($result->reviewStatus ?? 'all'));
        $this->line('Total staged rows: '.$result->totalRows);
        $this->line('Sample limit: '.$result->sampleLimit);

        if ($result->reviewStatusCounts !== []) {
            $this->table(['Review Status', 'Rows'], collect($result->reviewStatusCounts)->map(
                fn (int $count, string $status): array => [$status, (string) $count]
            )->values()->all());
        }

        if ($result->classificationCounts !== []) {
            $this->table(['Classification', 'Rows'], collect($result->classificationCounts)->map(
                fn (int $count, string $classification): array => [$classification, (string) $count]
            )->values()->all());
        }

        if ($result->blockerCounts !== []) {
            $this->table(['Blocker', 'Rows'], collect($result->blockerCounts)->map(
                fn (int $count, string $blocker): array => [$blocker, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyStagingSummaryResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'review_status' => $result->reviewStatus,
            'disk' => $result->disk,
            'total_rows' => $result->totalRows,
            'sample_limit' => $result->sampleLimit,
            'review_status_counts' => $result->reviewStatusCounts,
            'classification_counts' => $result->classificationCounts,
            'module_counts' => $result->moduleCounts,
            'blocker_counts' => $result->blockerCounts,
            'groups' => $result->groups,
            'samples' => $result->samples,
            'paths' => $result->paths,
        ];
    }
}
