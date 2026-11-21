<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFacultyProfileImportServiceInterface;
use App\DTOs\Legacy\LegacyFacultyProfileImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportFacultyProfilesCommand extends Command
{
    protected $signature = 'legacy-import:faculty-profiles
        {--write : Persist imported rows and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--enable : Import records as enabled instead of disabled review records}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import legacy faculty staff profiles DB-first, with photos/CVs deferred.';

    public function __construct(
        private readonly LegacyFacultyProfileImportServiceInterface $facultyProfileImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->facultyProfileImportService->import(
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
            enable: (bool) $this->option('enable'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Faculty Profile Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Enabled on import: '.($result->enabledOnImport ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyFacultyProfileImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'enabled_on_import' => $result->enabledOnImport,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
