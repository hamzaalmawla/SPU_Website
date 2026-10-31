<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportFileInventoryCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_file_inventory_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_file_inventory_command_testing', $connection);
        Storage::fake('local');
        config()->set('old_database.file_inventory_roots', [Storage::disk('local')->path('legacy-root')]);
        config()->set('old_database.file_inventory_fields', [
            ['table' => 'jx_docs', 'id_column' => 'id', 'columns' => ['file']],
        ]);
        DB::purge('legacy_file_inventory_command_testing');
    }

    public function test_command_is_dry_run_without_write_flag(): void
    {
        $this->createLegacyDocsTable();

        $this->artisan('legacy-import:file-inventory')
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy File Inventory Dry Run')
            ->expectsOutputToContain('Scanned references: 1')
            ->expectsOutputToContain('Dry-run only. Re-run with --write to persist inventory rows.');

        $this->assertDatabaseCount('legacy_file_inventory', 0);
    }

    public function test_command_writes_with_explicit_write_flag(): void
    {
        $this->createLegacyDocsTable();
        Storage::disk('local')->put('legacy-root/downloads/files/calendar.pdf', 'calendar-content');

        $this->artisan('legacy-import:file-inventory', ['--write' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy File Inventory Write')
            ->expectsOutputToContain('Written rows: 1')
            ->expectsOutputToContain('Existing files: 1')
            ->expectsOutputToContain('Missing files: 0');

        $this->assertDatabaseHas('legacy_file_inventory', [
            'legacy_path' => '/downloads/files/calendar.pdf',
            'source_table' => 'jx_docs',
            'source_column' => 'file',
            'source_id' => 1,
            'status' => 'unmapped',
            'checksum_sha256' => null,
            'checksum_status' => 'pending',
        ]);
    }

    public function test_command_refuses_write_when_source_root_is_unavailable(): void
    {
        $this->createLegacyDocsTable();
        config()->set('old_database.file_inventory_roots', [Storage::disk('local')->path('unmounted-root')]);

        $this->artisan('legacy-import:file-inventory', ['--write' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('Cannot write legacy file inventory evidence without a readable OLD_PUBLIC_ROOT.');

        $this->assertDatabaseCount('legacy_file_inventory', 0);
    }

    private function createLegacyDocsTable(): void
    {
        Schema::connection('legacy_file_inventory_command_testing')->create('jx_docs', function ($schema): void {
            $schema->increments('id');
            $schema->string('file')->nullable();
        });

        DB::connection('legacy_file_inventory_command_testing')->table('jx_docs')->insert([
            ['file' => 'downloads/files/calendar.pdf'],
        ]);
    }
}
