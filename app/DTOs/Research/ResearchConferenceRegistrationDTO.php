<?php

declare(strict_types=1);

namespace App\DTOs\Research;

final readonly class ResearchConferenceRegistrationDTO
{
    public function __construct(
        public string $id,
        public string $locale,
        public string $title,
        public string $formId,
    ) {}
}
