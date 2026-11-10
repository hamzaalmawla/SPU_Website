<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearchPublicationCmsDataDTO
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public ?int $publicationId,
        public array $payload,
        public ?string $targetKey = null,
    ) {}
}
