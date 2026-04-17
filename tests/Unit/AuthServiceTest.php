<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\AuthServiceInterface;
use App\Models\User;
use Tests\TestCase;

/**
 * Verifies auth service behavior against the current user schema.
 */
class AuthServiceTest extends TestCase
{
    /**
     * It resolves role checks from the current role_slug foundation field.
     */
    public function test_auth_service_checks_role_from_role_slug(): void
    {
        $user = new User;
        $user->forceFill(['role_slug' => 'editor']);

        $authService = app(AuthServiceInterface::class);

        $this->assertTrue($authService->checkRole($user, 'editor'));
        $this->assertFalse($authService->checkRole($user, 'super_admin'));
    }

    /**
     * It resolves lock status from the current locked_at foundation field.
     */
    public function test_auth_service_checks_lock_status_from_locked_at(): void
    {
        $user = new User;
        $user->forceFill(['locked_at' => now()]);

        $authService = app(AuthServiceInterface::class);

        $this->assertTrue($authService->isLocked($user));

        $user->forceFill(['locked_at' => null]);

        $this->assertFalse($authService->isLocked($user));
    }
}
