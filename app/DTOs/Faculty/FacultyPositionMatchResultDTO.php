<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

/**
 * What matching typed profile positions found (and, on a write, saved), per faculty.
 */
final readonly class FacultyPositionMatchResultDTO
{
    /**
     * @param  list<array{faculty: string, member: string, typed: string, position: string}>  $matched  rows added (or that a write would add) to the council page
     * @param  list<array{faculty: string, member: string, typed: string, position: string}>  $present  already on the council page
     * @param  list<array{faculty: string, member: string, typed: string}>  $heads  heads of department, set on the departments page instead
     * @param  list<array{faculty: string, member: string, typed: string, reason: string}>  $skipped  matched but not added
     * @param  list<array{faculty: string, member: string, typed: string}>  $unmatched  no position in the list fits
     * @param  list<string>  $savedFaculties  faculties whose council draft was saved
     */
    public function __construct(
        public array $matched,
        public array $present,
        public array $heads,
        public array $skipped,
        public array $unmatched,
        public array $savedFaculties,
        public bool $written,
    ) {}
}
