<?php

declare(strict_types=1);

namespace App\Contracts\News;

/**
 * Event details of articles in the Events and Society's Events categories.
 * The details travel inside the article's CMS payload under "event" and are
 * written to the event tables only when the article is published.
 */
interface NewsArticleEventCmsServiceInterface
{
    /** Event types an Events article can have; Society's Events are always "society". */
    public const KINDS = ['event', 'conference', 'seminar'];

    public const LANGUAGES = ['ar', 'en', 'both'];

    public const AUDIENCES = ['students', 'staff', 'public', 'alumni'];

    public function isEventCategory(?int $categoryId): bool;

    /**
     * The event part of a payload in its stored shape, or null when the
     * category does not carry event details.
     *
     * @return array<string, mixed>|null
     */
    public function normalize(mixed $event, ?int $categoryId): ?array;

    /** @return array<string, mixed>|null */
    public function storedPayload(int $articleId): ?array;

    /**
     * @param  array<string, mixed>|null  $event
     * @return array<string, array<int, string>> Errors keyed under "event.".
     */
    public function validationErrors(?array $event, ?int $categoryId): array;

    /**
     * Writes the details for a published article; null removes them.
     *
     * @param  array<string, mixed>|null  $event
     */
    public function persist(int $articleId, ?array $event): bool;

    /**
     * University profiles a speaker can be picked from: leadership first,
     * then one group per faculty. Values are "person:<id>" or
     * "faculty_member:<id>".
     *
     * @return array<string, array<string, string>>
     */
    public function speakerProfileOptions(string $locale, ?string $current = null): array;
}
