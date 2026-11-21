<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $facultyCounts
 * @param  array<string, int>  $skipReasonCounts
 */
final readonly class LegacyFacultyProjectImportResultDTO
{
    public function __construct(
        public bool $written,
        public string $batch,
        public bool $enabledVisibleProjects,
        public int $scannedProjects,
        public int $importableProjects,
        public int $importedProjects,
        public int $visibleProjects,
        public int $hiddenProjects,
        public int $verifiedMedia,
        public int $failedMedia,
        public array $facultyCounts,
        public array $skipReasonCounts,
    ) {}
}
