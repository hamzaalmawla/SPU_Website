<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Defines authentication and account lock operations.
 */
interface AuthServiceInterface
{
    /**
     * Attempt user authentication.
     *
     * @param  array<string, string>  $credentials
     */
    public function attempt(array $credentials): bool;

    /**
     * Lock a user account.
     */
    public function lockAccount(int|string $userId): void;

    /**
     * Unlock a user account.
     */
    public function unlockAccount(int|string $userId): void;

    /**
     * Check if the user has a specific role.
     */
    public function checkRole(int|string $userId, string $role): bool;

    /**
     * Check whether the account is currently locked.
     */
    public function isLocked(int|string $userId): bool;

    /**
     * Extend active session lifetime.
     */
    public function extendSession(): void;
}
