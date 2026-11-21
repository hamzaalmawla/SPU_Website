<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/**
 * A specialization as a decision prints it and the department it maps to.
 * `needsChoice` is true when the editor should confirm or pick the department.
 */
final readonly class StudentListSpecializationDTO
{
    public function __construct(
        public string $text,
        public int $count,
        public ?int $departmentId,
        public bool $needsChoice,
        public bool $remembered = false,
    ) {}
}
