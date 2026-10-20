<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/** A link just made or rotated: the only moment its address is known. */
final readonly class StaffIntakeLinkCreatedDTO
{
    public function __construct(
        public StaffIntakeLinkDTO $link,
        public string $urlAr,
        public string $urlEn,
    ) {}
}
