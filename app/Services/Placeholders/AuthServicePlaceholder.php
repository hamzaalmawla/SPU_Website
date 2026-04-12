<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\AuthServiceInterface;
use BadMethodCallException;

/**
 * Placeholder implementation for auth service contract.
 */
final class AuthServicePlaceholder implements AuthServiceInterface
{
    public function attempt(array $credentials): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function lockAccount(int|string $userId): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unlockAccount(int|string $userId): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function checkRole(int|string $userId, string $role): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function isLocked(int|string $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function extendSession(): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
