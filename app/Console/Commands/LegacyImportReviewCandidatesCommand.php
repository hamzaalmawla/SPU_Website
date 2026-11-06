<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface;
use App\DTOs\Legacy\LegacyReviewCandidateReportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportReviewCandidatesCommand extends Command
{
    protected $signature = 'legacy-import:review-candidates
        {module? : Optional module filter}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/review-candidates : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export Phase 4/11 review candidates from proposed legacy content mappings.';

    public function __construct(
        private readonly LegacyReviewCandidateReportServiceInterface $reviewCandidateReportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $result = $this->reviewCandidateReportService->export(
            module: is_string($module) ? $module : null,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Review Candidate Report');
        $this->line('Module: '.($result->module ?? 'all'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Safe candidates: '.$result->safeCandidateRows);
        $this->line('Decision-plan candidates: '.$result->decisionPlanCandidateRows);
        $this->line('Blocked rows: '.$result->blockedRows);

        if ($result->statusCounts !== []) {
            $this->table(['Status', 'Rows'], collect($result->statusCounts)->map(
                fn (int $count, string $status): array => [$status, (string) $count]
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
    private function toArray(LegacyReviewCandidateReportResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'disk' => $result->disk,
            'scanned_rows' => $result->scannedRows,
            'safe_candidate_rows' => $result->safeCandidateRows,
            'decision_plan_candidate_rows' => $result->decisionPlanCandidateRows,
            'blocked_rows' => $result->blockedRows,
            'status_counts' => $result->statusCounts,
            'blocker_counts' => $result->blockerCounts,
            'paths' => $result->paths,
        ];
    }
}
