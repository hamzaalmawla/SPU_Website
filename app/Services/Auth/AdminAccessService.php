<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\Faculty\Faculty;
use App\Models\User\Role;
use App\Models\User\RolePermission;
use App\Models\User\User;
use App\Models\User\UserPermission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class AdminAccessService implements AdminAccessServiceInterface
{
    /**
     * Older internal slugs still found on some records, mapped to the public
     * slug a limit is stored as.
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

    /**
     * Permissions per user for this request (or queued job). Role and grant
     * writes call flushPermissions(); the queue flushes before each job.
     *
     * @var array<string, list<string>>
     */
    private array $permissions = [];

    public function __construct(
        private readonly PermissionCatalogInterface $catalog,
    ) {}

    public function isSuperAdmin(Authenticatable $user): bool
    {
        return $user instanceof User && $user->isSuperAdmin();
    }

    public function can(Authenticatable $user, string $permission): bool
    {
        return $this->isSuperAdmin($user) || in_array($permission, $this->permissionsFor($user), true);
    }

    public function canAny(Authenticatable $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function permissionsFor(Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return $this->catalog->all();
        }

        // Keyed by the user's state too, so a save of the user (new role,
        // changed grants) is seen at once in the same request.
        $key = $user->getKey().'|'.$user->role_slug.'|'.$user->updated_at?->format('U.u');

        return $this->permissions[$key] ??= $this->loadPermissions($user);
    }

    public function hasAdminAccess(Authenticatable $user): bool
    {
        return $this->isSuperAdmin($user) || $this->permissionsFor($user) !== [];
    }

    public function userIdsWithPermission(string $permission): array
    {
        $permissions = str_ends_with($permission, '.publish')
            ? [$permission, substr($permission, 0, -strlen('publish')).'update']
            : [$permission];
        $roleIds = RolePermission::query()->whereIn('permission', $permissions)->pluck('role_id')->all();
        $roleSlugs = Role::query()->whereKey($roleIds)->pluck('slug')->all();

        return User::query()
            ->where(function (Builder $query) use ($permissions, $roleIds, $roleSlugs): void {
                $query->where('role_slug', 'super_admin')
                    ->orWhereIn('role_slug', $roleSlugs)
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $roleIds))
                    ->orWhereHas('extraPermissions', fn (Builder $grants): Builder => $grants->whereIn('permission', $permissions));
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function flushPermissions(): void
    {
        $this->permissions = [];
    }

    public function canManageTarget(Authenticatable $user, CmsTargetDTO $target, string $action = 'update'): bool
    {
        $component = $this->catalog->componentForTarget($target);

        if ($component === null || ! $this->can($user, $component.'.'.$action)) {
            return false;
        }

        if ($target->facultyScopeSlug !== null && ! $this->withinFacultyLimit($user, $target->facultyScopeSlug)) {
            return false;
        }

        return $this->withinPageLimit($user, $target);
    }

    public function canOpenScreen(Authenticatable $user, string $component, ?string $area = null): bool
    {
        if (! $this->can($user, $component.'.view')) {
            return false;
        }

        if ($area === null || ! $user instanceof User || ! $user->hasPageLimit()) {
            return true;
        }

        foreach ($user->pageScopes() as $targetKey) {
            if ($targetKey === $area || str_starts_with($targetKey, $area.'.')) {
                return true;
            }
        }

        return false;
    }

    public function hasFacultyLimit(Authenticatable $user): bool
    {
        return $user instanceof User && ! $user->isSuperAdmin() && $user->hasFacultyLimit();
    }

    public function withinFacultyLimit(Authenticatable $user, ?string $facultySlug): bool
    {
        if (! $this->hasFacultyLimit($user)) {
            return true;
        }

        return is_string($facultySlug) && trim($facultySlug) !== ''
            && in_array($this->canonicalFacultySlug($facultySlug), $this->limitedFaculties($user), true);
    }

    public function withinPageLimit(Authenticatable $user, CmsTargetDTO $target): bool
    {
        if (! $user instanceof User || $user->isSuperAdmin() || ! $user->hasPageLimit()) {
            return true;
        }

        // Only fixed site pages are limited; faculty pages follow the faculty
        // limit and records (articles, profiles) follow their own permissions.
        $isFixedPage = $target->facultyScopeSlug === null
            && $target->publicPath !== null
            && ! str_starts_with($target->key, 'entity.');

        return ! $isFixedPage || $user->hasPageScope($target->key);
    }

    public function limitedFaculties(Authenticatable $user): array
    {
        if (! $user instanceof User || ! $this->hasFacultyLimit($user)) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (string $slug): string => $this->canonicalFacultySlug($slug),
            $user->facultyScopes(),
        )));
    }

    public function facultyScopeValues(Authenticatable $user): array
    {
        $spellings = $this->facultySpellings();
        $values = [];

        foreach ($this->limitedFaculties($user) as $publicSlug) {
            array_push($values, ...($spellings[$publicSlug] ?? [$publicSlug]));
        }

        return array_values(array_unique($values));
    }

    public function facultyForWrite(Authenticatable $user, ?string $requestedFaculty): ?string
    {
        $requested = is_string($requestedFaculty) && trim($requestedFaculty) !== ''
            ? $this->canonicalFacultySlug($requestedFaculty)
            : null;

        if (! $this->hasFacultyLimit($user)) {
            return $requested;
        }

        $faculties = $this->limitedFaculties($user);

        if ($requested !== null && in_array($requested, $faculties, true)) {
            return $requested;
        }

        return count($faculties) === 1 ? $faculties[0] : null;
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

    /** @return list<string> */
    private function loadPermissions(User $user): array
    {
        $roleIds = Role::query()
            ->where('slug', (string) $user->role_slug)
            ->pluck('id')
            ->merge($user->roles()->pluck('roles.id'))
            ->unique()
            ->all();

        $granted = RolePermission::query()->whereIn('role_id', $roleIds)->pluck('permission')
            ->merge(UserPermission::query()->where('user_id', $user->getKey())->pluck('permission'))
            ->unique()
            ->all();

        // Edit includes publish: whoever may edit a page, faculty or record
        // may also put it live. Their faculty and page limits still apply.
        foreach ($granted as $permission) {
            if (str_ends_with($permission, '.update')) {
                $granted[] = substr($permission, 0, -strlen('update')).'publish';
            }
        }

        // Keys the catalog no longer lists grant nothing.
        return array_values(array_intersect($this->catalog->all(), $granted));
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
