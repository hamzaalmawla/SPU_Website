<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Structured preview payload for page or homepage content.
 */
final readonly class PreviewPayloadDTO
{
    public function __construct(
        public ?PageDTO $page = null,
        public ?HomepageDTO $homepage = null,
        public ?NavigationPayloadDTO $navigation = null,
    ) {}
}
