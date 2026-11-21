<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\DTOs\Legacy\LegacyClassificationReportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportClassificationReportCommand extends Command
{
    protected $signature = 'legacy-import:classification-report
        {module? : Optional configured classification module}
        {--limit= : Optional per-table scan/export limit}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/classification : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export Phase 4 legacy inventory classification reports and mapping sheets.';

    public function __construct(
        private readonly LegacyClassificationReportServiceInterface $classificationReportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $result = $this->classificationReportService->export(
            module: is_string($module) ? $module : null,
            limit: $limit,
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
        }

        $this->info('Legacy Classification Report');
        $this->line('Module: '.($result->module ?? 'all'));
        $this->line('Status: '.$result->status);
        $this->line('Tables: '.$result->tableCount);
        $this->line('Source rows: '.$result->sourceRowCount);
        $this->line('Classified rows: '.$result->classifiedRowCount);
        $this->line('Quarantine/unknown rows: '.$result->unknownRowCount);
        $this->line('High-risk tables covered: '.$result->highRiskTablesCovered.'/'.$result->highRiskTableCount);

        if ($result->bucketCounts !== []) {
            $this->table(['Bucket', 'Rows'], collect($result->bucketCounts)->map(
                fn (int $count, string $bucket): array => [$bucket, (string) $count]
            )->values()->all());
        }

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyClassificationReportResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'status' => $result->status,
            'disk' => $result->disk,
            'table_count' => $result->tableCount,
            'source_row_count' => $result->sourceRowCount,
            'classified_row_count' => $result->classifiedRowCount,
            'unknown_row_count' => $result->unknownRowCount,
            'high_risk_table_count' => $result->highRiskTableCount,
            'high_risk_tables_covered' => $result->highRiskTablesCovered,
            'bucket_counts' => $result->bucketCounts,
            'warnings' => $result->warnings,
            'paths' => $result->paths,
        ];
    }
}
