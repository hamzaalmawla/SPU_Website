<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\AuthServiceInterface;
use App\DTOs\LoginCredentialsDTO;
use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Placeholder implementation for auth service contract.
 */
final class AuthServicePlaceholder implements AuthServiceInterface
{
    public function attempt(LoginCredentialsDTO $credentials): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function checkRole(Authenticatable $user, string $role): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function isLocked(Authenticatable $user): bool
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

    public function recordFailedTwoFactor(Authenticatable $user): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function recordSuccessfulTwoFactor(Authenticatable $user): void
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateUser(int $userId, array $payload, int $actorUserId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
