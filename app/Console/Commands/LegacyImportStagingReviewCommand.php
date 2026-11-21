<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\DTOs\Legacy\LegacyStagingReviewResultDTO;
use Illuminate\Console\Command;

final class LegacyImportStagingReviewCommand extends Command
{
    protected $signature = 'legacy-import:staging-review
        {module? : Optional module filter}
        {--write : Upsert review records into legacy_review_items}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/staging-review : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Build full-database legacy staging review records from proposed content mappings.';

    public function __construct(
        private readonly LegacyStagingReviewServiceInterface $stagingReviewService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $result = $this->stagingReviewService->build(
            module: is_string($module) ? $module : null,
            write: (bool) $this->option('write'),
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Staging Review');
        $this->line('Module: '.($result->module ?? 'all'));
        $this->line('Written to review table: '.($result->written ? 'yes' : 'no'));
        $this->line('Scanned mappings: '.$result->scannedMappings);
        $this->line('Staged review rows: '.$result->stagedRows);
        $this->line('Created rows: '.$result->createdRows);
        $this->line('Updated rows: '.$result->updatedRows);

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
    private function toArray(LegacyStagingReviewResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'disk' => $result->disk,
            'written' => $result->written,
            'scanned_mappings' => $result->scannedMappings,
            'staged_rows' => $result->stagedRows,
            'created_rows' => $result->createdRows,
            'updated_rows' => $result->updatedRows,
            'review_status_counts' => $result->reviewStatusCounts,
            'classification_counts' => $result->classificationCounts,
            'blocker_counts' => $result->blockerCounts,
            'paths' => $result->paths,
        ];
    }
}
