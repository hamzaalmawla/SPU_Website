<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * Compact event card payload for homepage sections.
 */
final readonly class EventCardDTO
{
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $slug,
        public ?string $summary,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $location,
        public ?string $url,
        public ?string $imageUrl = null,
        public ?string $timeLabel = null,
    ) {}
}
