<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PeopleUnifyResultDTO;
use InvalidArgumentException;

/**
 * Gives every leadership row (persons) its one faculty profile: the existing
 * link, else the one profile with the same name, else a new profile made from
 * the row. The profile's values are never overwritten; only its empty fields
 * are filled from the row. Without $write it only reports.
 */
interface PeopleUnifyServiceInterface
{
    /** @throws InvalidArgumentException */
    public function unify(bool $write, ?string $approval): PeopleUnifyResultDTO;
}
