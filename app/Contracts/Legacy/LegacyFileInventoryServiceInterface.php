<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFileInventoryScanResultDTO;

interface LegacyFileInventoryServiceInterface
{
    public function scan(
        bool $write,
        ?int $limit = null,
        ?callable $progress = null,
        bool $computeChecksums = false,
    ): LegacyFileInventoryScanResultDTO;
}
