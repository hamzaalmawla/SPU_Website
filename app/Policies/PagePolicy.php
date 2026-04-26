<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Authorizes landing-page and homepage-shell actions against the current page model.
 *
 * The current pages table does not expose a page-level faculty scope column, so page
 * management remains editor-only until a later phase introduces a schema-backed scope rule.
 */
final class PagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function preview(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->role_slug === 'editor';
    }

    public function update(User $user, Page $page): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }
}
