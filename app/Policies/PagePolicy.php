<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page\Page;
use App\Models\User\User;

/**
 * Authorizes landing-page and homepage-shell actions against the current page model.
 *
 * The `manage` ability is reserved for full page management (editor-only) and is used
 * by the `manage-pages` gate. The `viewAny` ability grants list-level access to both
 * editors and faculty editors. Faculty editors are scoped to pages matching their
 * `faculty_scope_slug`.
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

        if ($user->role_slug === 'faculty_editor') {
            $userScope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
            $pageScope = $page->getAttribute('faculty_scope_slug');

            return $userScope !== '' && is_string($pageScope) && $pageScope === $userScope;
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }
}
