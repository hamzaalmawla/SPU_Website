<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized language switch target for public navigation.
 */
final readonly class LanguageSwitchLinkDTO
{
    public function __construct(
        public string $locale,
        public string $label,
        public string $url,
        public bool $isCurrent,
    ) {}
}
