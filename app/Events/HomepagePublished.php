<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when the homepage is published.
 */
final readonly class HomepagePublished
{
    use Dispatchable;

    public function __construct(
        public int $draftId,
        public int $actorId,
    ) {}
}
