<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyImportModuleRunnerDTO
{
    public function __construct(
        public string $module,
        public string $label,
        public bool $approvedForRealRun,
        public string $approvalStatus,
        public string $description,
    ) {}
}
