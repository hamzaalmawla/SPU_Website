<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCleanedRowDTO;

interface LegacyCleanedRowServiceInterface
{
    /**
     * @param array<string, string> $approvedActionsByField
     */
    public function cleanRow(string $module, string $sourceTable, object|array $row, array $approvedActionsByField = []): LegacyCleanedRowDTO;
}
