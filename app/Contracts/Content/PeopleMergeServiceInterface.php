<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PeopleMergeResultDTO;

/**
 * Merges researchers and faculty members entered twice, and links faculty
 * members to their leadership profile. Without $write it only reports.
 */
interface PeopleMergeServiceInterface
{
    public function merge(bool $write, ?string $approval): PeopleMergeResultDTO;
}
