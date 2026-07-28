<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFileContinuityProbeServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class LegacyImportFileContinuityProbeCommand extends Command
{
    protected $signature = 'legacy-import:file-continuity-probe
        {root : Absolute path to the legacy public file root}
        {--no-checksum : Skip SHA-256 calculation for a faster preliminary probe}
        {--target-root= : Optional absolute Laravel public root for deployment collision checks}
        {--disk=local : Private Laravel storage disk for evidence}
        {--dir=legacy-import-exports/file-continuity-probes : Private export directory}';

    protected $description = 'Read approved legacy static trees and export credential-safe cPanel continuity evidence.';

    public function __construct(
        private readonly LegacyFileContinuityProbeServiceInterface $probeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->probeService->probe(
                root: (string) $this->argument('root'),
                computeChecksums: ! (bool) $this->option('no-checksum'),
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
                targetRoot: is_string($this->option('target-root')) && trim((string) $this->option('target-root')) !== ''
                    ? (string) $this->option('target-root')
                    : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy File Continuity Probe (read-only)');
        $this->line('Root fingerprint: '.$result->rootFingerprint);
        $this->line('Scanned directories: '.$result->scannedDirectories);
        $this->line('Missing expected directories: '.count($result->missingDirectories));
        $this->line('Files: '.$result->fileCount);
        $this->line('Safe static files: '.$result->safeFiles);
        $this->line('Manual review files: '.$result->reviewFiles);
        $this->line('Blocked executable/sensitive files: '.$result->blockedFiles);
        $this->line('Symlink escapes: '.$result->symlinkEscapes);
        $this->line('Case collision groups: '.$result->caseCollisions);
        $this->line('Target path collisions: '.$result->targetCollisions);
        $this->line('Differing target collisions: '.$result->differingTargetCollisions);

        foreach ($result->missingDirectories as $directory) {
            $this->warn('Missing: '.$directory);
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($result->paths as $path) {
            $this->line('Evidence: '.$path);
        }

        return self::SUCCESS;
    }
}
