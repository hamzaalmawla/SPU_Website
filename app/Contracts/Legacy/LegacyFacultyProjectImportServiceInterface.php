<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFacultyProjectImportResultDTO;

interface LegacyFacultyProjectImportServiceInterface
{
    public function import(
        string $dumpPath,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $enableVisible = false,
        bool $verifyMedia = false,
    ): LegacyFacultyProjectImportResultDTO;
}
