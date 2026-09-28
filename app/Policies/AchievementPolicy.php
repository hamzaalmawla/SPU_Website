<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Achievement\Achievement;
use App\Models\User\User;

final class AchievementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function view(User $user, Achievement $achievement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Achievement $achievement): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Achievement $achievement): bool
    {
        return $this->viewAny($user);
    }
}
