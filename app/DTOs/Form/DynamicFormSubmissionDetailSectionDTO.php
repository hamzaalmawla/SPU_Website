<?php

declare(strict_types=1);

namespace App\DTOs\Form;

/**
 * @param  list<DynamicFormSubmissionDetailFieldDTO>  $fields
 */
final readonly class DynamicFormSubmissionDetailSectionDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public array $fields,
        public bool $isTechnical = false,
    ) {}
}
