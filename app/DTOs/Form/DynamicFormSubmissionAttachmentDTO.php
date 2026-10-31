<?php

declare(strict_types=1);

namespace App\DTOs\Form;

final readonly class DynamicFormSubmissionAttachmentDTO
{
    public function __construct(
        public string $field,
        public string $label,
        public string $originalName,
        public ?string $mimeType,
        public ?int $size,
    ) {}
}
