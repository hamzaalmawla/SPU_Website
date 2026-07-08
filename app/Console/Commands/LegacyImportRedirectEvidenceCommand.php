<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface;
use App\DTOs\Legacy\LegacyRedirectEvidenceResultDTO;
use Illuminate\Console\Command;

final class LegacyImportRedirectEvidenceCommand extends Command
{
    protected $signature = 'legacy-import:redirect-evidence
        {generated_inventory : Generated URL inventory CSV path}
        {triage_rows : URL continuity triage rows CSV path}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/redirect-evidence : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Build Phase 5 redirect evidence and preview exports from generated inventory and URL triage rows.';

    public function __construct(
        private readonly LegacyRedirectEvidenceServiceInterface $redirectEvidenceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->redirectEvidenceService->export(
            generatedInventoryPath: (string) $this->argument('generated_inventory'),
            triageRowsPath: (string) $this->argument('triage_rows'),
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Redirect Evidence');
        $this->line('Scanned evidence rows: '.$result->scannedRows);
        $this->line('Redirect preview rows: '.$result->redirectPreviewRows);
        $this->line('Blocked/backlog rows: '.$result->blockedRows);

        if ($result->evidenceStatusCounts !== []) {
            $this->table(['Evidence Status', 'Rows'], collect($result->evidenceStatusCounts)->map(
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
    private function toArray(LegacyRedirectEvidenceResultDTO $result): array
    {
        return [
            'generated_inventory_path' => $result->generatedInventoryPath,
            'triage_rows_path' => $result->triageRowsPath,
            'disk' => $result->disk,
            'scanned_rows' => $result->scannedRows,
            'redirect_preview_rows' => $result->redirectPreviewRows,
            'blocked_rows' => $result->blockedRows,
            'evidence_status_counts' => $result->evidenceStatusCounts,
            'approval_status_counts' => $result->approvalStatusCounts,
            'handler_counts' => $result->handlerCounts,
            'blocker_counts' => $result->blockerCounts,
            'paths' => $result->paths,
        ];
    }
}
