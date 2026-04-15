<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Draft snapshot payload for homepage and landing-page publishing workflows.
 */
final readonly class HomepageDraftDTO
{
    public function __construct(
        public int $id,
        public string $targetType,
        public ?int $targetId,
        public string $status,
        public DraftPayloadDTO $payload,
        public int $createdBy,
        public ?string $publishAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
