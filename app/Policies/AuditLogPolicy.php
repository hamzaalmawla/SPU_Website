<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Shared\AuditLog;
use App\Models\User\User;

/**
 * Audit entries are read-only; reading them needs `audit_log.view`.
 */
final class AuditLogPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'audit_log.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->access->can($user, 'audit_log.view');
    }
}
