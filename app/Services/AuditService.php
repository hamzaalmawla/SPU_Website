<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\DTOs\AuditLogDTO;
use App\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * Database-backed audit logging service.
 */
final class AuditService implements AuditServiceInterface
{
    /**
     * Write an audit log record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $metadata = []): bool
    {
        $auditLog = AuditLog::query()->create([
            'action' => $action,
            'actor_user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);

        return $auditLog->exists;
    }

    /**
     * Retrieve audit entries for a specific entity.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function forEntity(string $entityType, int $entityId): Collection
    {
        return $this->mapToDtos(
            AuditLog::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->latest('created_at')
                ->get()
        );
    }

    /**
     * Retrieve audit entries for a specific action.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function forAction(string $action): Collection
    {
        return $this->mapToDtos(
            AuditLog::query()
                ->where('action', $action)
                ->latest('created_at')
                ->get()
        );
    }

    /**
     * Retrieve recent audit log entries.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function latest(int $limit = 50): Collection
    {
        return $this->mapToDtos(
            AuditLog::query()
                ->latest('created_at')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * @param  Collection<int, AuditLog>  $auditLogs
     * @return Collection<int, AuditLogDTO>
     */
    private function mapToDtos(Collection $auditLogs): Collection
    {
        return $auditLogs
            ->map(fn (AuditLog $auditLog): AuditLogDTO => new AuditLogDTO(
                id: (int) $auditLog->getKey(),
                action: (string) $auditLog->action,
                actorId: $auditLog->actor_user_id !== null ? (int) $auditLog->actor_user_id : null,
                entityType: $auditLog->entity_type,
                entityId: $auditLog->entity_id !== null ? (int) $auditLog->entity_id : null,
                createdAt: $auditLog->created_at?->toIso8601String() ?? now()->toIso8601String(),
                context: is_array($auditLog->metadata) ? $auditLog->metadata : [],
            ))
            ->values();
    }
}
