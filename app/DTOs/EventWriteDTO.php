<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for event write payloads.
 */
final readonly class EventWriteDTO
{
    public function __construct(
        public ?string $slug,
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
