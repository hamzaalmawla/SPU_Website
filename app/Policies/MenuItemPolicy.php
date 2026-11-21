<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Navigation\MenuItem;
use App\Models\User\User;

/**
 * Opening the menu builder needs `menu.view`; any change needs `menu.update`.
 */
final class MenuItemPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'menu.view');
    }

    public function manage(User $user): bool
    {
        return $this->access->can($user, 'menu.update');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $this->manage($user);
    }

    public function reorder(User $user): bool
    {
        return $this->manage($user);
    }
}
