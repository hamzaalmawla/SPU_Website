<?php

declare(strict_types=1);

namespace App\DTOs\Navigation;

/**
 * CTA or shortcut link shown in the public navigation shell.
 */
final readonly class NavigationActionDTO
{
    public function __construct(
        public string $label,
        public string $url,
        public ?string $target = null,
    ) {}
}
