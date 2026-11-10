<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyInternalLinkExtractionServiceInterface;
use App\DTOs\Legacy\LegacyInternalLinkExtractionResultDTO;
use Illuminate\Console\Command;

final class LegacyImportInternalLinksReportCommand extends Command
{
    protected $signature = 'legacy-import:internal-links-report
        {module : Configured internal link extraction module}
        {--record-review : Persist extracted internal links to migration_rejections for review}
        {--limit= : Optional per-table scan limit}
        {--json : Output machine-readable JSON}';

    protected $description = 'Extract legacy internal links from configured legacy fields, dry-run by default.';

    public function __construct(
        private readonly LegacyInternalLinkExtractionServiceInterface $extractionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = (string) $this->argument('module');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $result = $this->extractionService->extract(
            module: $module,
            recordReviewRows: (bool) $this->option('record-review'),
            limit: $limit,
        );
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
        }

        $this->info('Legacy Internal Links Report ['.$result->module.']');
        $this->line('Status: '.$result->status);
        $this->line('Recorded review rows: '.($result->recordedReviewRows ? 'yes' : 'no'));
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Scanned fields: '.$result->scannedFields);
        $this->line('Extracted links: '.$result->extractedLinks);
        $this->line('Unique links: '.$result->uniqueLinks);
        $this->line('Recorded rows: '.$result->recordedRows);

        if ($result->sampleLinks !== []) {
            $this->table(['Sample Legacy Link'], collect($result->sampleLinks)->map(
                fn (string $link): array => [$link]
            )->all());
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if (! $result->recordedReviewRows && $result->extractedLinks > 0) {
            $this->warn('Dry-run only. Re-run with --record-review to persist internal link review rows.');
        }

        return $result->status === 'unknown_or_unconfigured_module' ? self::INVALID : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyInternalLinkExtractionResultDTO $result): array
    {
        return [
            'module' => $result->module,
            'status' => $result->status,
            'recorded_review_rows' => $result->recordedReviewRows,
            'scanned_rows' => $result->scannedRows,
            'scanned_fields' => $result->scannedFields,
            'extracted_links' => $result->extractedLinks,
            'unique_links' => $result->uniqueLinks,
            'recorded_rows' => $result->recordedRows,
            'warnings' => $result->warnings,
            'sample_links' => $result->sampleLinks,
        ];
    }
}
