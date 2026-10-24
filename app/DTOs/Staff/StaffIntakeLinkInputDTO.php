<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use DateTimeInterface;

final readonly class StaffIntakeLinkInputDTO
{
    public function __construct(
        public int $facultyId,
        public ?string $label,
        public ?DateTimeInterface $expiresAt,
        public ?int $maxSubmissions,
    ) {}
}
