<?php

declare(strict_types=1);

namespace App\DTOs\Form;

use App\Enums\FormSubmissionInbox;

/**
 * A form built in the admin. Each step holds its fields; a form that is not
 * multi-step has one untitled step.
 *
 * @param  list<array{titleAr: string, titleEn: string, fields: list<array<string, mixed>>}>  $steps
 * @param  list<array<string, mixed>>  $fields  every step's fields, in order
 */
final readonly class FormDefinitionDTO
{
    public function __construct(
        public int $id,
        public string $key,
        public string $nameAr,
        public string $nameEn,
        public FormSubmissionInbox $inbox,
        public bool $isEnabled,
        public bool $multiStep,
        public string $submitLabelAr,
        public string $submitLabelEn,
        public array $steps,
        public array $fields,
    ) {}
}
