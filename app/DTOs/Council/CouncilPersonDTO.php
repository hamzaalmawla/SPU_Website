<?php

declare(strict_types=1);

namespace App\DTOs\Council;

/**
 * One person on a council page. A teaching staff member is read live from
 * their profile (rank, photo, email, profile URL); anyone else shows as the
 * editor typed them, with no profile link.
 */
final readonly class CouncilPersonDTO
{
    public function __construct(
        public string $name,
        public string $rank,
        public string $position,
        public ?string $image,
        public string $email,
        public ?string $url,
        public bool $isHead,
    ) {}
}
