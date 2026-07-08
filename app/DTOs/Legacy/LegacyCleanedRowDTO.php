<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyCleanedRowDTO
{
    /**
     * @param array<string, mixed> $values
     * @param array<int, array<string, mixed>> $decisions
     * @param array<int, string> $blockedFields
     * @param array<string, int> $issueCounts
     */
    public function __construct(
        public string $module,
        public string $sourceTable,
        public array $values,
        public array $decisions,
        public array $blockedFields,
        public array $issueCounts,
        public bool $canImportPublicly,
    ) {}
}
