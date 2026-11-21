<?php

declare(strict_types=1);

namespace App\DTOs\EServices;

final readonly class EServicesDetailPageDTO
{
    /**
     * @param  array<int, array{id: string, title: string, body: string}>  $sections
     * @param  array<int, array{id: string, title: string, url: string}>  $resourceLinks
     * @param  array<int, array{id: string, title: string, url: string}>  $relatedLinks
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $slug,
        public string $heroEyebrow,
        public string $heroTitle,
        public string $heroSummary,
        public string $heroImage,
        public string $introTitle,
        public string $introBody,
        public array $sections,
        public string $resourceLinksTitle,
        public array $resourceLinks,
        public string $ctaTitle,
        public string $ctaBody,
        public string $ctaLabel,
        public string $ctaUrl,
        public array $relatedLinks,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
