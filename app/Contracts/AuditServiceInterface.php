<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\AuditLogDTO;
use Illuminate\Support\Collection;

/**
 * Defines audit log write and retrieval operations.
 */
interface AuditServiceInterface
{
    /**
     * Write an audit log record.
     *
     * @param  array<string, mixed>  $context
     */
    public function logAction(string $action, int|string|null $actorId, array $context = []): void;

    /**
     * Retrieve recent audit log entries.
     *
     * @return Collection<int, AuditLogDTO>
     */
    public function getRecent(int $limit = 50): Collection;
}
