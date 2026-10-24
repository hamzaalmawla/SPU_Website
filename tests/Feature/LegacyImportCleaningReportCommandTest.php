<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportCleaningReportCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_cleaning_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_cleaning_command_testing', $connection);
        config()->set('old_database.cleaning_inspection_fields', [
            'links' => [[
                'table' => 'jx_sites',
                'id_column' => 'id',
                'fields' => [
                    ['column' => 'url', 'type' => 'url', 'required' => true],
                    ['column' => 'label', 'type' => 'text', 'required' => true],
                ],
            ]],
        ]);
        DB::purge('legacy_cleaning_command_testing');
    }

    public function test_command_reports_cleaning_dry_run_without_writing(): void
    {
        $this->createLegacySitesTable();

        $this->artisan('legacy-import:cleaning-report', ['module' => 'links'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Cleaning Report [links]')
            ->expectsOutputToContain('Status: quarantine_required')
            ->expectsOutputToContain('Blocked fields: 1')
            ->expectsOutputToContain('Dry-run only. Re-run with --record-quarantine to persist review rows.');

        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_command_records_quarantine_with_explicit_flag(): void
    {
        $this->createLegacySitesTable();

        $this->artisan('legacy-import:cleaning-report', ['module' => 'links', '--record-quarantine' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Recorded quarantine: yes')
            ->expectsOutputToContain('Recorded rows: 1');

        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'reason_code' => 'unsafe_url',
        ]);
    }

    public function test_command_rejects_unknown_module(): void
    {
        $this->artisan('legacy-import:cleaning-report', ['module' => 'missing'])
            ->assertExitCode(2)
            ->expectsOutputToContain('No cleaning inspection fields are configured for this module.');
    }

    private function createLegacySitesTable(): void
    {
        Schema::connection('legacy_cleaning_command_testing')->create('jx_sites', function ($schema): void {
            $schema->increments('id');
            $schema->string('url')->nullable();
            $schema->string('label')->nullable();
        });

        DB::connection('legacy_cleaning_command_testing')->table('jx_sites')->insert([
            ['url' => 'javascript:alert(1)', 'label' => 'Unsafe'],
        ]);
    }
}
