<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixPageImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportPhaseSixPagesCommand extends Command
{
    protected $signature = 'legacy-import:phase6-pages
        {--write : Persist draft pages, translations, and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import approved Phase 6 legacy static pages as disabled draft pages.';

    public function __construct(
        private readonly LegacyPhaseSixPageImportServiceInterface $pageImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->pageImportService->import(
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Phase 6 Page Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Created pages: '.$result->createdPages);
        $this->line('Created translations: '.$result->createdTranslations);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyPhaseSixPageImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'created_pages' => $result->createdPages,
            'created_translations' => $result->createdTranslations,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
