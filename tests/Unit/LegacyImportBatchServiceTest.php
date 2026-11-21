<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportTableInventoryDTO;
use App\Models\Legacy\LegacyImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyImportBatchServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LegacyImportBatchServiceInterface::class);
    }

    public function test_record_dry_run_creates_batch_summary(): void
    {
        $batch = $this->service->recordDryRun(new LegacyImportDryRunDTO(
            module: 'homepage',
            enabled: true,
            canRun: true,
            sourceTables: collect([
                new LegacyImportTableInventoryDTO('jx_home_photos', true, 3),
            ]),
            targetTables: ['media_assets'],
            estimatedSourceRows: 3,
            status: 'ready_for_dry_run',
        ), 'homepage review');

        $this->assertSame('homepage-review', $batch->batchName);
        $this->assertSame('dry_run_ready', $batch->status);
        $this->assertSame(3, $batch->estimatedSourceRows);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'homepage-review',
            'module' => 'homepage',
            'mode' => 'dry_run',
            'status' => 'dry_run_ready',
            'estimated_source_rows' => 3,
        ]);
    }

    public function test_duplicate_requested_batch_names_are_made_unique(): void
    {
        LegacyImportBatch::query()->create([
            'batch_name' => 'duplicate-batch',
            'module' => 'homepage',
            'mode' => 'dry_run',
            'status' => 'dry_run_ready',
            'estimated_source_rows' => 0,
        ]);

        $batch = $this->service->recordBlockedRun('homepage', 'blocked', 'duplicate batch');

        $this->assertSame('duplicate-batch-2', $batch->batchName);
        $this->assertSame('blocked', $batch->status);
    }

    public function test_record_blocked_run_creates_auditable_batch(): void
    {
        $batch = $this->service->recordBlockedRun('news', 'not approved', 'news-run');

        $this->assertSame('news-run', $batch->batchName);
        $this->assertSame('run', $batch->mode);
        $this->assertSame('blocked', $batch->status);
        $this->assertSame('not approved', $batch->summary['reason'] ?? null);
    }
}
