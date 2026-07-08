<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\DTOs\Legacy\LegacyStudentProfileImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportStudentProfilesCommand extends Command
{
    protected $signature = 'legacy-import:student-profiles
        {lane : alumni or honor_students}
        {--write : Persist imported rows and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--enable : Import records as enabled instead of disabled review records}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import legacy alumni or honor students DB-first, with photos deferred.';

    public function __construct(
        private readonly LegacyStudentProfileImportServiceInterface $studentProfileImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lane = $this->argument('lane');
        $result = $this->studentProfileImportService->import(
            lane: is_string($lane) ? $lane : '',
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

        $this->info('Legacy Student Profile Import');
        $this->line('Lane: '.$result->lane);
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Enabled on import: '.($result->enabledOnImport ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Importable rows: '.$result->importableRows);
        $this->line('Imported rows: '.$result->importedRows);
        $this->line('Skipped rows: '.$result->skippedRows);
        $this->line('Duplicate skipped rows: '.$result->duplicateSkippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyStudentProfileImportResultDTO $result): array
    {
        return [
            'lane' => $result->lane,
            'written' => $result->written,
            'batch' => $result->batch,
            'enabled_on_import' => $result->enabledOnImport,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'skipped_rows' => $result->skippedRows,
            'duplicate_skipped_rows' => $result->duplicateSkippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
