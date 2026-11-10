<?php

declare(strict_types=1);

namespace App\DTOs\Page;

use App\DTOs\Seo\PageSeoInputDTO;

/**
 * Structured draft payload for the page editor.
 *
 * This draft shape keeps shell-level metadata separate from localized translation content so
 * later runtime work does not collapse contentJson and translation payload precedence.
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
