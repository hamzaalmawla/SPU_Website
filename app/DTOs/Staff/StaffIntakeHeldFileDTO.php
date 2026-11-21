<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

/**
 * A photo or CV a teacher already sent on a try that came back (a missing
 * field, or "we may already have your profile"), kept so they need not pick
 * it again.
 */
final readonly class StaffIntakeHeldFileDTO
{
    public function __construct(
        public string $kind,
        public string $handle,
        public string $name,
    ) {}
}
