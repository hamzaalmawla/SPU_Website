<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportInternalLinksReportCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_internal_links_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_internal_links_command_testing', $connection);
        config()->set('old_database.internal_link_extraction_fields', [
            'pages' => [[
                'table' => 'jx_pages',
                'id_column' => 'id',
                'columns' => ['body'],
            ]],
        ]);
        DB::purge('legacy_internal_links_command_testing');
    }

    public function test_command_reports_internal_links_without_writing(): void
    {
        $this->createLegacyPagesTable();

        $this->artisan('legacy-import:internal-links-report', ['module' => 'pages'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Legacy Internal Links Report [pages]')
            ->expectsOutputToContain('Status: internal_links_found')
            ->expectsOutputToContain('Extracted links: 1')
            ->expectsOutputToContain('Dry-run only. Re-run with --record-review to persist internal link review rows.');

        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_command_records_review_rows_with_explicit_flag(): void
    {
        $this->createLegacyPagesTable();

        $this->artisan('legacy-import:internal-links-report', ['module' => 'pages', '--record-review' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Recorded review rows: yes')
            ->expectsOutputToContain('Recorded rows: 1');

        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'pages',
            'source_table' => 'jx_pages',
            'source_id' => 1,
            'reason_code' => 'legacy_internal_link',
        ]);
    }

    public function test_command_rejects_unknown_module(): void
    {
        $this->artisan('legacy-import:internal-links-report', ['module' => 'missing'])
            ->assertExitCode(2)
            ->expectsOutputToContain('No internal link extraction fields are configured for this module.');
    }

    private function createLegacyPagesTable(): void
    {
        Schema::connection('legacy_internal_links_command_testing')->create('jx_pages', function ($schema): void {
            $schema->increments('id');
            $schema->text('body')->nullable();
        });

        DB::connection('legacy_internal_links_command_testing')->table('jx_pages')->insert([
            ['body' => '<a href="index.php?page=show&dir=news&service=3&cat_id=10&lang=1">News</a>'],
        ]);
    }
}
