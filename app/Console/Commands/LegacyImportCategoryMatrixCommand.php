<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyCategoryMatrixExporterInterface;
use App\DTOs\Legacy\LegacyCategoryMatrixExportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportCategoryMatrixCommand extends Command
{
    protected $signature = 'legacy-import:category-matrix
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/category-matrix : Export directory}
        {--json : Output machine-readable JSON}';

    protected $description = 'Export a read-only per-record audit matrix for legacy jx_categories metadata.';

    public function __construct(
        private readonly LegacyCategoryMatrixExporterInterface $exporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->exporter->export(
            disk: (string) $this->option('disk'),
            directory: (string) $this->option('dir'),
        );
        $payload = $this->payload($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy jx_categories Matrix Export');
        $this->line('Source rows: '.$result->sourceRows);
        $this->line('Output rows: '.$result->outputRows);
        $this->line('Known/unknown subsite: '.$result->knownSubsiteRows.'/'.$result->unknownSubsiteRows);
        $this->line('Hidden/link/orphan/mapped: '.$result->hiddenRows.'/'.$result->linkReviewRows.'/'.$result->orphanRows.'/'.$result->mappedRows);

        foreach ($result->paths as $path) {
            $this->line('Path: '.$path);
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function payload(LegacyCategoryMatrixExportResultDTO $result): array
    {
        return [
            'disk' => $result->disk,
            'source_rows' => $result->sourceRows,
            'output_rows' => $result->outputRows,
            'known_subsite_rows' => $result->knownSubsiteRows,
            'unknown_subsite_rows' => $result->unknownSubsiteRows,
            'hidden_rows' => $result->hiddenRows,
            'link_review_rows' => $result->linkReviewRows,
            'orphan_rows' => $result->orphanRows,
            'mapped_rows' => $result->mappedRows,
            'service_counts' => $result->serviceCounts,
            'subsite_counts' => $result->subsiteCounts,
            'paths' => $result->paths,
            'warnings' => $result->warnings,
        ];
    }
}
