<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/** A staff profile offered while a teacher types their name: public fields only. */
final readonly class StaffMatchSuggestionDTO
{
    public function __construct(
        public string $ref,
        public string $nameAr,
        public string $nameEn,
        public ?string $title,
        public ?string $department,
        public ?string $faculty,
        public bool $inLinkFaculty,
        public ?string $photoUrl,
        public int $rank,
    ) {}
}
