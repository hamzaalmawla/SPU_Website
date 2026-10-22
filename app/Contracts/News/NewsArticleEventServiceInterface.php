<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\NewsArticleEventDTO;

/**
 * Public side of article events: the event part of a published article,
 * its registration state and seats, and its calendar file.
 */
interface NewsArticleEventServiceInterface
{
    /** Registrations made on the site carry this source in their context. */
    public const REGISTRATION_SOURCE = 'news-articles';

    /** The published event of an article, or null when it has none. */
    public function forArticle(int $articleId, string $locale): ?NewsArticleEventDTO;

    /**
     * An event built from an unpublished payload, for the protected preview.
     * It never offers registration.
     *
     * @param  array<string, mixed>|null  $event
     */
    public function fromPayload(?array $event, int $articleId, string $title, string $locale): ?NewsArticleEventDTO;

    /** The event of a public article by its ID or slug, whatever its registration state. */
    public function findPublicEvent(string $article, string $locale): ?NewsArticleEventDTO;

    /** Registrations sent through the site for the article's event. */
    public function registrationCount(int $articleId): int;

    /**
     * An iCalendar file with one entry per session, or null when the event
     * is over, has no sessions, or the article is not public.
     */
    public function calendarFile(string $article, string $locale): ?string;
}
