<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportInspectionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_testing_commands');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_testing_commands', $connection);
        DB::purge('legacy_testing_commands');
    }

    public function test_inventory_command_outputs_configured_module(): void
    {
        $this->artisan('legacy-import:inventory', ['module' => 'homepage'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Import Inventory')
            ->expectsOutputToContain('homepage');
    }

    public function test_dry_run_command_reports_disabled_module_without_writes(): void
    {
        $this->artisan('legacy-import:dry-run', ['module' => 'homepage'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Import Dry Run [homepage]')
            ->expectsOutputToContain('Module is disabled in config/old_database.php and cannot run.')
            ->expectsOutputToContain('Can run: no');

        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_dry_run_command_counts_enabled_module_sources(): void
    {
        config()->set('old_database.modules.homepage.enabled', true);
        $this->createLegacyTable('jx_home_photos', 2);
        $this->createLegacyTable('jx_logos', 1);

        $this->artisan('legacy-import:dry-run', ['module' => 'homepage'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Status: ready_for_dry_run')
            ->expectsOutputToContain('Can run: yes')
            ->expectsOutputToContain('Estimated source rows: 3');

        $this->assertDatabaseCount('migration_logs', 0);
        $this->assertDatabaseHas('legacy_import_batches', [
            'module' => 'homepage',
            'mode' => 'dry_run',
            'status' => 'dry_run_ready',
            'estimated_source_rows' => 3,
        ]);
    }

    public function test_dry_run_command_rejects_unknown_module(): void
    {
        $this->artisan('legacy-import:dry-run', ['module' => 'unknown-module'])
            ->assertExitCode(2)
            ->expectsOutputToContain('Legacy import module is not configured.');
    }

    public function test_run_command_records_blocked_attempt_without_importing(): void
    {
        config()->set('old_database.modules.homepage.enabled', true);
        $this->createLegacyTable('jx_home_photos', 2);
        $this->createLegacyTable('jx_logos', 1);

        $this->artisan('legacy-import:run', ['module' => 'homepage', '--batch' => 'homepage real run'])
            ->assertExitCode(1)
            ->expectsOutputToContain('No controlled legacy import runner is registered for this module.')
            ->expectsOutputToContain('Blocked run batch recorded: homepage-real-run');

        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'homepage-real-run',
            'module' => 'homepage',
            'mode' => 'run',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_run_command_dry_run_records_dry_run_batch(): void
    {
        $this->artisan('legacy-import:run', [
            'module' => 'homepage',
            '--dry-run' => true,
            '--batch' => 'homepage dry run command',
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry-run batch recorded: homepage-dry-run-command');

        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'homepage-dry-run-command',
            'module' => 'homepage',
            'mode' => 'dry_run',
            'status' => 'dry_run_blocked',
        ]);
    }

    private function createLegacyTable(string $table, int $rows): void
    {
        Schema::connection('legacy_testing_commands')->create($table, function ($schema): void {
            $schema->increments('id');
            $schema->string('name')->nullable();
        });

        for ($i = 0; $i < $rows; $i++) {
            DB::connection('legacy_testing_commands')->table($table)->insert(['name' => 'row-'.$i]);
        }
    }
}
