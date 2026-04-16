<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuthServiceInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Framework-backed authentication service for admin access checks.
 */
final class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly AuthFactory $authFactory,
        private readonly Session $session,
    ) {}

    /**
     * Attempt user authentication.
     *
     * @param  array<string, string>  $credentials
     */
    public function attempt(array $credentials): bool
    {
        return $this->authFactory->guard((string) config('auth.admin_guard', 'web'))->attempt($credentials);
    }

    /**
     * Check if the user has a specific role.
     */
    public function checkRole(mixed $user, string $role): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole($role);
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole([$role]);
        }

        $roles = $this->extractRoles($user);

        return in_array($role, $roles, true);
    }

    /**
     * Check whether the account is currently locked.
     */
    public function isLocked(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isLocked')) {
            return (bool) $user->isLocked();
        }

        $isLocked = $this->readAttribute($user, 'is_locked');

        if (is_bool($isLocked)) {
            return $isLocked;
        }

        return $this->readAttribute($user, 'locked_at') !== null;
    }

    /**
     * End the current authenticated session.
     */
    public function logout(): void
    {
        $this->authFactory->guard((string) config('auth.admin_guard', 'web'))->logout();

        if ($this->session->isStarted()) {
            $this->session->invalidate();
            $this->session->regenerateToken();
        }
    }

    /**
     * Extend active session lifetime.
     */
    public function extendSession(): void
    {
        if ($this->session->isStarted()) {
            $this->session->migrate(true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractRoles(object $user): array
    {
        $rawRoles = null;

        if (method_exists($user, 'getRoleNames')) {
            $rawRoles = $user->getRoleNames();
        }

        $rawRoles ??= $this->readAttribute($user, 'roles');

        if ($rawRoles !== null) {
            return $this->normalizeRoles($rawRoles);
        }

        $singleRole = $this->readAttribute($user, 'role_slug');

        if (is_string($singleRole) && $singleRole !== '') {
            return [$singleRole];
        }

        $singleRole = $this->readAttribute($user, 'role');

        return is_string($singleRole) && $singleRole !== '' ? [$singleRole] : [];
    }

    private function readAttribute(object $user, string $attribute): mixed
    {
        if (method_exists($user, 'getAttribute')) {
            return $user->getAttribute($attribute);
        }

        return isset($user->{$attribute}) ? $user->{$attribute} : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (is_string($roles)) {
            return $roles === '' ? [] : [$roles];
        }

        if (! is_array($roles)) {
            return [];
        }

        $normalized = [];

        foreach ($roles as $role) {
            if (is_string($role) && $role !== '') {
                $normalized[] = $role;

                continue;
            }

            if (is_object($role) && isset($role->name) && is_string($role->name) && $role->name !== '') {
                $normalized[] = $role->name;
            }
        }

        return array_values(array_unique($normalized));
    }
}
