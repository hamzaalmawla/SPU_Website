<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/**
 * @param  array<int, string>  $issueCodes
 * @param  array<int, string>  $messages
 */
final readonly class LegacyCleaningDecisionDTO
{
    public function __construct(
        public string $field,
        public string $decision,
        public ?string $originalValue,
        public ?string $cleanedValue,
        public bool $canImportPublicly,
        public array $issueCodes,
        public array $messages,
    ) {}
}
