<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Data transfer object for homepage draft saves.
 */
final readonly class HomepageDraftWriteDTO
{
    /**
     * @param  array<int, HomepageSectionWriteDTO>  $sections
     */
    public function __construct(
        public array $sections,
    ) {}
}
