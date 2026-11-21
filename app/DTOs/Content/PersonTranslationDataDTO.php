<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PersonTranslationDataDTO
{
    public function __construct(
        public string $locale,
        public string $name,
        public string $role,
        public ?string $bio,
        public ?string $quote,
    ) {}
}
