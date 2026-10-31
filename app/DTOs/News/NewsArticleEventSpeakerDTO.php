<?php

declare(strict_types=1);

namespace App\DTOs\News;

/** A speaker as shown on the public event page. */
final readonly class NewsArticleEventSpeakerDTO
{
    public function __construct(
        public string $name,
        public ?string $title,
        public ?string $organization,
        public ?string $bio,
        public ?string $photoUrl,
        public ?string $profileUrl,
    ) {}
}
