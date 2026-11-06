<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, string>  $warnings
 * @param  array<int, string>  $sampleMissingPaths
 * @param  array<int, string>  $checksumFailedPaths
 * @param  array<int, string>  $unexpectedErrorPaths
 * @param  array<int, string>  $brokenSymlinkPaths
 */
final readonly class LegacyFileInventoryScanResultDTO
{
    public function __construct(
        public bool $wroteChanges,
        public int $scannedReferences,
        public int $uniqueLegacyPaths,
        public int $writtenRows,
        public int $updatedRows,
        public int $missingTables,
        public int $missingColumns,
        public int $existingFiles,
        public int $missingFiles,
        public int $unverifiedFiles,
        public array $warnings,
        public array $sampleMissingPaths = [],
        public int $checksumFailedFiles = 0,
        public array $checksumFailedPaths = [],
        public int $unexpectedErrorFiles = 0,
        public array $unexpectedErrorPaths = [],
        public int $brokenSymlinks = 0,
        public array $brokenSymlinkPaths = [],
    ) {}
}
