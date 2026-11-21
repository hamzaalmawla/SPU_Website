<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyGeneratedUrlInventoryResultDTO;

interface LegacyGeneratedUrlInventoryServiceInterface
{
    public function export(
        ?string $table = null,
        ?int $limit = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/generated-url-inventory',
    ): LegacyGeneratedUrlInventoryResultDTO;
}
