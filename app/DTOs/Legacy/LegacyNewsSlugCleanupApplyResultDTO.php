<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyNewsSlugCleanupApplyResultDTO
{
    public function __construct(
        public string $status,
        public int $plannedRows,
        public int $updatedArticles,
        public int $createdRedirects,
        public int $updatedRedirects,
        public int $skippedRows,
    ) {}
}
