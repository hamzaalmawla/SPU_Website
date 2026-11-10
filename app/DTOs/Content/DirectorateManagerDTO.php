<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * A directorate's manager. Most managers are not teaching staff, so their
 * details are typed on the directorate; a manager who has a staff profile is
 * linked to it instead, and then $profileUrl opens that profile and $rank is
 * their academic rank.
 */
final readonly class DirectorateManagerDTO
{
    /** @param list<string> $education One qualification per entry. */
    public function __construct(
        public string $name,
        public array $education,
        public ?string $email,
        public ?string $phone,
        public ?string $photo = null,
        public ?string $rank = null,
        public ?string $profileUrl = null,
    ) {}
}
