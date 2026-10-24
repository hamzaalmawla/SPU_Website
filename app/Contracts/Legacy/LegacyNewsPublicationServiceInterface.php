<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyNewsPublicationResultDTO;

interface LegacyNewsPublicationServiceInterface
{
    /**
     * @param  list<int>  $sourceIds
     * @param  list<int>  $featuredSourceIds
     */
    public function publish(
        array $sourceIds,
        array $featuredSourceIds,
        int $actorUserId,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $allowDeferredMedia = false,
    ): LegacyNewsPublicationResultDTO;
}
