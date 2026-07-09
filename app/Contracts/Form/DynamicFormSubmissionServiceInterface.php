<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\DTOs\Form\DynamicFormSubmissionDataDTO;

interface DynamicFormSubmissionServiceInterface
{
    /** @return array<int, string> */
    public function allowedFormIds(): array;

    /** @return array<string, array<int, string>> */
    public function validationRules(string $formId): array;

    public function submit(DynamicFormSubmissionDataDTO $data): bool;
}
