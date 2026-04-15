<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Validation messages for a single field.
 */
final readonly class ValidationMessageDTO
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $field,
        public array $messages,
    ) {}
}
