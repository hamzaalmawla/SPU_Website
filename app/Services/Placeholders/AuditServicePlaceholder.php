<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\AuditServiceInterface;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for audit service contract.
 */
final class AuditServicePlaceholder implements AuditServiceInterface
{
    public function logAction(string $action, int|string|null $actorId, array $context = []): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getRecent(int $limit = 50): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
