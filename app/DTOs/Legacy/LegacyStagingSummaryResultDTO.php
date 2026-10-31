<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $reviewStatusCounts
 * @param  array<string, int>  $classificationCounts
 * @param  array<string, int>  $moduleCounts
 * @param  array<string, int>  $blockerCounts
 * @param  array<int, array<string, mixed>>  $groups
 * @param  array<int, array<string, mixed>>  $samples
 * @param  array<int, string>  $paths
 */
final readonly class LegacyStagingSummaryResultDTO
{
    public function __construct(
        public ?string $module,
        public ?string $reviewStatus,
        public string $disk,
        public int $totalRows,
        public int $sampleLimit,
        public array $reviewStatusCounts,
        public array $classificationCounts,
        public array $moduleCounts,
        public array $blockerCounts,
        public array $groups,
        public array $samples,
        public array $paths,
    ) {}
}
