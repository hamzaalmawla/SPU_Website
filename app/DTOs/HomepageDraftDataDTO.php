<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Structured draft payload for the fixed homepage editor.
 */
final readonly class HomepageDraftDataDTO
{
    /**
     * @param  array<int, HomepageSectionDTO>  $sections
     */
    public function __construct(
        public array $sections,
    ) {}
}
