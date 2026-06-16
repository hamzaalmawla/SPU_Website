<?php

declare(strict_types=1);

namespace App\DTOs\Settings;

use App\DTOs\Navigation\NavigationActionDTO;

/**
 * Footer link column used by the homepage footer section.
 */
final readonly class FooterColumnDTO
{
    /**
     * @param  array<int, NavigationActionDTO>  $links
     */
    public function __construct(
        public string $title,
        public array $links,
    ) {}
}
