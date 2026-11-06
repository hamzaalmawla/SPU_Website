<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * Who a leadership row is, read from the faculty profile it points at. The
 * profile's values win; a field the profile leaves empty falls back to the
 * leadership row's old copy until people:unify has filled the profile.
 */
final readonly class PersonIdentityDTO
{
    /** @param list<string> $specializations */
    public function __construct(
        public int $personId,
        public int $memberId,
        public string $slug,
        public string $name,
        public ?string $title,
        public ?string $position,
        public ?string $bio,
        public ?string $image,
        public ?string $email,
        public ?string $phone,
        public array $specializations,
        public string $profileUrl,
    ) {}
}
