<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyQueryResolutionDTO
{
    public function __construct(
        public string $module,
        public string $sourceTable,
        public int $sourceId,
        public string $targetUrl,
        public int $statusCode,
        public string $confidence,
        public string $notes,
    ) {}
}
