<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;

/**
 * Authorizes research publications through the `research_publications.*`
 * permissions. A user with a faculty limit only reaches publications whose
 * faculty (or researcher's faculty) is inside it.
 */
final class ResearchPublicationPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'research_publications.view');
    }

    public function view(User $user, ResearchPublication $publication): bool
    {
        return $this->allowed($user, 'research_publications.view', $publication);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'research_publications.create');
    }

    public function update(User $user, ResearchPublication $publication): bool
    {
        return $this->allowed($user, 'research_publications.update', $publication);
    }

    public function publish(User $user, ResearchPublication $publication): bool
    {
        return $this->allowed($user, 'research_publications.publish', $publication);
    }

    public function delete(User $user, ResearchPublication $publication): bool
    {
        return $this->allowed($user, 'research_publications.delete', $publication);
    }

    private function allowed(User $user, string $permission, ResearchPublication $publication): bool
    {
        if (! $this->access->can($user, $permission)) {
            return false;
        }

        if (! $this->access->hasFacultyLimit($user)) {
            return true;
        }

        $publication->loadMissing(['faculty', 'facultyMember.faculty']);
        $faculty = $publication->effectiveFaculty();

        return $faculty instanceof Faculty
            && $this->access->withinFacultyLimit($user, $faculty->public_slug ?: $faculty->faculty_scope_slug ?: $faculty->slug);
    }
}
