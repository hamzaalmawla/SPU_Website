<?php

declare(strict_types=1);

namespace App\DTOs\Page;

/**
 * Draft snapshot payload for landing-page editor workflows.
 */
final readonly class PageDraftDTO
{
    public function __construct(
        public int $id,
        public int $pageId,
        public string $status,
        public DraftPayloadDTO $payload,
        public int $createdBy,
        public ?string $publishAt,
        public string $createdAt,
        public string $updatedAt,
        public int $version = 1,
    ) {}
}
