<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shared\AuditLog;
use App\Models\User\User;

/**
 * Authorizes audit log viewing actions.
 *
 * Only super_admin can view audit logs (granted via before() hook).
 * All other roles are denied.
 */
final class AuditLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
