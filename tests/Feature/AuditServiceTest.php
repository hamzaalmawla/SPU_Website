<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\AuditServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the concrete audit service binding persists and returns DTOs.
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It stores audit events and returns them as DTO collections.
     */
    public function test_audit_service_persists_and_reads_entries(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $auditService = app(AuditServiceInterface::class);

        $logged = $auditService->log(
            action: 'auth.login.success',
            userId: $user->id,
            entityType: User::class,
            entityId: $user->id,
            metadata: ['ip' => '127.0.0.1'],
        );

        $this->assertTrue($logged);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login.success',
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);

        $latest = $auditService->latest();

        $this->assertCount(1, $latest);
        $this->assertSame('auth.login.success', $latest->first()?->action);
        $this->assertSame($user->id, $latest->first()?->actorId);
        $this->assertSame(['ip' => '127.0.0.1'], $latest->first()?->context);
    }
}
