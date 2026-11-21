<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when an optimistic locking conflict is detected during a draft save.
 */
final readonly class DraftConflictDetected
{
    use Dispatchable;

    public function __construct(
        public string $entityType,
        public int $entityId,
        public int $expectedVersion,
        public int $actualVersion,
        public int $actorId,
    ) {}
}
