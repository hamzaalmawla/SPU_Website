<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Structured editor payload for one homepage section.
 * Shared shell concerns such as footer, social, and contact settings are
 * handled outside the homepage section set.
 */
final readonly class HomepageSectionDataDTO
{
    /**
     * @param  array<int, HomepageStatItemDTO>  $stats
     * @param  array<int, HomepageFeatureItemDTO>  $featuredItems
     * @param  array<int, ArticleCardDTO>  $articles
     * @param  array<int, ResearchCardDTO>  $researchItems
     * @param  array<int, EventCardDTO>  $events
     */
    public function __construct(
        public ?string $eyebrow = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $body = null,
        public ?string $imageUrl = null,
        public ?string $backgroundImageUrl = null,
        public ?NavigationActionDTO $primaryAction = null,
        public ?NavigationActionDTO $secondaryAction = null,
        public array $stats = [],
        public array $featuredItems = [],
        public array $articles = [],
        public array $researchItems = [],
        public array $events = [],
    ) {}
}
