<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyQuarantineSummaryResultDTO
{
    /** @param array<int, string> $paths */
    public function __construct(
        public string $disk,
        public ?string $module,
        public int $rowCount,
        public int $summaryGroupCount,
        public int $needsDecisionGroupCount,
        public array $paths,
    ) {}
}
