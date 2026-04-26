<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized footer presentation settings.
 */
final readonly class FooterSettingsDTO
{
    /**
     * @param  array<int, NavigationActionDTO>  $legalLinks
     */
    public function __construct(
        public string $locale,
        public string $copyrightText,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $brandTitle = null,
        public ?string $brandSummary = null,
        public ?string $logoUrl = null,
        public ?string $mapEmbedUrl = null,
        public array $legalLinks = [],
    ) {}
}
