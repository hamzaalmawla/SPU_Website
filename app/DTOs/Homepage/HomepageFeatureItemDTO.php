<?php

declare(strict_types=1);

namespace App\DTOs\Homepage;

/**
 * Generic featured item for homepage sections such as faculties, highlights, or services.
 */
final readonly class HomepageFeatureItemDTO
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $title,
        public ?string $summary = null,
        public ?string $imageUrl = null,
        public ?string $url = null,
        public array $tags = [],
    ) {}
}
