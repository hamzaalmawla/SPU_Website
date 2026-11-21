<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsCategoryDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $type,
        public string $name,
        public ?string $description,
    ) {}
}
