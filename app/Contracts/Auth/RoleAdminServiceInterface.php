<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

/**
 * Super-admin management of roles (named sets of permissions) and of what a
 * user holds. Every write is audited and refreshes remembered permissions.
 */
interface RoleAdminServiceInterface
{
    /** @return array<string, string> role slug => name */
    public function roleOptions(): array;

    /** @return list<string> */
    public function permissionsForRole(int $roleId): array;

    /**
     * Creates the role when `$roleId` is null, otherwise renames it and
     * replaces its permissions. Returns the role id.
     *
     * @param  list<string>  $permissions
     */
    public function saveRole(?int $roleId, string $name, string $slug, array $permissions, int $actorUserId): int;

    /** Refuses the super admin role and any role still assigned to a user. */
    public function deleteRole(int $roleId, int $actorUserId): bool;

    public function isSuperAdminRole(int $roleId): bool;

    public function usersWithRole(int $roleId): int;

    /**
     * The roles and extra permissions a user holds, for the user form.
     *
     * @return array{role_slugs: list<string>, extra_permissions: list<string>}
     */
    public function userGrants(int $userId): array;
}
