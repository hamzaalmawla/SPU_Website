<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorizes homepage draft and publish actions without assuming a concrete draft model yet.
 */
final class HomepagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function preview(User $user): bool
    {
        return $this->manage($user);
    }

    public function publish(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user): bool
    {
        return $this->manage($user);
    }

    public function viewDraft(User $user): bool
    {
        return $this->manage($user);
    }
}
