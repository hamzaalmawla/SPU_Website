<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsArticleCmsDataDTO
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public ?int $articleId,
        public array $payload,
        public ?string $targetKey = null,
    ) {}
}
