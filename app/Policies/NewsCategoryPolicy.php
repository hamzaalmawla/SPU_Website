<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\News\NewsCategory;
use App\Models\User\User;

final class NewsCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function delete(User $user, NewsCategory $category): bool
    {
        return $this->update($user, $category);
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function update(User $user, NewsCategory $category): bool
    {
        return $user->role_slug === 'editor';
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function view(User $user, NewsCategory $category): bool
    {
        return $this->viewAny($user);
    }
}
