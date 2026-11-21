<?php

declare(strict_types=1);

namespace App\DTOs\Shared;

/**
 * Breadcrumb trail for a public page.
 */
final readonly class BreadcrumbTrailDTO
{
    /**
     * @param  array<int, BreadcrumbItemDTO>  $items
     */
    public function __construct(
        public string $locale,
        public array $items,
    ) {}
}
