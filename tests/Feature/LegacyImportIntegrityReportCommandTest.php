<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportIntegrityReportCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_integrity_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_integrity_command_testing', $connection);
        config()->set('old_database.integrity_inspection_rules', [
            'links' => [
                'duplicates' => [[
                    'table' => 'jx_sites',
                    'id_column' => 'id',
                    'columns' => ['url'],
                ]],
            ],
        ]);
        DB::purge('legacy_integrity_command_testing');
    }

    public function test_command_reports_integrity_dry_run_without_writing(): void
    {
        $this->createLegacySitesTable();

        $this->artisan('legacy-import:integrity-report', ['module' => 'links'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Integrity Report [links]')
            ->expectsOutputToContain('Status: integrity_blockers_found')
            ->expectsOutputToContain('Blocked rows: 2')
            ->expectsOutputToContain('Dry-run only. Re-run with --record-quarantine to persist integrity review rows.');

        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_command_records_quarantine_with_explicit_flag(): void
    {
        $this->createLegacySitesTable();

        $this->artisan('legacy-import:integrity-report', ['module' => 'links', '--record-quarantine' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Recorded quarantine: yes')
            ->expectsOutputToContain('Recorded rows: 2');

        $this->assertDatabaseCount('migration_rejections', 2);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'reason_code' => 'duplicate_legacy_content',
        ]);
    }

    public function test_command_rejects_unknown_module(): void
    {
        $this->artisan('legacy-import:integrity-report', ['module' => 'missing'])
            ->assertExitCode(2)
            ->expectsOutputToContain('No integrity inspection rules are configured for this module.');
    }

    private function createLegacySitesTable(): void
    {
        Schema::connection('legacy_integrity_command_testing')->create('jx_sites', function ($schema): void {
            $schema->increments('id');
            $schema->string('url')->nullable();
        });

        DB::connection('legacy_integrity_command_testing')->table('jx_sites')->insert([
            ['url' => 'https://spu.edu.sy/same'],
            ['url' => ' https://spu.edu.sy/same '],
        ]);
    }
}
