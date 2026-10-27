<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/** One student read from a decision, and what importing it will do. */
final readonly class StudentListImportRowDTO
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_SKIP = 'skip';

    /**
     * @param  list<string>  $issues  translation keys under student_lists.issues
     */
    public function __construct(
        public int $line,
        public ?string $studentIdentifier,
        public string $name,
        public ?string $specialization,
        public ?int $departmentId,
        public ?string $departmentName,
        public string $action,
        public array $issues = [],
        public ?float $gpaPoints = null,
        public ?float $gpaPercent = null,
        public ?string $grade = null,
        public ?float $gpa = null,
        public ?int $rank = null,
        public ?int $studyYear = null,
        public ?string $possibleDuplicate = null,
        // A top student's own semester when the decision lists several; null means the import's.
        public ?string $semester = null,
    ) {}
}
