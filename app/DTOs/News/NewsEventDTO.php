<?php

declare(strict_types=1);

namespace App\DTOs\News;

final readonly class NewsEventDTO
{
    /**
     * @param  array<int, string>  $highlights
     * @param  array<int, array{name: string, title: string}>  $speakers
     * @param  array<int, string>  $gallery
     */
    public function __construct(
        public string $id,
        public string $locale,
        public string $title,
        public string $summary,
        public string $startsAt,
        public ?string $endsAt,
        public string $dateLabel,
        public string $timeLabel,
        public string $location,
        public string $categoryId,
        public string $categoryLabel,
        public string $imageUrl,
        public bool $isPast,
        public bool $isFeatured,
        public ?string $formId,
        public ?int $capacity,
        public int $registered,
        public ?int $remainingCapacity,
        public bool $isRegisterable,
        public ?string $registrationUrl,
        public string $detailUrl,
        public ?string $participants,
        public array $highlights,
        public array $speakers,
        public ?string $results,
        public array $gallery,
    ) {}
}
