<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $reviewStatusCounts
 * @param  array<string, int>  $classificationCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<int, string>  $paths
 */
final readonly class LegacyStagingReviewResultDTO
{
    public function __construct(
        public ?string $module,
        public string $disk,
        public bool $written,
        public int $scannedMappings,
        public int $stagedRows,
        public int $createdRows,
        public int $updatedRows,
        public array $reviewStatusCounts,
        public array $classificationCounts,
        public array $blockerCounts,
        public array $paths,
    ) {}
}
