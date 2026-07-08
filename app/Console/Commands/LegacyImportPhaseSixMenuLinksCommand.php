<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixMenuLinkImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixMenuLinksCommand extends Command
{
    protected $signature = 'legacy-import:phase6-menu-links
        {--write : Persist disabled menu items and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import approved Phase 6 legacy menu links as disabled footer menu items.';

    public function __construct(
        private readonly LegacyPhaseSixMenuLinkImportServiceInterface $menuLinkImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->menuLinkImportService->import(
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Menu Link Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Created menu items: '.$result->createdMenuItems);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyPhaseSixMenuLinkImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'created_menu_items' => $result->createdMenuItems,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
