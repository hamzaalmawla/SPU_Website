<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized apply CTA configuration.
 */
final readonly class ApplyCtaSettingsDTO
{
    public function __construct(
        public string $locale,
        public string $label,
        public string $url,
        public ?string $target = null,
        public bool $isEnabled = true,
    ) {}
}
