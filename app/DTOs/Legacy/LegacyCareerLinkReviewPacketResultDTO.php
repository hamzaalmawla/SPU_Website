<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

/** @param array<string, int> $blockerCounts @param list<string> $paths @param list<string> $warnings */
final readonly class LegacyCareerLinkReviewPacketResultDTO
{
    public function __construct(
        public string $disk,
        public int $totalRows,
        public int $candidateRows,
        public array $blockerCounts,
        public array $paths,
        public array $warnings,
    ) {}
}
