<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Person\FacultyMember;
use App\Models\User\User;

/**
 * Authorizes faculty members through the `faculty_members.*` permissions,
 * inside the user's faculty limit.
 */
final class FacultyMemberPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'faculty_members.view');
    }

    public function view(User $user, FacultyMember $member): bool
    {
        return $this->allowed($user, 'faculty_members.view', $member);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'faculty_members.create');
    }

    public function update(User $user, FacultyMember $member): bool
    {
        return $this->allowed($user, 'faculty_members.update', $member);
    }

    public function delete(User $user, FacultyMember $member): bool
    {
        return $this->allowed($user, 'faculty_members.delete', $member);
    }

    private function allowed(User $user, string $permission, FacultyMember $member): bool
    {
        if (! $this->access->can($user, $permission)) {
            return false;
        }

        $member->loadMissing('faculty');
        $faculty = $member->faculty;

        return $this->access->withinFacultyLimit($user, $faculty?->public_slug ?? $faculty?->faculty_scope_slug ?? $faculty?->slug);
    }
}
