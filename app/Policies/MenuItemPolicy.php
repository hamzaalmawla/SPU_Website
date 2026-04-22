<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorizes menu-management actions without assuming the MenuItem model exists yet.
 */
final class MenuItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, object $menuItem): bool
    {
        return $this->manage($user);
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function reorder(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, object $menuItem): bool
    {
        return $this->manage($user);
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }
}
