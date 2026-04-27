<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Result of a redirect resolution from the continuity layer.
 */
final readonly class RedirectResultDTO
{
    public function __construct(
        public int $statusCode,
        public string $destinationUrl,
        public string $matchType,
    ) {}
}
