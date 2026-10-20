<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class AdminAccessService implements AdminAccessServiceInterface
{
    /**
     * Older internal slugs still found on some records, mapped to the public
     * slug a grant is stored as.
     */
    private const FACULTY_ALIASES = [
        'ai' => 'artificial-intelligence',
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    private const FACULTY_LABEL_KEYS = [
        'medicine' => 'admin_resources.person.medicine',
        'dentistry' => 'admin_resources.person.dentistry',
        'pharmacy' => 'admin_resources.person.pharmacy',
        'artificial-intelligence' => 'admin_resources.person.artificial_intelligence_engineering',
        'building-construction-engineering' => 'admin_resources.person.building_and_construction_engineering',
        'petroleum' => 'admin_resources.person.petroleum_engineering',
        'business-administration' => 'admin_resources.person.business_administration',
    ];

    public function isLimitedEditor(Authenticatable $user): bool
    {
        return $user instanceof User && $user->isLimitedEditor();
    }

    public function canManageFaculty(Authenticatable $user, ?string $facultySlug): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return true;
        }

        if (! $user->isLimitedEditor() || ! is_string($facultySlug) || trim($facultySlug) === '') {
            return false;
        }

        return in_array($this->canonicalFacultySlug($facultySlug), $this->grantedFaculties($user), true);
    }

    public function canManageTarget(Authenticatable $user, CmsTargetDTO $target): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->isLimitedEditor()) {
            return in_array($user->role_slug, ['super_admin', 'editor'], true)
                || ($target->key === 'campus_life.jobs' && $user->role_slug === 'hr');
        }

        if ($target->facultyScopeSlug !== null) {
            return $this->canManageFaculty($user, $target->facultyScopeSlug);
        }

        return $user->hasPageScope($target->key);
    }

    public function canEnterArea(Authenticatable $user, string $area): bool
    {
        if (! $user instanceof User || ! $user->isLimitedEditor()) {
            return false;
        }

        foreach ($user->pageScopes() as $targetKey) {
            if ($targetKey === $area || str_starts_with($targetKey, $area.'.')) {
                return true;
            }
        }

        return false;
    }

    public function facultyScopeValues(Authenticatable $user): array
    {
        $spellings = $this->facultySpellings();
        $values = [];

        foreach ($this->grantedFaculties($user) as $publicSlug) {
            array_push($values, ...($spellings[$publicSlug] ?? [$publicSlug]));
        }

        return array_values(array_unique($values));
    }

    public function grantedFaculties(Authenticatable $user): array
    {
        if (! $user instanceof User || ! $user->isLimitedEditor()) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (string $slug): string => $this->canonicalFacultySlug($slug),
            $user->facultyScopes(),
        )));
    }

    public function singleGrantedFaculty(Authenticatable $user): ?string
    {
        $faculties = $this->grantedFaculties($user);

        return count($faculties) === 1 ? $faculties[0] : null;
    }

    public function facultyForWrite(Authenticatable $user, ?string $requestedFaculty): ?string
    {
        if (is_string($requestedFaculty) && trim($requestedFaculty) !== '') {
            $requested = $this->canonicalFacultySlug($requestedFaculty);

            if (in_array($requested, $this->grantedFaculties($user), true)) {
                return $requested;
            }
        }

        return $this->singleGrantedFaculty($user);
    }

    public function canonicalFacultySlug(string $facultySlug): string
    {
        $facultySlug = trim($facultySlug);

        foreach ($this->facultySpellings() as $publicSlug => $spellings) {
            if (in_array($facultySlug, $spellings, true)) {
                return $publicSlug;
            }
        }

        return $facultySlug;
    }

    public function facultyOptions(): array
    {
        return array_map(static fn (string $key): string => __($key), self::FACULTY_LABEL_KEYS);
    }

    /**
     * Not memoized: the service is a container singleton and faculties can be
     * added while it lives.
     *
     * @return array<string, list<string>> public slug => every slug spelling
     */
    private function facultySpellings(): array
    {
        $spellings = [];

        foreach (array_keys(self::FACULTY_LABEL_KEYS) as $publicSlug) {
            $spellings[$publicSlug] = [$publicSlug];
        }

        foreach (self::FACULTY_ALIASES as $alias => $publicSlug) {
            $spellings[$publicSlug][] = $alias;
        }

        Faculty::query()
            ->whereNotNull('public_slug')
            ->get(['slug', 'public_slug', 'faculty_scope_slug'])
            ->each(function (Faculty $faculty) use (&$spellings): void {
                $publicSlug = (string) $faculty->public_slug;
                $spellings[$publicSlug] ??= [$publicSlug];

                foreach ([$faculty->slug, $faculty->faculty_scope_slug] as $spelling) {
                    if (is_string($spelling) && $spelling !== '') {
                        $spellings[$publicSlug][] = $spelling;
                    }
                }
            });

        return array_map(
            static fn (array $values): array => array_values(array_unique($values)),
            $spellings,
        );
    }
}
