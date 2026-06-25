<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\User\User;

final class FacultyDomainPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function create(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function delete(User $user, mixed $record): bool
    {
        return $this->update($user, $record);
    }

    public function manage(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function update(User $user, mixed $record): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        if ($user->role_slug !== 'faculty_editor') {
            return false;
        }

        $userScope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';

        return $userScope !== '' && $this->recordScope($record) === $userScope;
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

        if ($record instanceof FacultyPage || $record instanceof FacultyHighlight || $record instanceof FacultyLab || $record instanceof FacultyStudentProject || $record instanceof Alumni || $record instanceof HonorStudent) {
            $record->loadMissing('faculty');

            return $record->faculty?->faculty_scope_slug ?? $record->faculty?->public_slug ?? $record->faculty?->slug;
        }

        return null;
    }
}
