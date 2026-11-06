<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

final readonly class CmsDraftDTO
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $id,
        public string $targetKey,
        public string $status,
        public array $payload,
        public int $createdBy,
        public ?string $publishAt,
        public string $createdAt,
        public string $updatedAt,
        public int $version,
    ) {}
}
