<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\AuditServiceInterface;
use App\DTOs\AuditLogDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for audit service contract.
 */
final class AuditServicePlaceholder implements AuditServiceInterface
{
    public function log(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $metadata = []): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function forEntity(string $entityType, int $entityId): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function forAction(string $action): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    /**
     * @return Collection<int, AuditLogDTO>
     */
    public function latest(int $limit = 50): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
