<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

use Illuminate\Support\Collection;

final readonly class LegacyNewsSlugCleanupPlanDTO
{
    /** @param Collection<int, LegacyNewsSlugCleanupItemDTO> $items */
    public function __construct(
        public int $maxSlugLength,
        public ?int $limit,
        public int $totalLongSlugRows,
        public int $plannedRows,
        public int $omittedRows,
        public int $collisionAdjustedRows,
        public string $status,
        public Collection $items,
    ) {}
}
