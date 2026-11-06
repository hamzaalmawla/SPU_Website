<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<string, int>  $bucketCounts
 * @param  array<int, string>  $warnings
 * @param  array<int, string>  $paths
 */
final readonly class LegacyClassificationReportResultDTO
{
    public function __construct(
        public ?string $module,
        public string $status,
        public string $disk,
        public int $tableCount,
        public int $sourceRowCount,
        public int $classifiedRowCount,
        public int $unknownRowCount,
        public int $highRiskTableCount,
        public int $highRiskTablesCovered,
        public array $bucketCounts,
        public array $warnings,
        public array $paths,
    ) {}
}
