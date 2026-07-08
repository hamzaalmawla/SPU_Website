<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixCandidateServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixCandidateResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixCandidatesCommand extends Command
{
    protected $signature = 'legacy-import:phase6-candidates
        {lane? : Optional Phase 6 lane filter}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/phase6-candidates : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export Phase 6 current-scope import candidates from staged legacy review items.';

    public function __construct(
        private readonly LegacyPhaseSixCandidateServiceInterface $phaseSixCandidateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lane = $this->argument('lane');
        $result = $this->phaseSixCandidateService->export(
            lane: is_string($lane) ? $lane : null,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Current-Scope Candidates');
        $this->line('Lane: '.($result->lane ?? 'all'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Approval candidates: '.$result->approvalCandidateRows);
        $this->line('Import-ready rows: '.$result->importReadyRows);
        $this->line('Blocked rows: '.$result->blockedRows);

        if ($result->laneCounts !== []) {
            $this->table(['Lane', 'Rows'], collect($result->laneCounts)->map(
                fn (int $count, string $lane): array => [$lane, (string) $count]
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
    private function toArray(LegacyPhaseSixCandidateResultDTO $result): array
    {
        return [
            'lane' => $result->lane,
            'disk' => $result->disk,
            'scanned_rows' => $result->scannedRows,
            'approval_candidate_rows' => $result->approvalCandidateRows,
            'import_ready_rows' => $result->importReadyRows,
            'blocked_rows' => $result->blockedRows,
            'lane_counts' => $result->laneCounts,
            'candidate_status_counts' => $result->candidateStatusCounts,
            'blocker_counts' => $result->blockerCounts,
            'paths' => $result->paths,
        ];
    }
}
