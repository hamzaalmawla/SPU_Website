<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Page\Page;
use App\Models\User\User;

/**
 * Authorizes landing pages through the `pages.*` permissions. A user with a
 * faculty limit only reaches pages whose `faculty_scope_slug` is inside it.
 */
final class PagePolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $this->allowed($user, 'pages.view', $page);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'pages.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $this->allowed($user, 'pages.update', $page);
    }

    public function preview(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    public function publish(User $user, Page $page): bool
    {
        return $this->allowed($user, 'pages.publish', $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->allowed($user, 'pages.delete', $page);
    }

    private function allowed(User $user, string $permission, Page $page): bool
    {
        $scope = $page->getAttribute('faculty_scope_slug');

        return $this->access->can($user, $permission)
            && $this->access->withinFacultyLimit($user, is_string($scope) ? $scope : null);
    }
}
