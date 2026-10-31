<?php

declare(strict_types=1);

namespace App\DTOs\ErrorPage;

/**
 * Single "way back into the site" link offered on an error page.
 */
final readonly class ErrorPageLinkDTO
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $isPrimary = false,
    ) {}
}
