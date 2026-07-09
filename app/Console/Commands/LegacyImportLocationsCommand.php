<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyLocationImportServiceInterface;
use App\DTOs\Legacy\LegacyLocationImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportLocationsCommand extends Command
{
    protected $signature = 'legacy-import:locations
        {--write : Persist imported countries/cities and migration logs}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--enable : Import records as enabled instead of disabled reference records}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import legacy countries and cities DB-first.';

    public function __construct(
        private readonly LegacyLocationImportServiceInterface $locationImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->locationImportService->import(
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
                batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
                enable: (bool) $this->option('enable'),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Location Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Enabled on import: '.($result->enabledOnImport ? 'yes' : 'no'));
        $this->line('Scanned countries: '.$result->scannedCountries);
        $this->line('Scanned cities: '.$result->scannedCities);
        $this->line('Importable countries: '.$result->importableCountries);
        $this->line('Importable cities: '.$result->importableCities);
        $this->line('Imported countries: '.$result->importedCountries);
        $this->line('Imported cities: '.$result->importedCities);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->skipReasonCounts !== []) {
            $this->table(['Skip Reason', 'Rows'], collect($result->skipReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyLocationImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'enabled_on_import' => $result->enabledOnImport,
            'scanned_countries' => $result->scannedCountries,
            'scanned_cities' => $result->scannedCities,
            'importable_countries' => $result->importableCountries,
            'importable_cities' => $result->importableCities,
            'imported_countries' => $result->importedCountries,
            'imported_cities' => $result->importedCities,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
