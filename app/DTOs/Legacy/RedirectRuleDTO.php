<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * DTO for an exact legacy redirect rule.
 */
final readonly class RedirectRuleDTO
{
    public function __construct(
        public int $id,
        public string $legacyPath,
        public string $destinationUrl,
        public int $statusCode,
        public ?string $locale,
        public bool $isActive,
    ) {}
}
