<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

use DateTimeInterface;

final readonly class StaffIntakeLinkInputDTO
{
    /** @param list<string>|null $requiredFields */
    public function __construct(
        public ?int $facultyId,
        public ?string $label,
        public ?DateTimeInterface $expiresAt,
        public ?int $maxSubmissions,
        // A center's link (config/centers.php) instead of a faculty's.
        public ?string $centerSlug = null,
        // Fields the form makes required (StaffIntakeFields::REQUIRABLE); null keeps the defaults.
        public ?array $requiredFields = null,
    ) {}
}
