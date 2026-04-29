<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a page is unpublished.
 */
final readonly class PageUnpublished
{
    use Dispatchable;

    public function __construct(
        public int $pageId,
        public int $actorId,
    ) {}
}
