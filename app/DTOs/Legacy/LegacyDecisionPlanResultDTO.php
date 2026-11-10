<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyDecisionPlanResultDTO
{
    /** @param array<string, int> $actionCounts */
    public function __construct(
        public string $module,
        public string $disk,
        public string $path,
        public int $decisionCount,
        public int $manualReviewCount,
        public array $actionCounts,
    ) {}
}
