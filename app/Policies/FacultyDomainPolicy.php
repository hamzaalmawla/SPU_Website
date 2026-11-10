<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\User\User;

/**
 * Faculty-owned content edited on a faculty's admin screen (the faculty
 * itself, its pages, highlights, labs, projects, alumni and honor students)
 * needs that faculty's `faculty_<slug>.update` permission and must be inside
 * the user's faculty limit.
 */
final class FacultyDomainPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly PermissionCatalogInterface $catalog,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->canEditAnyFaculty($user);
    }

    public function create(User $user): bool
    {
        return $this->canEditAnyFaculty($user);
    }

    public function view(User $user, mixed $record): bool
    {
        return $this->update($user, $record);
    }

    public function update(User $user, mixed $record): bool
    {
        $scope = $this->recordScope($record);

        return $scope !== null
            && $this->access->can($user, $this->catalog->componentForFaculty($this->access->canonicalFacultySlug($scope)).'.update')
            && $this->access->withinFacultyLimit($user, $scope);
    }

    public function delete(User $user, mixed $record): bool
    {
        return $this->update($user, $record);
    }

    private function canEditAnyFaculty(User $user): bool
    {
        return $this->access->canAny($user, array_map(
            static fn (string $component): string => $component.'.update',
            $this->catalog->facultyComponents(),
        ));
    }

    private function recordScope(mixed $record): ?string
    {
        if ($record instanceof Faculty) {
            return $record->public_slug ?? $record->faculty_scope_slug ?? $record->slug;
        }

        if ($record instanceof FacultyPage || $record instanceof FacultyHighlight || $record instanceof FacultyLab || $record instanceof FacultyStudentProject || $record instanceof Alumni || $record instanceof HonorStudent) {
            $record->loadMissing('faculty');

            return $record->faculty?->public_slug ?? $record->faculty?->faculty_scope_slug ?? $record->faculty?->slug;
        }

        return null;
    }
}
