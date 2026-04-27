<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO for a legacy file inventory entry.
 */
final readonly class FileInventoryItemDTO
{
    public function __construct(
        public int $id,
        public string $legacyPath,
        public ?string $currentPath,
        public ?int $mediaAssetId,
        public string $status,
    ) {}
}
