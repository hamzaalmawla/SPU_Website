<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized footer presentation settings.
 */
final readonly class FooterSettingsDTO
{
    public function __construct(
        public string $locale,
        public string $copyrightText,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}
}
