<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\Person\FacultyMember;
use App\Models\User\User;

final class FacultyDomainPolicy
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
        return $user->role_slug === 'editor'
            || $this->access->grantedFaculties($user) !== [];
    }

    public function delete(User $user, mixed $record): bool
    {
        return $this->update($user, $record);
    }

    public function manage(User $user): bool
    {
        return $user->role_slug === 'editor' || $this->access->grantedFaculties($user) !== [];
    }

    public function update(User $user, mixed $record): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        return $user->isLimitedEditor() && $this->access->canManageFaculty($user, $this->recordScope($record));
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user, mixed $record): bool
    {
        return $this->update($user, $record);
    }

    private function recordScope(mixed $record): ?string
    {
        if ($record instanceof Faculty) {
            return $record->faculty_scope_slug ?? $record->public_slug ?? $record->slug;
        }

        if ($record instanceof FacultySubpageCard) {
            return $record->faculty_slug;
        }

        if ($record instanceof FacultyPage || $record instanceof FacultyHighlight || $record instanceof FacultyLab || $record instanceof FacultyStudentProject || $record instanceof Alumni || $record instanceof HonorStudent || $record instanceof FacultyMember) {
            $record->loadMissing('faculty');

            return $record->faculty?->faculty_scope_slug ?? $record->faculty?->public_slug ?? $record->faculty?->slug;
        }

        return null;
    }
}
