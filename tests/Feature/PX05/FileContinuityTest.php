<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Contracts\ContinuityServiceInterface;
use App\Models\LegacyFileInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for file continuity resolution.
 *
 * Requirements: 18.1, 18.2, 18.3
 */
class FileContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapped_file_resolves_to_current_path(): void
    {
        LegacyFileInventory::create([
            'legacy_path' => '/files/old-document.pdf',
            'current_path' => '/media/new-document.pdf',
            'status' => 'mapped',
        ]);

        $service = app(ContinuityServiceInterface::class);
        $result = $service->resolveFileContinuity('/files/old-document.pdf');

        $this->assertSame('/media/new-document.pdf', $result);
    }

    public function test_unmapped_file_returns_null(): void
    {
        LegacyFileInventory::create([
            'legacy_path' => '/files/unmapped-file.pdf',
            'current_path' => null,
            'status' => 'unmapped',
        ]);

        $service = app(ContinuityServiceInterface::class);
        $result = $service->resolveFileContinuity('/files/unmapped-file.pdf');

        $this->assertNull($result);
    }

    public function test_nonexistent_file_returns_null(): void
    {
        $service = app(ContinuityServiceInterface::class);
        $result = $service->resolveFileContinuity('/files/does-not-exist.pdf');

        $this->assertNull($result);
    }

    public function test_mapped_file_request_redirects_in_runtime(): void
    {
        LegacyFileInventory::create([
            'legacy_path' => '/files/runtime-document.pdf',
            'current_path' => '/media/runtime-document.pdf',
            'status' => 'mapped',
        ]);

        $this->get('/files/runtime-document.pdf')
            ->assertRedirect('/media/runtime-document.pdf')
            ->assertStatus(301);
    }
}
