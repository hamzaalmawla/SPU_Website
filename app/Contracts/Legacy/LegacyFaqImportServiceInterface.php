<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFaqImportResultDTO;

interface LegacyFaqImportServiceInterface
{
    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyFaqImportResultDTO;
}
