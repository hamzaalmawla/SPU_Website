<?php

declare(strict_types=1);

namespace App\DTOs\News;

/** The optional button on an announcement. */
final readonly class NewsRelatedLinkDTO
{
    public function __construct(
        public string $url,
        public string $label,
        public bool $isExternal,
    ) {}
}
