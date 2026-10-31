<?php

declare(strict_types=1);

namespace App\DTOs\Form;

final readonly class DynamicFormSubmissionDetailFieldDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public mixed $rawValue,
        public string $displayValue,
        public bool $isLegacyField = false,
        public bool $isLegacyValue = false,
    ) {}
}
