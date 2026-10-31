<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Achievement\AchievementCategory;
use App\Models\User\User;

/**
 * Authorizes AchievementCategory records through the `achievement_categories.*` permissions.
 */
final class AchievementCategoryPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'achievement_categories.view');
    }

    public function view(User $user, AchievementCategory $category): bool
    {
        return $this->access->can($user, 'achievement_categories.view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'achievement_categories.create');
    }

    public function update(User $user, AchievementCategory $category): bool
    {
        return $this->access->can($user, 'achievement_categories.update');
    }

    public function delete(User $user, AchievementCategory $category): bool
    {
        return $this->access->can($user, 'achievement_categories.delete');
    }
}
