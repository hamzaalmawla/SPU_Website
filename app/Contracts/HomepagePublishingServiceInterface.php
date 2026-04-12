<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageDraftWriteDTO;

/**
 * Defines homepage draft and publish workflow operations.
 */
interface HomepagePublishingServiceInterface
{
    /**
     * Save homepage draft content.
     */
    public function saveDraft(HomepageDraftWriteDTO $data): bool;

    /**
     * Publish the current homepage draft.
     */
    public function publish(): bool;

    /**
     * Unpublish the homepage.
     */
    public function unpublish(): bool;
}
