<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\PeopleDuplicateReportDTO;

/**
 * Finds records that look like the same person: within researchers, faculty
 * members and people, and across them. Read-only.
 */
interface PeopleDuplicateReportServiceInterface
{
    public function report(): PeopleDuplicateReportDTO;
}
