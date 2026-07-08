<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyStudentProfileImportResultDTO;

interface LegacyStudentProfileImportServiceInterface
{
    public function import(string $lane, bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false): LegacyStudentProfileImportResultDTO;
}
