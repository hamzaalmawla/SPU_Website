<?php

declare(strict_types=1);

namespace App\DTOs\Career;

/** What importing a decision file would do, shown before the editor confirms. */
final readonly class StudentListImportPreviewDTO
{
    /**
     * @param  list<StudentListImportRowDTO>  $rows
     * @param  list<StudentListSpecializationDTO>  $specializations
     * @param  array<int, string>  $departmentOptions  department id => name in the editor's locale
     * @param  list<string>  $warnings  translation keys under student_lists.warnings
     */
    public function __construct(
        public string $kind,
        public string $facultySlug,
        public string $facultyName,
        public string $fileName,
        public ?string $decisionNumber,
        public ?string $academicYear,
        public ?string $semester,
        public array $rows,
        public array $specializations,
        public array $departmentOptions,
        public int $createCount,
        public int $updateCount,
        public int $skipCount,
        public array $warnings = [],
    ) {}
}
