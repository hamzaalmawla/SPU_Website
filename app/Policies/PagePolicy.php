<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Page\Page;
use App\Models\User\User;

/**
 * Authorizes landing-page and homepage-shell actions against the current page model.
 *
 * The `manage` ability is reserved for full page management (editor-only) and is used
 * by the `manage-pages` gate. The `viewAny` ability grants list-level access to both
 * editors and limited editors. Limited editors are scoped to pages whose
 * `faculty_scope_slug` is one of their granted faculties.
 */
final class PagePolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

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

        $pageScope = $page->getAttribute('faculty_scope_slug');

        return $user->isLimitedEditor() && is_string($pageScope) && $this->access->canManageFaculty($user, $pageScope);
    }

    public function viewAny(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function view(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }
}
