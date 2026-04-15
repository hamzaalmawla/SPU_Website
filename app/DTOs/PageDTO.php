<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Bilingual landing-page shell payload for admin and public consumers.
 */
final readonly class PageDTO
{
    public function __construct(
        public int $id,
        public PageMetadataDTO $metadata,
        public ?string $publishedAt,
        public PageTranslationDTO $arabicTranslation,
        public PageTranslationDTO $englishTranslation,
        public PageSeoDTO $arabicSeo,
        public PageSeoDTO $englishSeo,
    ) {}
}
