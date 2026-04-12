<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\HomepagePublishingServiceInterface;
use App\DTOs\HomepageDraftWriteDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for homepage publishing service contract.
 */
final class HomepagePublishingServicePlaceholder implements HomepagePublishingServiceInterface
{
    public function saveDraft(HomepageDraftWriteDTO $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unpublish(): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
