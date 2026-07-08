<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyImportInspectionServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_testing', $connection);
        DB::purge('legacy_testing');

        $this->service = app(LegacyImportInspectionServiceInterface::class);
    }

    public function test_inventory_reports_disabled_configured_modules_without_importing(): void
    {
        $inventory = $this->service->inventory('homepage');
        $result = $inventory->first();

        $this->assertNotNull($result);
        $this->assertSame('homepage', $result->module);
        $this->assertFalse($result->enabled);
        $this->assertFalse($result->canRun);
        $this->assertSame('disabled', $result->status);
        $this->assertContains('Module is disabled in config/old_database.php and cannot run.', $result->warnings);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_dry_run_counts_source_rows_when_module_enabled(): void
    {
        config()->set('old_database.modules.homepage.enabled', true);
        $this->createLegacyTable('jx_home_photos', 3);
        $this->createLegacyTable('jx_logos', 2);

        $result = $this->service->dryRun('homepage');

        $this->assertTrue($result->enabled);
        $this->assertTrue($result->canRun);
        $this->assertSame('ready_for_dry_run', $result->status);
        $this->assertSame(5, $result->estimatedSourceRows);
        $this->assertSame(['jx_home_photos', 'jx_logos'], $result->sourceTables->pluck('table')->all());
        $this->assertSame([3, 2], $result->sourceTables->pluck('rowCount')->all());
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_dry_run_blocks_enabled_module_with_missing_source_table(): void
    {
        config()->set('old_database.modules.links.enabled', true);
        $this->createLegacyTable('jx_docs', 1);

        $result = $this->service->dryRun('links');

        $this->assertTrue($result->enabled);
        $this->assertFalse($result->canRun);
        $this->assertSame('missing_sources', $result->status);
        $this->assertContains('Source table [jx_sites] is unavailable.', $result->warnings);
    }

    public function test_dry_run_unknown_module_is_invalid_summary(): void
    {
        $result = $this->service->dryRun('unknown-module');

        $this->assertSame('unknown-module', $result->module);
        $this->assertFalse($result->enabled);
        $this->assertFalse($result->canRun);
        $this->assertSame('unknown_module', $result->status);
    }

    private function createLegacyTable(string $table, int $rows): void
    {
        Schema::connection('legacy_testing')->create($table, function ($schema): void {
            $schema->increments('id');
            $schema->string('name')->nullable();
        });

        for ($i = 0; $i < $rows; $i++) {
            DB::connection('legacy_testing')->table($table)->insert(['name' => 'row-'.$i]);
        }
    }
}
