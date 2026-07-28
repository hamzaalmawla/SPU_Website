<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFileInventoryServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportFileInventoryCommand extends Command
{
    protected $signature = 'legacy-import:file-inventory
        {--write : Persist discovered legacy file references}
        {--checksum : Compute SHA-256, MIME type, and size for files found under an available root}
        {--limit= : Optional per-column scan limit}';

    protected $description = 'Scan high-priority legacy file fields into the legacy file inventory, dry-run by default.';

    public function __construct(
        private readonly LegacyFileInventoryServiceInterface $fileInventoryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $write = (bool) $this->option('write');

        try {
            $result = $this->fileInventoryService->scan(
                $write,
                $limit,
                $write ? function (int $processed, int $total, int $existing, int $missing, int $written, int $updated): void {
                    $this->line("Progress: {$processed}/{$total} paths, existing {$existing}, missing {$missing}, written {$written}, updated {$updated}");
                } : null,
                (bool) $this->option('checksum'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy File Inventory '.($result->wroteChanges ? 'Write' : 'Dry Run'));
        $this->line('Scanned references: '.$result->scannedReferences);
        $this->line('Unique legacy paths: '.$result->uniqueLegacyPaths);
        $this->line('Written rows: '.$result->writtenRows);
        $this->line('Updated rows: '.$result->updatedRows);
        $this->line('Existing files: '.$result->existingFiles);
        $this->line('Missing files: '.$result->missingFiles);
        $this->line('Unverified files: '.$result->unverifiedFiles);
        $this->line('Checksum failed files: '.$result->checksumFailedFiles);
        $this->line('Unexpected error files: '.$result->unexpectedErrorFiles);
        $this->line('Broken symlink candidates: '.$result->brokenSymlinks);
        $this->line('Missing tables: '.$result->missingTables);
        $this->line('Missing columns: '.$result->missingColumns);

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($this->sampleLines('Sample missing paths', $result->sampleMissingPaths) as $line) {
            $this->warn($line);
        }

        foreach ($this->sampleLines('Checksum failed paths', $result->checksumFailedPaths) as $line) {
            $this->warn($line);
        }

        foreach ($this->sampleLines('Unexpected error paths', $result->unexpectedErrorPaths) as $line) {
            $this->warn($line);
        }

        foreach ($this->sampleLines('Broken symlink paths', $result->brokenSymlinkPaths) as $line) {
            $this->warn($line);
        }

        if (! $result->wroteChanges) {
            $this->warn('Dry-run only. Re-run with --write to persist inventory rows.');
        }

        return self::SUCCESS;
    }

    /** @param array<int, string> $paths @return array<int, string> */
    private function sampleLines(string $label, array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        return [$label.': '.implode(', ', $paths)];
    }
}
