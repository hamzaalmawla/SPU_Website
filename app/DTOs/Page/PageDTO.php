<?php

declare(strict_types=1);

namespace App\DTOs\Page;

/**
 * Bilingual landing-page shell payload for admin and public consumers.
 *
 * Translation DTOs carry the authoritative localized content. Page metadata carries
 * non-localized shell data, including any page-level content_json that is still in use.
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
