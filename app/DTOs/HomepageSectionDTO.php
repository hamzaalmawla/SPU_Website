<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for a fixed homepage section.
 */
final readonly class HomepageSectionDTO
{
    public function __construct(
        public int $id,
        public string $key,
        public int $sortOrder,
        public bool $isEnabled,
        public HomepageSectionDataDTO $payload,
        public HomepageSectionTranslationDTO $arabicTranslation,
        public HomepageSectionTranslationDTO $englishTranslation,
        public ?HomepageSectionDataDTO $arabicPayload = null,
        public ?HomepageSectionDataDTO $englishPayload = null,
    ) {}
}
