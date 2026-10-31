<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyImportTableInventoryDTO
{
    public function __construct(
        public string $table,
        public bool $exists,
        public ?int $rowCount,
        public ?string $error = null,
    ) {}
}
