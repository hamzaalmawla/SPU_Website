<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * A directorate's manager, typed on the directorate: managers are not
 * teaching staff, so they have no faculty profile.
 */
final readonly class DirectorateManagerDTO
{
    /** @param list<string> $education One qualification per entry. */
    public function __construct(
        public string $name,
        public array $education,
        public ?string $email,
        public ?string $phone,
    ) {}
}
