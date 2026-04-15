<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Grouped settings payload with optional locale context.
 */
final readonly class SettingsDTO
{
    /**
     * @param  array<int, SettingValueDTO>  $values
     */
    public function __construct(
        public array $values,
    ) {}
}
