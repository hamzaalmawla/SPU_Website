<?php

declare(strict_types=1);

namespace App\DTOs\Page;

/**
 * Localized content payload for a landing page.
 *
 * These fields are the authoritative localized runtime source when both translation content
 * and page-level contentJson exist.
 */
final readonly class PageTranslationDTO
{
    /**
     * @param  array<string, mixed>|null  $heroPayload
     * @param  array<int, array<string, mixed>>|null  $overviewCardsPayload
     * @param  array<int, array<string, mixed>>|null  $statsPayload
     * @param  array<string, mixed>|null  $bodyPayload
     * @param  array<string, mixed>|null  $ctaPayload
     * @param  array<string, mixed>|null  $sidebarPayload
     */
    public function __construct(
        public string $title,
        public ?string $navigationLabel = null,
        public ?string $headline = null,
        public ?string $subheadline = null,
        public ?array $heroPayload = null,
        public ?array $overviewCardsPayload = null,
        public ?array $statsPayload = null,
        public ?array $bodyPayload = null,
        public ?array $ctaPayload = null,
        public ?array $sidebarPayload = null,
        public ?string $excerpt = null,
        public ?string $body = null,
        public ?string $rawExcerpt = null,
        public ?string $metaTitleFallback = null,
    ) {}
}
