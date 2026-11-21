<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsArticleEventSessionDTO
{
    /**
     * @param  string  $date  Y-m-d in the admin time zone
     * @param  string  $startsAt  ISO 8601 start
     * @param  string|null  $endsAt  ISO 8601 end, when the editor gave one
     * @param  array<int, NewsArticleEventSpeakerDTO>  $speakers
     */
    public function __construct(
        public string $date,
        public string $dateLabel,
        public string $timeLabel,
        public string $startsAt,
        public ?string $endsAt,
        public ?string $title,
        public ?string $location,
        public array $speakers,
    ) {}
}
