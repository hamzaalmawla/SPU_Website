<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\AuditLogDTO;
use App\DTOs\PaginatedResultDTO;
use Illuminate\Support\Collection;

/**
 * Defines audit logging operations for admin-managed content.
 */
interface AuditServiceInterface
{
    /**
     * Write an audit log record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $metadata = []): bool;

    /**
     * Retrieve audit entries for a specific entity.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function forEntity(string $entityType, int $entityId): Collection;

    /**
     * Retrieve audit entries for a specific action.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function forAction(string $action): Collection;

    /**
     * Retrieve recent audit log entries.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function latest(int $limit = 50): Collection;

    /**
     * Paginated audit log listing for non-Filament consumers.
     */
    public function latestPaginated(int $page = 1, int $perPage = 50): PaginatedResultDTO;

    /**
     * Return distinct action values for admin filter dropdowns.
     *
     * @return array<string, string> Keyed by action value.
     */
    public function distinctActions(): array;

    /**
     * Return distinct entity type values for admin filter dropdowns.
     *
     * @return array<string, string> Keyed by entity type value.
     */
    public function distinctEntityTypes(): array;
}
