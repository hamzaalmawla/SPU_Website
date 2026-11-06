<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsAttachmentDTO
{
    public function __construct(
        public int $id,
        public string $kind,
        public ?string $label,
        public ?string $url,
    ) {}
}
