<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCareerLinkImportResultDTO;

interface LegacyCareerLinkImportServiceInterface
{
    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyCareerLinkImportResultDTO;
}
