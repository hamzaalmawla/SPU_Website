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
        public ?array $normalized = null,
        public ?string $handler = null,
        public ?string $outcome = null,
        public ?string $subsite = null,
        public ?int $oldSiteId = null,
        public ?int $oldLanguageId = null,
        public ?string $oldLanguageSymbol = null,
        // How many times this URL has been requested and not resolved.
        //
        // The write path never sets it - logUnresolved() increments the stored
        // counter itself - so it stays null there and carries a value only when
        // a record is read back out. It is what separates a URL something still
        // links to from one that was requested once by a crawler.
        public ?int $hitCount = null,
    ) {}
}
