<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shared\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_audit_logs_older_than_retention_days(): void
    {
        config(['audit.retention_days' => 90]);

        $old = AuditLog::query()->create(['action' => 'old.event']);
        $recent = AuditLog::query()->create(['action' => 'recent.event']);

        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        $recent->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('audit:prune')
            ->expectsOutput('Pruned 1 audit log records older than 90 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }

    public function test_prune_can_be_disabled_with_zero_retention(): void
    {
        config(['audit.retention_days' => 0]);

        $old = AuditLog::query()->create(['action' => 'old.event']);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $this->artisan('audit:prune')
            ->expectsOutput('Audit log pruning is disabled because audit.retention_days is 0.')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $old->id]);
    }
}
