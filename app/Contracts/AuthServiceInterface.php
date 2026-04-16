<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Defines authentication and access checks for the admin foundation.
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
}
