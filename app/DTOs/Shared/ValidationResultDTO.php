<?php

declare(strict_types=1);

namespace App\DTOs\Shared;

/**
 * Validation result payload for editor-side checks.
 */
final readonly class ValidationResultDTO
{
    /**
     * @param  array<int, ValidationMessageDTO>  $errors
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
    ) {}
}
