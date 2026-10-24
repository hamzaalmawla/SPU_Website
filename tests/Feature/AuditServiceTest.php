<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Shared\AuditServiceInterface;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\User\User;
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

    /**
     * latestPaginated returns a PaginatedResultDTO with correct pagination metadata.
     */
    public function test_latest_paginated_returns_paginated_result_dto(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $auditService = app(AuditServiceInterface::class);

        for ($i = 0; $i < 5; $i++) {
            $auditService->log(
                action: "test.action.{$i}",
                userId: $user->id,
            );
        }

        $result = $auditService->latestPaginated(page: 1, perPage: 3);

        $this->assertInstanceOf(PaginatedResultDTO::class, $result);
        $this->assertCount(3, $result->items);
        $this->assertSame(5, $result->total);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(3, $result->perPage);
        $this->assertSame(2, $result->lastPage);
    }

    /**
     * latestPaginated second page returns remaining items.
     */
    public function test_latest_paginated_second_page(): void
    {
        $user = User::factory()->create(['role_slug' => 'super_admin']);
        $auditService = app(AuditServiceInterface::class);

        for ($i = 0; $i < 5; $i++) {
            $auditService->log(
                action: "test.action.{$i}",
                userId: $user->id,
            );
        }

        $result = $auditService->latestPaginated(page: 2, perPage: 3);

        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->currentPage);
        $this->assertSame(5, $result->total);
    }

    /**
     * latestPaginated with no records returns empty result.
     */
    public function test_latest_paginated_empty_result(): void
    {
        $auditService = app(AuditServiceInterface::class);

        $result = $auditService->latestPaginated(page: 1, perPage: 10);

        $this->assertCount(0, $result->items);
        $this->assertSame(0, $result->total);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(1, $result->lastPage);
    }
}
