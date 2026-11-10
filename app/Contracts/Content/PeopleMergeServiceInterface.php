<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PeopleMergeResultDTO;
use InvalidArgumentException;

/**
 * Merges researchers and faculty members entered twice. Without $write it
 * only reports.
 */
interface PeopleMergeServiceInterface
{
    /** @throws InvalidArgumentException */
    public function merge(bool $write, ?string $approval): PeopleMergeResultDTO;
}
