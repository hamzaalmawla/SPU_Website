<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyLocationImportResultDTO;

interface LegacyLocationImportServiceInterface
{
    public function import(bool $write, ?string $approval, ?string $batch, bool $enable): LegacyLocationImportResultDTO;
}
