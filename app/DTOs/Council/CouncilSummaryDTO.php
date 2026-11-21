<?php

declare(strict_types=1);

namespace App\DTOs\Council;

/**
 * A council's card on the Councils page: its name, introduction, who heads it
 * and how many members and functions it has.
 */
final readonly class CouncilSummaryDTO
{
    /** @param list<string> $headNames */
    public function __construct(
        public string $slug,
        public string $url,
        public string $title,
        public string $summary,
        public ?string $image,
        public array $headNames,
        public int $memberCount,
        public int $functionCount,
    ) {}
}
