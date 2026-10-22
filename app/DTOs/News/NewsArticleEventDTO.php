<?php

declare(strict_types=1);

namespace App\DTOs\News;

/**
 * Event details of an article in the Events or Society's Events category.
 * registrationState is "none" (no registration), "open", "full" or "closed".
 */
final readonly class NewsArticleEventDTO
{
    /**
     * @param  array<int, string>  $audience
     * @param  array<int, NewsArticleEventSessionDTO>  $sessions
     * @param  array<int, NewsArticleEventSpeakerDTO>  $speakers
     */
    public function __construct(
        public string $id,
        public int $articleId,
        public string $locale,
        public string $title,
        public string $kind,
        public bool $requiresRegistration,
        public bool $showOnCalendar,
        public bool $isPast,
        public string $registrationState,
        public ?string $formId,
        public ?int $capacity,
        public ?int $seatsLeft,
        public ?string $registrationClosesAt,
        public ?string $registrationUrl,
        public ?string $calendarUrl,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $language,
        public bool $hasCertificate,
        public ?string $certificateNote,
        public array $audience,
        public ?string $audienceNote,
        public ?string $organizer,
        public ?string $location,
        public ?string $mapUrl,
        public ?string $onlineUrl,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $invitationImageUrl,
        public ?string $results,
        public array $sessions,
        public array $speakers,
    ) {}
}
