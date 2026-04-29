<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\DTOs\AuditLogDTO;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Database-backed audit logging service.
 */
final class AuditService implements AuditServiceInterface
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * Write an audit log record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $metadata = []): bool
    {
        $auditLog = AuditLog::query()->create([
            'action' => $action,
            'user_id' => $userId,
            'actor_user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
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
                actorId: $auditLog->user_id !== null
                    ? (int) $auditLog->user_id
                    : ($auditLog->actor_user_id !== null ? (int) $auditLog->actor_user_id : null),
                entityType: $auditLog->entity_type,
                entityId: $auditLog->entity_id !== null ? (int) $auditLog->entity_id : null,
                createdAt: $auditLog->created_at?->toIso8601String() ?? now()->toIso8601String(),
                context: is_array($auditLog->metadata) ? $auditLog->metadata : [],
            ))
            ->values();
    }

    /**
     * Return distinct action values for admin filter dropdowns.
     *
     * @return array<string, string>
     */
    public function distinctActions(): array
    {
        return AuditLog::query()
            ->distinct()
            ->pluck('action', 'action')
            ->toArray();
    }

    /**
     * Return distinct entity type values for admin filter dropdowns.
     *
     * @return array<string, string>
     */
    public function distinctEntityTypes(): array
    {
        return AuditLog::query()
            ->distinct()
            ->whereNotNull('entity_type')
            ->pluck('entity_type', 'entity_type')
            ->toArray();
    }
}
