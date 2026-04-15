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

    public function checkRole(mixed $user, string $role): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function isLocked(mixed $user): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function logout(): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function extendSession(): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
