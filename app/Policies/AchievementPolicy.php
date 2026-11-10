<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Achievement\Achievement;
use App\Models\User\User;

/**
 * Authorizes Achievement records through the `achievements.*` permissions.
 */
final class AchievementPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'achievements.view');
    }

    public function view(User $user, Achievement $achievement): bool
    {
        return $this->access->can($user, 'achievements.view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'achievements.create');
    }

    public function update(User $user, Achievement $achievement): bool
    {
        return $this->access->can($user, 'achievements.update');
    }

    public function delete(User $user, Achievement $achievement): bool
    {
        return $this->access->can($user, 'achievements.delete');
    }
}
