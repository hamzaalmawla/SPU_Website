<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\DTOs\Legacy\LegacyCleaningInspectionResultDTO;
use Illuminate\Console\Command;

final class LegacyImportCleaningReportCommand extends Command
{
    protected $signature = 'legacy-import:cleaning-report
        {module : Configured cleaning inspection module}
        {--record-quarantine : Persist blocked field decisions to migration_rejections}
        {--limit= : Optional per-table scan limit}
        {--json : Output machine-readable JSON}';

    protected $description = 'Inspect configured legacy fields with Phase 3 cleaning decisions, dry-run by default.';

    public function __construct(
        private readonly LegacyCleaningInspectionServiceInterface $inspectionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $result = $this->inspectionService->inspect(
            module: $module,
            recordQuarantine: (bool) $this->option('record-quarantine'),
            limit: $limit,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
        }

        $this->info('Legacy Cleaning Report ['.$result->module.']');
        $this->line('Status: '.$result->status);
        $this->line('Recorded quarantine: '.($result->recordedQuarantine ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Scanned fields: '.$result->scannedFields);
        $this->line('Publicly importable fields: '.$result->publiclyImportableFields);
        $this->line('Blocked fields: '.$result->blockedFields);
        $this->line('Recorded rows: '.$result->recordedRows);

        if ($result->decisionCounts !== []) {
            $this->table(['Decision', 'Fields'], collect($result->decisionCounts)->map(
                fn (int $count, string $decision): array => [$decision, (string) $count]
            )->values()->all());
        }

        if ($result->issueCounts !== []) {
            $this->table(['Issue', 'Fields'], collect($result->issueCounts)->map(
                fn (int $count, string $issue): array => [$issue, (string) $count]
            )->values()->all());
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if (! $result->recordedQuarantine && $result->blockedFields > 0) {
            $this->warn('Dry-run only. Re-run with --record-quarantine to persist review rows.');
        }

        return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyCleaningInspectionResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'status' => $result->status,
            'recorded_quarantine' => $result->recordedQuarantine,
            'scanned_rows' => $result->scannedRows,
            'scanned_fields' => $result->scannedFields,
            'publicly_importable_fields' => $result->publiclyImportableFields,
            'blocked_fields' => $result->blockedFields,
            'recorded_rows' => $result->recordedRows,
            'decision_counts' => $result->decisionCounts,
            'issue_counts' => $result->issueCounts,
            'warnings' => $result->warnings,
        ];
    }
}
