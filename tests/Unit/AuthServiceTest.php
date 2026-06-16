<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Auth\AuthServiceInterface;
use App\DTOs\Auth\LoginCredentialsDTO;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies auth service behavior against the current user schema.
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * It tracks failed attempts and locks the account on the fifth failure.
     */
    public function test_auth_service_locks_account_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'editor@example.com',
            'password' => 'password',
            'role_slug' => 'editor',
        ]);

        $authService = app(AuthServiceInterface::class);

        foreach (range(1, 5) as $attempt) {
            $this->assertFalse($authService->attempt(new LoginCredentialsDTO(
                email: 'editor@example.com',
                password: 'wrong-password',
            )));
        }

        $user->refresh();

        $this->assertSame(5, $user->failed_login_attempts);
        $this->assertSame(5, $user->failed_attempts);
        $this->assertTrue($user->is_locked);
        $this->assertNotNull($user->locked_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.locked',
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
        $this->assertSame(5, AuditLog::query()->where('action', 'user.login_failed')->count());
    }

    /**
     * It resets failed-attempt tracking after a successful login.
     */
    public function test_auth_service_resets_failed_attempts_after_successful_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_slug' => 'super_admin',
            'failed_login_attempts' => 2,
        ]);

        $authService = app(AuthServiceInterface::class);

        $this->assertTrue($authService->attempt(new LoginCredentialsDTO(
            email: 'admin@example.com',
            password: 'password',
        )));

        $user->refresh();

        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(0, $user->failed_attempts);
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login',
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_update_user_preserves_omitted_lock_and_faculty_scope_fields(): void
    {
        $actor = User::factory()->create([
            'email' => 'super-admin@example.com',
            'role_slug' => 'super_admin',
        ]);
        $user = User::factory()->create([
            'email' => 'faculty-editor@example.com',
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
            'is_locked' => true,
            'locked_at' => now(),
        ]);

        $updated = app(AuthServiceInterface::class)->updateUser($user->id, [
            'name' => 'Updated Faculty Editor',
        ], $actor->id);

        $this->assertTrue($updated);

        $user->refresh();

        $this->assertSame('Updated Faculty Editor', $user->name);
        $this->assertSame('faculty_editor', $user->role_slug);
        $this->assertSame('medicine', $user->faculty_scope_slug);
        $this->assertTrue($user->is_locked);
        $this->assertNotNull($user->locked_at);
    }
}
