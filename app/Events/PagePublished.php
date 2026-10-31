<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a page is published.
 */
final readonly class PagePublished
{
    use Dispatchable;

    public function __construct(
        public int $pageId,
        public int $actorId,
    ) {}
}
