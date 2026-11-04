<?php

declare(strict_types=1);

namespace App\DTOs\About;

/**
 * One card of the university's teaching staff directory. `positions` are the
 * positions held (dean, head of department, ...), `role` the same joined, and
 * `academicRank` the rank (أستاذ، أستاذ مساعد، مدرس), shown apart.
 */
final readonly class StaffDirectoryItemDTO
{
    /** @param list<string> $positions */
    public function __construct(
        public string $sourceType,
        public string $slug,
        public string $name,
        public string $role,
        public ?string $image,
        public ?string $facultySlug,
        public ?string $facultyName,
        public array $positions = [],
        public ?string $academicRank = null,
    ) {}
}
