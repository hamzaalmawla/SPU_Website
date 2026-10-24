<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyMappingProposalServiceInterface;
use App\DTOs\Legacy\LegacyMappingProposalImportResultDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportMappingProposalsCommand extends Command
{
    protected $signature = 'legacy-import:mapping-proposals
        {path : Classification mapping CSV path on the selected disk}
        {--write : Persist proposed mappings to legacy_content_mappings}
        {--disk=local : Storage disk}
        {--json : Output machine-readable JSON}';

    protected $description = 'Dry-run or persist Phase 4 mapping proposals from a classification mapping CSV.';

    public function __construct(
        private readonly LegacyMappingProposalServiceInterface $mappingProposalService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->mappingProposalService->importFromClassificationCsv(
                path: (string) $this->argument('path'),
                write: (bool) $this->option('write'),
                disk: (string) $this->option('disk'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Mapping Proposals');
        $this->line('Mode: '.($result->written ? 'write' : 'dry-run'));
        $this->line('Path: '.$result->path);
        $this->line('Scanned rows: '.$result->scannedRows);
        $this->line('Proposed rows: '.$result->proposedRows);
        $this->line('Created rows: '.$result->createdRows);
        $this->line('Updated rows: '.$result->updatedRows);
        $this->line('Skipped rows: '.$result->skippedRows);

        if ($result->classificationCounts !== []) {
            $this->table(['Classification', 'Rows'], collect($result->classificationCounts)->map(
                fn (int $count, string $classification): array => [$classification, (string) $count]
            )->values()->all());
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if (! $result->written) {
            $this->warn('Dry-run only. Re-run with --write to persist proposed mappings.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyMappingProposalImportResultDTO $result): array
    {
        return [
            'path' => $result->path,
            'disk' => $result->disk,
            'written' => $result->written,
            'scanned_rows' => $result->scannedRows,
            'proposed_rows' => $result->proposedRows,
            'created_rows' => $result->createdRows,
            'updated_rows' => $result->updatedRows,
            'skipped_rows' => $result->skippedRows,
            'classification_counts' => $result->classificationCounts,
            'target_type_counts' => $result->targetTypeCounts,
            'warnings' => $result->warnings,
        ];
    }
}
