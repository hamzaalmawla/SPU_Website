<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Structured draft payload for the page editor.
 */
final readonly class PageDraftDataDTO
{
    public function __construct(
        public PageMetadataDTO $metadata,
        public PageTranslationDTO $arabicTranslation,
        public PageTranslationDTO $englishTranslation,
        public PageSeoInputDTO $arabicSeo,
        public PageSeoInputDTO $englishSeo,
    ) {}
}
