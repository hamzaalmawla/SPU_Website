<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Single social link in public settings.
 */
final readonly class SocialLinkDTO
{
    public function __construct(
        public string $platform,
        public string $url,
        public bool $isEnabled = true,
    ) {}
}
