<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

final readonly class AboutEntityCmsDataDTO
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $entityType,
        public ?int $entityId,
        public array $payload,
        public ?string $targetKey = null,
    ) {}
}
