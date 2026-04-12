<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for event entities.
 */
final readonly class EventDTO
{
    /**
     * Create a new event data transfer object.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $titleAr,
        public string $titleEn,
        public string $descriptionAr,
        public string $descriptionEn,
        public string $startsAt,
        public ?string $endsAt,
        public string $location,
        public ?string $rsvpUrl,
        public string $category,
    ) {}
}
