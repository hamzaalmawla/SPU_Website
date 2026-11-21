<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

use Illuminate\Support\Collection;

final readonly class LegacyImportDryRunDTO
{
    /**
     * @param  Collection<int, LegacyImportTableInventoryDTO>  $sourceTables
     * @param  array<int, string>  $targetTables
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public string $module,
        public bool $enabled,
        public bool $canRun,
        public Collection $sourceTables,
        public array $targetTables,
        public int $estimatedSourceRows,
        public string $status,
        public array $warnings = [],
    ) {}
}
