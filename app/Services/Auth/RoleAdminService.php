<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RoleAdminService implements RoleAdminServiceInterface
{
    public function __construct(
        private readonly PermissionCatalogInterface $catalog,
        private readonly AdminAccessServiceInterface $access,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function roleOptions(): array
    {
        return Role::query()->orderBy('id')->pluck('name', 'slug')->all();
    }

    public function permissionsForRole(int $roleId): array
    {
        $role = Role::query()->findOrFail($roleId);

        if ($role->slug === 'super_admin') {
            return $this->catalog->all();
        }

        return array_values(array_intersect($this->catalog->all(), $role->permissions()->pluck('permission')->all()));
    }

    public function saveRole(?int $roleId, string $name, string $slug, array $permissions, int $actorUserId): int
    {
        $actor = $this->authorizedActor($actorUserId);
        $role = $roleId !== null ? Role::query()->findOrFail($roleId) : new Role;

        if ($role->exists && $role->slug === 'super_admin') {
            throw ValidationException::withMessages(['name' => [__('admin_permissions.role.super_admin_locked')]]);
        }

        $name = trim($name);
        $slug = $role->exists ? (string) $role->slug : trim($slug);
        $permissions = array_values(array_unique(array_filter($permissions, 'is_string')));

        if ($name === '' || preg_match('/^[a-z][a-z0-9_]{1,49}$/', $slug) !== 1) {
            throw ValidationException::withMessages(['slug' => [__('admin_permissions.role.slug_help')]]);
        }

        if (Role::query()->where('slug', $slug)->whereKeyNot($role->getKey())->exists()) {
            throw ValidationException::withMessages(['slug' => [__('admin_permissions.role.slug_taken')]]);
        }

        if (array_diff($permissions, $this->catalog->all()) !== []) {
            throw ValidationException::withMessages(['permissions' => [__('admin_permissions.role.unknown_permission')]]);
        }

        $before = $role->exists ? $role->permissions()->pluck('permission')->all() : [];

        DB::transaction(function () use ($role, $name, $slug, $permissions): void {
            $role->fill(['name' => $name, 'slug' => $slug])->save();
            $role->permissions()->whereNotIn('permission', $permissions)->delete();

            foreach (array_diff($permissions, $role->permissions()->pluck('permission')->all()) as $permission) {
                $role->permissions()->create(['permission' => $permission]);
            }
        });

        $this->access->flushPermissions();
        $this->auditService->log(
            action: $roleId === null ? 'role.created' : 'role.updated',
            userId: $actorUserId,
            entityType: Role::class,
            entityId: (int) $role->getKey(),
            metadata: [
                'actor_email' => $actor->email,
                'role_slug' => $slug,
                'added_permissions' => array_values(array_diff($permissions, $before)),
                'removed_permissions' => array_values(array_diff($before, $permissions)),
            ],
        );

        return (int) $role->getKey();
    }

    public function deleteRole(int $roleId, int $actorUserId): bool
    {
        $actor = $this->authorizedActor($actorUserId);
        $role = Role::query()->findOrFail($roleId);

        if ($role->slug === 'super_admin') {
            throw ValidationException::withMessages(['role' => [__('admin_permissions.role.cannot_delete_system')]]);
        }

        if ($this->usersWithRole($roleId) > 0) {
            throw ValidationException::withMessages(['role' => [__('admin_permissions.role.cannot_delete_in_use')]]);
        }

        $slug = (string) $role->slug;
        $permissions = $role->permissions()->pluck('permission')->all();
        $role->delete();

        $this->access->flushPermissions();
        $this->auditService->log(
            action: 'role.deleted',
            userId: $actorUserId,
            entityType: Role::class,
            entityId: $roleId,
            metadata: ['actor_email' => $actor->email, 'role_slug' => $slug, 'permissions' => $permissions],
        );

        return true;
    }

    public function isSuperAdminRole(int $roleId): bool
    {
        return Role::query()->whereKey($roleId)->where('slug', 'super_admin')->exists();
    }

    public function usersWithRole(int $roleId): int
    {
        $role = Role::query()->find($roleId);

        if (! $role instanceof Role) {
            return 0;
        }

        return User::query()
            ->where(fn ($query) => $query
                ->where('role_slug', $role->slug)
                ->orWhereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->count();
    }

    public function userGrants(int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $slugs = $user->roles()->pluck('slug')->all();

        if (is_string($user->role_slug) && $user->role_slug !== '' && ! in_array($user->role_slug, $slugs, true)) {
            array_unshift($slugs, $user->role_slug);
        }

        return [
            'role_slugs' => array_values($slugs),
            'extra_permissions' => $user->extraPermissions()->pluck('permission')->all(),
        ];
    }

    private function authorizedActor(int $actorUserId): User
    {
        $actor = User::query()->find($actorUserId);

        if (! $actor instanceof User || $actor->isAccountLocked() || Gate::forUser($actor)->denies('manage-users')) {
            throw new AuthorizationException('Only a super admin can manage roles.');
        }

        return $actor;
    }
}
