<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyNewsSlugCleanupItemDTO
{
    public function __construct(
        public int $articleId,
        public ?int $legacySourceId,
        public ?int $legacyServiceType,
        public string $oldSlug,
        public string $proposedSlug,
        public int $oldSlugLength,
        public int $proposedSlugLength,
        public bool $collisionAdjusted,
        public bool $redirectRequired,
        public string $redirectFromAr,
        public string $redirectToAr,
        public string $redirectFromEn,
        public string $redirectToEn,
    ) {}
}
