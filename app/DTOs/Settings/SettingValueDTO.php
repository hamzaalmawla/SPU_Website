<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

/**
 * Key-value entry for grouped settings operations.
 *
 * The settings table stores either value_json or value_text per key, so this DTO keeps that
 * storage shape explicit instead of forcing artificial primitive/list/map splits.
 */
final readonly class SettingValueDTO
{
    /**
     * @param  array<string, mixed>|array<int, mixed>|null  $jsonValue
     */
    public function __construct(
        public string $key,
        public string $type,
        public ?array $jsonValue = null,
        public ?string $textValue = null,
        public bool $isPublic = false,
    ) {}
}
