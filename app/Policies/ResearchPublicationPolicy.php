<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faculty\Faculty;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;

/**
 * Editors manage every research publication. Faculty editors manage the ones
 * attributed to a researcher of their own faculty.
 */
final class ResearchPublicationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function view(User $user, ResearchPublication $publication): bool
    {
        return $this->update($user, $publication);
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor'
            || ($user->role_slug === 'faculty_editor' && is_string($user->faculty_scope_slug) && $user->faculty_scope_slug !== '');
    }

    public function update(User $user, ResearchPublication $publication): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        if ($user->role_slug !== 'faculty_editor' || ! is_string($user->faculty_scope_slug) || $user->faculty_scope_slug === '') {
            return false;
        }

        $publication->loadMissing(['faculty', 'facultyMember.faculty']);
        $faculty = $publication->effectiveFaculty();

        return $faculty instanceof Faculty
            && in_array($user->faculty_scope_slug, array_filter([$faculty->faculty_scope_slug, $faculty->public_slug, $faculty->slug]), true);
    }

    public function delete(User $user, ResearchPublication $publication): bool
    {
        return $this->update($user, $publication);
    }
}
