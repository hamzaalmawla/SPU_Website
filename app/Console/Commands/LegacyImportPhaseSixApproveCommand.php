<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixApprovalResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixApproveCommand extends Command
{
    protected $signature = 'legacy-import:phase6-approve
        {lane : Phase 6 lane to approve}
        {--write : Persist approvals}
        {--approve= : Required approval token for write mode}
        {--json : Output machine-readable JSON}';

    protected $description = 'Approve clean Phase 6 current-scope candidate lanes after review.';

    public function __construct(
        private readonly LegacyPhaseSixApprovalServiceInterface $approvalService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lane = (string) $this->argument('lane');

        $approval = is_string($this->option('approve')) ? (string) $this->option('approve') : null;
        $write = (bool) $this->option('write');
        $result = match ($lane) {
            'menu_links' => $this->approvalService->approveMenuLinks(write: $write, approval: $approval),
            'pages' => $this->approvalService->approvePages(write: $write, approval: $approval),
            default => null,
        };

        if (! $result instanceof LegacyPhaseSixApprovalResultDTO) {
            $this->error('Supported Phase 6 approval lanes: menu_links, pages.');

            return self::INVALID;
        }
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Approval');
        $this->line('Lane: '.$result->lane);
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Approvable rows: '.$result->approvableRows);
        $this->line('Approved rows: '.$result->approvedRows);
        $this->line('Blocked rows: '.$result->blockedRows);

        if ($result->blockerCounts !== []) {
            $this->table(['Blocker', 'Rows'], collect($result->blockerCounts)->map(
                fn (int $count, string $blocker): array => [$blocker, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyPhaseSixApprovalResultDTO $result): array
    {
        return [
            'lane' => $result->lane,
            'written' => $result->written,
            'scanned_rows' => $result->scannedRows,
            'approvable_rows' => $result->approvableRows,
            'approved_rows' => $result->approvedRows,
            'blocked_rows' => $result->blockedRows,
            'blocker_counts' => $result->blockerCounts,
        ];
    }
}
