<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

/**
 * Localized social and contact settings payload.
 */
final readonly class SocialContactSettingsDTO
{
    /**
     * @param  array<int, SocialLinkDTO>  $socialLinks
     * @param  array<int, ContactLinkDTO>  $contactLinks
     */
    public function __construct(
        public string $locale,
        public array $socialLinks,
        public array $contactLinks,
    ) {}
}
