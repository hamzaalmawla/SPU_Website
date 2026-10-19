<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PeopleMergeResultDTO
{
    /** @param list<string> $lines One line per planned or done merge */
    public function __construct(
        public bool $written,
        public int $researchersMerged,
        public int $membersMerged,
        public int $membersLinked,
        public int $leftAlone,
        public array $lines,
    ) {}
}
