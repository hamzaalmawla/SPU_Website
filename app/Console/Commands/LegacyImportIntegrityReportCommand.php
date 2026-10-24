<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use App\DTOs\Legacy\LegacyIntegrityInspectionResultDTO;
use Illuminate\Console\Command;

final class LegacyImportIntegrityReportCommand extends Command
{
    protected $signature = 'legacy-import:integrity-report
        {module : Configured integrity inspection module}
        {--record-quarantine : Persist duplicate/orphan blockers to migration_rejections}
        {--limit= : Optional per-rule scan limit}
        {--json : Output machine-readable JSON}';

    protected $description = 'Inspect configured legacy duplicate and orphan rules, dry-run by default.';

    public function __construct(
        private readonly LegacyIntegrityInspectionServiceInterface $inspectionService,
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

        $this->info('Legacy Integrity Report ['.$result->module.']');
        $this->line('Status: '.$result->status);
        $this->line('Recorded quarantine: '.($result->recordedQuarantine ? 'yes' : 'no'));
        $this->line('Scanned rules: '.$result->scannedRules);
        $this->line('Duplicate groups: '.$result->duplicateGroups);
        $this->line('Duplicate rows: '.$result->duplicateRows);
        $this->line('Orphan rows: '.$result->orphanRows);
        $this->line('Blocked rows: '.$result->blockedRows);
        $this->line('Recorded rows: '.$result->recordedRows);

        if ($result->issueCounts !== []) {
            $this->table(['Issue', 'Rows'], collect($result->issueCounts)->map(
                fn (int $count, string $issue): array => [$issue, (string) $count]
            )->values()->all());
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if (! $result->recordedQuarantine && $result->blockedRows > 0) {
            $this->warn('Dry-run only. Re-run with --record-quarantine to persist integrity review rows.');
        }

        return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyIntegrityInspectionResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'status' => $result->status,
            'recorded_quarantine' => $result->recordedQuarantine,
            'scanned_rules' => $result->scannedRules,
            'duplicate_groups' => $result->duplicateGroups,
            'duplicate_rows' => $result->duplicateRows,
            'orphan_rows' => $result->orphanRows,
            'blocked_rows' => $result->blockedRows,
            'recorded_rows' => $result->recordedRows,
            'issue_counts' => $result->issueCounts,
            'warnings' => $result->warnings,
        ];
    }
}
