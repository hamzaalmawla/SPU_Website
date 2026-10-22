<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\NewsArticleEventDTO;
use App\DTOs\News\NewsEventDTO;
use App\DTOs\News\NewsRelatedLinkDTO;
use Illuminate\Support\Collection;

/**
 * Public side of article events: the event part of a published article,
 * its registration state and seats, and its calendar file.
 */
interface NewsArticleEventServiceInterface
{
    /** Registrations made on the site carry this source in their context. */
    public const REGISTRATION_SOURCE = 'news-articles';

    /** What an announcement's related link can point at. */
    public const RELATED_KINDS = ['article', 'conference', 'url'];

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

    /**
     * Public articles in the Events category, one entry each, in the shape
     * the events list, news index and homepage already render.
     *
     * @return Collection<int, NewsEventDTO>
     */
    public function listingEvents(string $locale): Collection;

    /**
     * Events and Society's Events marked "Add to calendar", one entry per session.
     *
     * @return Collection<int, NewsEventDTO>
     */
    public function calendarEntries(string $locale): Collection;

    /**
     * The button of an announcement, or null when there is no link or its
     * target is not public. Without an editor label the text follows the
     * target: "Register" while registration is open, otherwise its details.
     */
    public function relatedLink(?string $kind, ?int $articleId, ?string $conferenceId, ?string $url, ?string $label, string $locale): ?NewsRelatedLinkDTO;

    /**
     * Choices for the announcement editor: public Events and Society's
     * Events articles ("article"), or published research conferences
     * ("conference"). A current value that is no longer public stays listed.
     *
     * @return array<int|string, string>
     */
    public function relatedLinkOptions(string $kind, string $locale, int|string|null $current = null): array;

    /**
     * Events page list IDs that were moved into articles, with the article ID.
     *
     * @return array<string, int>
     */
    public function movedEventArticleIds(): array;
}
