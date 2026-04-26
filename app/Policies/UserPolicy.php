<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorizes admin user-management actions against the existing users table.
 */
final class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, User $targetUser): bool
    {
        return false;
    }

    public function manageUsers(User $user): bool
    {
        return false;
    }

    public function manageSettings(User $user): bool
    {
        return $this->hasAnyRole($user, ['editor']);
    }

    public function manageMedia(User $user): bool
    {
        return $this->hasAnyRole($user, ['editor', 'faculty_editor']);
    }

    public function publishContent(User $user): bool
    {
        return $this->hasAnyRole($user, ['editor']);
    }

    public function previewContent(User $user): bool
    {
        return $this->hasAnyRole($user, ['editor', 'faculty_editor']);
    }

    public function update(User $user, User $targetUser): bool
    {
        return false;
    }

    public function view(User $user, User $targetUser): bool
    {
        return false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function viewAuditLog(User $user): bool
    {
        return false;
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return in_array($user->role_slug, $roles, true);
    }
}
