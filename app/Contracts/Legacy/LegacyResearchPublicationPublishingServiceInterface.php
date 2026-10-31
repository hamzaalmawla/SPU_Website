<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyResearchPublicationPublicationResultDTO;

interface LegacyResearchPublicationPublishingServiceInterface
{
    public function publishImported(
        int $actorUserId,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $includeDuplicateReview = false,
    ): LegacyResearchPublicationPublicationResultDTO;
}
