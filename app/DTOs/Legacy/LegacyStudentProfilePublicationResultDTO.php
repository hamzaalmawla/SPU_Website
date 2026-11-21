<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyStudentProfilePublicationResultDTO
{
    public function __construct(
        public string $lane,
        public bool $written,
        public string $batch,
        public int $importedMappings,
        public int $visibleSourceRows,
        public int $eligibleRows,
        public int $enabledRows,
        public int $alreadyEnabledRows,
        public int $blockedRows,
    ) {}
}
