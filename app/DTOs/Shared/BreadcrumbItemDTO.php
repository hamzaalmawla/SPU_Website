<?php

declare(strict_types=1);

namespace App\DTOs\Shared;

/**
 * Single breadcrumb item for public page navigation.
 */
final readonly class BreadcrumbItemDTO
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $isCurrent,
    ) {}
}
