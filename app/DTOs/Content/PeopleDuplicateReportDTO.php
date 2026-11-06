<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PeopleDuplicateReportDTO
{
    /**
     * Each section is a list of groups; each group lists the records that look
     * like one person, as short display lines ("R12 Saeed Kadri - 6 papers").
     *
     * @param  array<string, list<list<string>>>  $sections  Section title to groups
     * @param  array<string, int>  $totals  Record counts per list
     */
    public function __construct(
        public array $sections,
        public array $totals,
    ) {}
}
