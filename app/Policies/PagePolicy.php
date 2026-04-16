<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorizes page actions while the concrete Page model is still out of repo scope.
 */
final class PagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor' || ($user->role_slug === 'faculty_editor' && $user->faculty_scope_slug !== null);
    }

    public function delete(User $user, object $page): bool
    {
        return $this->update($user, $page);
    }

    public function manage(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function preview(User $user, object $page): bool
    {
        return $this->update($user, $page);
    }

    public function publish(User $user, object $page): bool
    {
        return $user->role_slug === 'editor';
    }

    public function update(User $user, object $page): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        if ($user->role_slug !== 'faculty_editor') {
            return false;
        }

        return $this->sharesFacultyScope($user, $page);
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, object $page): bool
    {
        return $this->update($user, $page);
    }

    private function sharesFacultyScope(User $user, object $page): bool
    {
        if ($user->faculty_scope_slug === null || $user->faculty_scope_slug === '') {
            return false;
        }

        $facultyScope = $this->readFacultyScope($page);

        return $facultyScope !== null && $facultyScope === $user->faculty_scope_slug;
    }

    private function readFacultyScope(object $page): ?string
    {
        if (method_exists($page, 'getAttribute')) {
            $facultyScope = $page->getAttribute('faculty_scope_slug');

            return is_string($facultyScope) && $facultyScope !== '' ? $facultyScope : null;
        }

        if (property_exists($page, 'faculty_scope_slug')) {
            $facultyScope = $page->faculty_scope_slug;

            return is_string($facultyScope) && $facultyScope !== '' ? $facultyScope : null;
        }

        return null;
    }
}
