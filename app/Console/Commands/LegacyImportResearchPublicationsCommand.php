<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\DTOs\Legacy\LegacyResearchPublicationImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportResearchPublicationsCommand extends Command
{
    protected $signature = 'legacy-import:research-publications
        {--write : Persist imported rows and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--limit= : Optional number of importable rows to process}
        {--enable : Import records as enabled instead of disabled review records}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import visible legacy research publications DB-first, with file attachments deferred.';

    public function __construct(
        private readonly LegacyResearchPublicationImportServiceInterface $researchPublicationImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->researchPublicationImportService->import(
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
            enable: (bool) $this->option('enable'),
            limit: is_numeric($this->option('limit')) ? max(1, (int) $this->option('limit')) : null,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Research Publication Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Enabled on import: '.($result->enabledOnImport ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Published candidate rows: '.$result->publishedCandidateRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Skipped rows: '.$result->skippedRows);
        $this->line('Attachment reference rows: '.$result->attachmentReferenceRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyResearchPublicationImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'enabled_on_import' => $result->enabledOnImport,
            'scanned_rows' => $result->scannedRows,
            'published_candidate_rows' => $result->publishedCandidateRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'skipped_rows' => $result->skippedRows,
            'attachment_reference_rows' => $result->attachmentReferenceRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
