<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Single contact channel in public settings.
 */
final readonly class ContactLinkDTO
{
    public function __construct(
        public string $type,
        public string $label,
        public string $value,
    ) {}
}
