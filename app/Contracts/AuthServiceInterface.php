<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\LoginCredentialsDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Defines authentication and access checks for the admin foundation.
 */
interface AuthServiceInterface
{
    /**
     * Attempt user authentication.
     */
    public function attempt(LoginCredentialsDTO $credentials): bool;

    /**
     * Check if the user has a specific role.
     */
    public function checkRole(Authenticatable $user, string $role): bool;

    /**
     * Check whether the account is currently locked.
     */
    public function isLocked(Authenticatable $user): bool;

    /**
     * End the current authenticated session.
     */
    public function logout(): void;

    /**
     * Extend active session lifetime.
     */
    public function extendSession(): void;

    public function recordFailedTwoFactor(Authenticatable $user): void;

    public function recordSuccessfulTwoFactor(Authenticatable $user): void;

    /**
     * Update an admin-managed user account.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateUser(int $userId, array $payload, int $actorUserId): bool;
}
