<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

/**
 * A faculty's leadership and council for one locale, as the public page shows them.
 * Every person is a public academic staff profile of the faculty.
 */
final readonly class FacultyCouncilDTO
{
    /**
     * @param  list<array{type: string, title: string, memberId: int, name: string, image: ?string, url: string}>  $positions  dean first, then vice deans, then other positions
     * @param  list<array{memberId: int, name: string, image: ?string, url: string, roles: list<string>, isChair: bool}>  $members  one entry per person, with every role they hold on the council
     */
    public function __construct(
        public array $positions,
        public array $members,
    ) {}
}
