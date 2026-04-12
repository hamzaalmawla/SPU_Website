<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for SEO metadata attached to an entity.
 */
final readonly class SeoMetadataDTO
{
    public function __construct(
        public string $entityType,
        public int|string $entityId,
        public string $locale,
        public string $metaTitle,
        public string $metaDescription,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?string $ogImage,
        public ?string $twitterCard,
        public ?string $robots,
        public ?string $schema,
    ) {}
}
