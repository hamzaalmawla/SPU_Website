<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Resources\AuditLogResource;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for AuditLogResource Filament resource.
 *
 * Requirements: 25.1–25.3
 */
class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_audit_log_resource(): void
    {
        $this->actingAs($this->createUser('super_admin'));

        $this->assertTrue(AuditLogResource::canAccess());
    }

    public function test_editor_cannot_access_audit_log_resource(): void
    {
        $this->actingAs($this->createUser('editor'));

        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_faculty_editor_cannot_access_audit_log_resource(): void
    {
        $this->actingAs($this->createUser('faculty_editor'));

        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_audit_log_resource(): void
    {
        $this->assertFalse(AuditLogResource::canAccess());
    }

    public function test_audit_log_resource_is_read_only(): void
    {
        $this->assertFalse(AuditLogResource::canCreate());

        $auditLog = new AuditLog;
        $this->assertFalse(AuditLogResource::canEdit($auditLog));
        $this->assertFalse(AuditLogResource::canDelete($auditLog));
    }

    public function test_audit_log_resource_has_list_and_view_pages(): void
    {
        $pages = AuditLogResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayNotHasKey('create', $pages);
        $this->assertArrayNotHasKey('edit', $pages);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
