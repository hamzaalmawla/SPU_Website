<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageDraftDTO;
use DateTimeInterface;

/**
 * Defines homepage draft and publish workflow operations.
 */
interface HomepagePublishingServiceInterface
{
    /**
     * Save homepage draft content.
     *
     * @param  int|null  $expectedVersion  When provided, the service checks the current draft version
     *                                     and throws ConflictException on mismatch (optimistic locking).
     */
    public function saveDraft(HomepageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): HomepageDraftDTO;

    /**
     * Publish a saved homepage draft.
     */
    public function publish(int $draftId, int $userId): bool;

    /**
     * Unpublish an already published homepage target.
     */
    public function unpublish(string $targetType, ?int $targetId, int $userId): bool;

    /**
     * Schedule a homepage draft for publication.
     */
    public function schedulePublish(int $draftId, DateTimeInterface $publishAt, int $userId): bool;

    /**
     * Publish all due scheduled homepage drafts.
     */
    public function publishDueScheduled(): int;

    /**
     * Check whether an editable (draft or scheduled) homepage draft exists.
     */
    public function hasEditableDraft(): bool;

    /**
     * Discard all editable (draft or scheduled) homepage drafts.
     *
     * @return int Number of drafts deleted.
     */
    public function discardEditableDraft(int $userId): int;

    /**
     * Return the status string of the latest homepage draft, or null if none exists.
     */
    public function latestHomepageState(): ?string;

    /**
     * Return the latest editable homepage draft version for optimistic locking.
     */
    public function latestEditableDraftVersion(): ?int;
}
