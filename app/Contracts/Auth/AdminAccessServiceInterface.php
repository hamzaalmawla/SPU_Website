<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\DTOs\Cms\CmsTargetDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The one place that answers "what may this admin do?".
 *
 * Permissions (`<component>.<action>`, see PermissionCatalogInterface) come
 * from the user's roles plus extra permissions given to the user alone. The
 * super admin role holds every permission.
 *
 * On top of permissions a user may carry two optional limits: a faculty
 * limit (faculty content only of the listed faculties) and a page limit
 * (multi-page CMS screens only for the listed pages). No limit means no
 * restriction beyond the permissions.
 */
interface AdminAccessServiceInterface
{
    public function isSuperAdmin(Authenticatable $user): bool;

    public function can(Authenticatable $user, string $permission): bool;

    /** @param list<string> $permissions */
    public function canAny(Authenticatable $user, array $permissions): bool;

    /** @return list<string> */
    public function permissionsFor(Authenticatable $user): array;

    /** Whether the user may sign in to the admin panel at all. */
    public function hasAdminAccess(Authenticatable $user): bool;

    /**
     * Unlocked users holding a permission (super admins included), for
     * assignee lists and notifications.
     *
     * @return list<int>
     */
    public function userIdsWithPermission(string $permission): array;

    /** Forget remembered permissions after roles or grants change. */
    public function flushPermissions(): void;

    /**
     * Whether the user may do `$action` (view, update, publish) on this CMS
     * page: the owning component's permission, then the faculty and page
     * limits.
     */
    public function canManageTarget(Authenticatable $user, CmsTargetDTO $target, string $action = 'update'): bool;

    /**
     * Whether a CMS screen opens: its view permission and, for a user with a
     * page limit, at least one allowed page in the screen's area.
     */
    public function canOpenScreen(Authenticatable $user, string $component, ?string $area = null): bool;

    public function hasFacultyLimit(Authenticatable $user): bool;

    /**
     * Whether content owned by this faculty is inside the user's faculty
     * limit. Accepts any slug spelling. Without a limit everything is inside;
     * with one, content owned by no faculty is outside.
     */
    public function withinFacultyLimit(Authenticatable $user, ?string $facultySlug): bool;

    public function withinPageLimit(Authenticatable $user, CmsTargetDTO $target): bool;

    /**
     * The limit's faculties as public slugs; empty when there is no limit.
     *
     * @return list<string>
     */
    public function limitedFaculties(Authenticatable $user): array;

    /**
     * Every slug spelling of the limit's faculties, for filtering records that
     * may store a faculty's public, internal or scope slug.
     *
     * @return list<string>
     */
    public function facultyScopeValues(Authenticatable $user): array;

    /**
     * The faculty a record is saved under. With a faculty limit: the requested
     * faculty when inside it, otherwise the limit's only faculty, otherwise
     * null (the write must be refused). Without a limit: the requested faculty.
     */
    public function facultyForWrite(Authenticatable $user, ?string $requestedFaculty): ?string;

    public function canonicalFacultySlug(string $facultySlug): string;

    /**
     * Faculties a super admin can choose, keyed by public slug.
     *
     * @return array<string, string>
     */
    public function facultyOptions(): array;
}
