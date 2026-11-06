<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class StaffIntakeApprovalResultDTO
{
    /** @param list<string> $publishErrors Why an approval by someone who may publish stayed a draft. */
    public function __construct(
        public int $facultyMemberId,
        public bool $published,
        public array $publishErrors = [],
    ) {}
}
