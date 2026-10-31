<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacySubsiteDTO
{
    public function __construct(
        public string $key,
        public ?string $pathPrefix,
        public int $siteId,
        public bool $isPublicAdminSubsite = false,
    ) {}
}
