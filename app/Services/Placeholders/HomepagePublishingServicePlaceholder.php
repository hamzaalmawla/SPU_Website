<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\HomepagePublishingServiceInterface;
use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageDraftDTO;
use BadMethodCallException;
use DateTimeInterface;

/**
 * Placeholder implementation for the homepage publishing service contract.
 */
final class HomepagePublishingServicePlaceholder implements HomepagePublishingServiceInterface
{
    public function saveDraft(HomepageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): HomepageDraftDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(int $draftId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unpublish(string $targetType, ?int $targetId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function schedulePublish(int $draftId, DateTimeInterface $publishAt, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
