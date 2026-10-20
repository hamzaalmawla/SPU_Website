<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User\User;

/**
 * Admin accounts, roles and permissions are managed by super admins only:
 * a user who can grant permissions could grant themselves anything.
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $user->isSuperAdmin();
    }
}
