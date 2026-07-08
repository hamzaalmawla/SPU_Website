<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFacultyProfileImportResultDTO;

interface LegacyFacultyProfileImportServiceInterface
{
    public function import(bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false): LegacyFacultyProfileImportResultDTO;
}
