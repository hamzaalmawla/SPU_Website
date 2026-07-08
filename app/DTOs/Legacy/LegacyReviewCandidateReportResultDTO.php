<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param array<string, int> $statusCounts
 * @param array<string, int> $blockerCounts
 * @param array<int, string> $paths
 */
final readonly class LegacyReviewCandidateReportResultDTO
{
    public function __construct(
        public ?string $module,
        public string $disk,
        public int $scannedRows,
        public int $safeCandidateRows,
        public int $decisionPlanCandidateRows,
        public int $blockedRows,
        public array $statusCounts,
        public array $blockerCounts,
        public array $paths,
    ) {}
}
