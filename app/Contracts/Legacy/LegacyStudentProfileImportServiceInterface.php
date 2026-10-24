<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyStudentProfileImportResultDTO;
use App\DTOs\Legacy\LegacyStudentProfilePublicationResultDTO;

interface LegacyStudentProfileImportServiceInterface
{
    public function import(string $lane, bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false): LegacyStudentProfileImportResultDTO;

    public function publishImported(string $lane, bool $write = false, ?string $approval = null, ?string $batch = null): LegacyStudentProfilePublicationResultDTO;
}
