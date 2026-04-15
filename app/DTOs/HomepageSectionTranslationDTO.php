<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Localized text payload for a homepage section.
 */
final readonly class HomepageSectionTranslationDTO
{
    public function __construct(
        public string $locale,
        public ?string $headline = null,
        public ?string $body = null,
        public ?string $ctaLabel = null,
        public ?string $imageAlt = null,
    ) {}
}
