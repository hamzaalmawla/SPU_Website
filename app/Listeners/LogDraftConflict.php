<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Shared\AuditServiceInterface;
use App\Events\DraftConflictDetected;

/**
 * Logs draft conflict events to the audit service.
 *
 * When an optimistic locking conflict is detected during a draft save,
 * this listener records the conflict details for audit trail purposes.
 */
final class LogDraftConflict
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
    ) {}

    /**
     * Handle DraftConflictDetected events.
     */
    public function handle(DraftConflictDetected $event): void
    {
        $this->auditService->log(
            action: 'draft.conflict',
            userId: $event->actorId,
            entityType: $event->entityType,
            entityId: $event->entityId,
            metadata: [
                'expected_version' => $event->expectedVersion,
                'actual_version' => $event->actualVersion,
            ],
        );
    }
}
