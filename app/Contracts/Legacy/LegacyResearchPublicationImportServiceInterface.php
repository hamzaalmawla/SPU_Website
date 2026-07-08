<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyResearchPublicationImportResultDTO;

interface LegacyResearchPublicationImportServiceInterface
{
    public function import(bool $write = false, ?string $approval = null, ?string $batch = null, bool $enable = false, ?int $limit = null): LegacyResearchPublicationImportResultDTO;
}
