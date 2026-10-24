<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyQuarantineExportResultDTO
{
    /**
     * @param  array<string, int>  $moduleCounts
     * @param  array<string, int>  $reasonCounts
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $format,
        public ?string $module,
        public ?string $reasonCode,
        public int $rowCount,
        public array $moduleCounts,
        public array $reasonCounts,
    ) {}
}
