<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Typed key-value entry for grouped settings operations.
 */
final readonly class SettingValueDTO
{
    /**
     * @param  array<int, string>  $listValues
     * @param  array<string, string>  $mapValues
     */
    public function __construct(
        public string $key,
        public string $valueType,
        public ?string $stringValue = null,
        public ?bool $boolValue = null,
        public ?int $intValue = null,
        public array $listValues = [],
        public array $mapValues = [],
    ) {}
}
