<?php

declare(strict_types=1);

namespace App\DTOs\Media;

/**
 * Outcome of one run registering imported person photos in the legacy media library.
 */
final readonly class LegacyPersonPhotoLibraryReportDTO
{
    /**
     * @param  array<string, int>  $referencesBySource  photo references read per source table
     * @param  array<int, string>  $missingFiles  legacy values whose file is not under the legacy root
     */
    public function __construct(
        public bool $written,
        public array $referencesBySource,
        public int $photoCount,
        public int $createdCount,
        public int $updatedCount,
        public int $unchangedCount,
        public int $inMainLibraryCount,
        public int $notImageCount,
        public array $missingFiles,
    ) {}
}
