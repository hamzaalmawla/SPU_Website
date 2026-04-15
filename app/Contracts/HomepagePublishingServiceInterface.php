<?php

declare(strict_types=1);

namespace App\Contracts;

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
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(array $payload, int $userId): HomepageDraftDTO;

    /**
     * Publish a saved homepage draft.
     */
    public function publish(int $draftId, int $userId): bool;

    /**
     * Unpublish an already published homepage target.
     */
    public function unpublish(string $targetType, int $targetId, int $userId): bool;

    /**
     * Schedule a homepage draft for publication.
     */
    public function schedulePublish(int $draftId, DateTimeInterface $publishAt, int $userId): bool;
}
