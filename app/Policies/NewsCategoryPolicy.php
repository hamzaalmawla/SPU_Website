<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\News\NewsCategory;
use App\Models\User\User;

/**
 * Authorizes NewsCategory records through the `news_categories.*` permissions.
 */
final class NewsCategoryPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'news_categories.view');
    }

    public function view(User $user, NewsCategory $category): bool
    {
        return $this->access->can($user, 'news_categories.view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'news_categories.create');
    }

    public function update(User $user, NewsCategory $category): bool
    {
        return $this->access->can($user, 'news_categories.update');
    }

    public function delete(User $user, NewsCategory $category): bool
    {
        return $this->access->can($user, 'news_categories.delete');
    }
}
