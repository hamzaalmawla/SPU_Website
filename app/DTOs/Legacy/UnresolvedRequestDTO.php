<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * Structured payload for logging an unresolved legacy request.
 */
final readonly class UnresolvedRequestDTO
{
    public function __construct(
        public string $url,
        public ?string $queryString,
        public string $method,
        public ?string $referrer,
        public ?string $resolvedLocale,
        public string $requestType,
        public string $timestamp,
    ) {}
}
