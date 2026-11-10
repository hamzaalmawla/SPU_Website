<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  list<string>  $missingDirectories
 * @param  list<string>  $paths
 * @param  list<string>  $warnings
 */
final readonly class LegacyFileContinuityProbeResultDTO
{
    public function __construct(
        public string $rootFingerprint,
        public int $scannedDirectories,
        public int $fileCount,
        public int $safeFiles,
        public int $reviewFiles,
        public int $blockedFiles,
        public int $symlinkEscapes,
        public int $caseCollisions,
        public int $targetCollisions,
        public int $differingTargetCollisions,
        public array $missingDirectories,
        public array $paths,
        public array $warnings,
    ) {}
}
