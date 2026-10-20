<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;

/**
 * Editors manage every research publication. Faculty editors manage the ones
 * attributed to a researcher of their own faculty.
 */
final class ResearchPublicationPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function view(User $user, ResearchPublication $publication): bool
    {
        return $this->update($user, $publication);
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor'
            || $this->access->grantedFaculties($user) !== [];
    }

    public function update(User $user, ResearchPublication $publication): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        if ($this->access->grantedFaculties($user) === []) {
            return false;
        }

        $publication->loadMissing(['faculty', 'facultyMember.faculty']);
        $faculty = $publication->effectiveFaculty();

        return $faculty instanceof Faculty
            && $this->access->canManageFaculty($user, $faculty->public_slug ?: $faculty->faculty_scope_slug ?: $faculty->slug);
    }

    public function delete(User $user, ResearchPublication $publication): bool
    {
        return $this->update($user, $publication);
    }
}
