<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/** A link with its address: just made, rotated, or shown again to its manager. */
final readonly class StaffIntakeLinkCreatedDTO
{
    public function __construct(
        public StaffIntakeLinkDTO $link,
        public string $urlAr,
        public string $urlEn,
    ) {}
}
