<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

/**
 * A faculty's leadership and council for one locale, as the public page shows them.
 * Every person is a public academic staff profile of the faculty, with `name`,
 * `image`, `url` and `bio` (the start of their profile's bio).
 */
final readonly class FacultyCouncilDTO
{
    /**
     * @param  array{memberId: int, name: string, image: ?string, url: string, bio: string, title: string}|null  $dean  the dean, who chairs the council
     * @param  list<array{memberId: int, name: string, image: ?string, url: string, bio: string, titles: list<string>}>  $leadership  the other leadership positions, one card per person
     * @param  list<array{memberId: int, name: string, image: ?string, url: string, bio: string, roles: list<string>}>  $members  the council list, one card per person with every role they hold there
     * @param  list<array{department: string, type: string, memberId: int, name: string, image: ?string, url: string, bio: string, title: string}>  $heads  department heads, in the departments page's order
     */
    public function __construct(
        public ?array $dean,
        public array $leadership,
        public array $members,
        public array $heads,
    ) {}
}
