<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Grouped settings payload with explicit group and locale context.
 */
final readonly class SettingsDTO
{
    /**
     * @param  array<int, SettingValueDTO>  $values
     */
    public function __construct(
        public string $group,
        public ?string $locale,
        public array $values,
    ) {}
}
