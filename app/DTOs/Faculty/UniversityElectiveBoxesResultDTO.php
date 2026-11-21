<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

/**
 * What replacing listed university electives with elective boxes found (and, on
 * a write, saved), per faculty program.
 */
final readonly class UniversityElectiveBoxesResultDTO
{
    /**
     * @param  list<array{faculty: string, program: string, removed: string, added: string, kept: string}>  $changes  programs that change
     * @param  list<string>  $unchanged  faculties whose plan already has its boxes and no listed electives
     * @param  list<string>  $savedFaculties  faculties whose study plan draft was saved
     * @param  list<string>  $publishedFaculties  faculties whose saved draft was also published
     * @param  array<string, string>  $notPublished  faculty => why its saved draft was left unpublished
     */
    public function __construct(
        public array $changes,
        public array $unchanged,
        public array $savedFaculties,
        public bool $written,
        public array $publishedFaculties = [],
        public array $notPublished = [],
    ) {}
}
