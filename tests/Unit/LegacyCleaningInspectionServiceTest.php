<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyCleaningInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyCleaningInspectionServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_cleaning_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_cleaning_testing', $connection);
        config()->set('old_database.cleaning_inspection_fields', [
            'pages' => [[
                'table' => 'jx_pages',
                'id_column' => 'id',
                'fields' => [
                    ['column' => 'title', 'type' => 'text', 'required' => true],
                    ['column' => 'body', 'type' => 'html', 'required' => false],
                    ['column' => 'email', 'type' => 'email', 'required' => true],
                    ['column' => 'published_at', 'type' => 'date', 'required' => false],
                    ['column' => 'locale', 'type' => 'locale', 'required' => false],
                ],
            ]],
        ]);
        DB::purge('legacy_cleaning_testing');

        $this->service = app(LegacyCleaningInspectionServiceInterface::class);
    }

    public function test_inspection_is_dry_run_by_default_and_does_not_record_rejections(): void
    {
        $this->createLegacyPagesTable();

        $result = $this->service->inspect('pages');

        $this->assertSame('quarantine_required', $result->status);
        $this->assertFalse($result->recordedQuarantine);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(10, $result->scannedFields);
        $this->assertSame(3, $result->blockedFields);
        $this->assertSame(0, $result->recordedRows);
        $this->assertSame(3, $result->issueCounts['invalid_email'] + $result->issueCounts['unsafe_html'] + $result->issueCounts['unsupported_locale']);
        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_inspection_can_record_quarantine_rows_without_importing_content(): void
    {
        $this->createLegacyPagesTable();

        $result = $this->service->inspect('pages', recordQuarantine: true);

        $this->assertSame('quarantine_required', $result->status);
        $this->assertTrue($result->recordedQuarantine);
        $this->assertSame(3, $result->recordedRows);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'pages',
            'source_table' => 'jx_pages',
            'source_id' => 1,
            'reason_code' => 'unsafe_html',
        ]);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'pages',
            'source_table' => 'jx_pages',
            'source_id' => 1,
            'reason_code' => 'invalid_email',
        ]);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'pages',
            'source_table' => 'jx_pages',
            'source_id' => 1,
            'reason_code' => 'unsupported_locale',
        ]);
    }

    public function test_record_quarantine_is_duplicate_safe(): void
    {
        $this->createLegacyPagesTable();

        $first = $this->service->inspect('pages', recordQuarantine: true);
        $second = $this->service->inspect('pages', recordQuarantine: true);

        $this->assertSame(3, $first->recordedRows);
        $this->assertSame(0, $second->recordedRows);
        $this->assertDatabaseCount('migration_rejections', 3);
    }

    public function test_unknown_module_reports_unconfigured_status(): void
    {
        $result = $this->service->inspect('missing');

        $this->assertSame('unknown_or_unconfigured_module', $result->status);
        $this->assertSame(0, $result->scannedRows);
        $this->assertSame(['No cleaning inspection fields are configured for this module.'], $result->warnings);
    }

    private function createLegacyPagesTable(): void
    {
        Schema::connection('legacy_cleaning_testing')->create('jx_pages', function ($schema): void {
            $schema->increments('id');
            $schema->string('title')->nullable();
            $schema->text('body')->nullable();
            $schema->string('email')->nullable();
            $schema->string('published_at')->nullable();
            $schema->string('locale')->nullable();
        });

        DB::connection('legacy_cleaning_testing')->table('jx_pages')->insert([
            [
                'title' => "  Useful\u{200B} page  ",
                'body' => '<p>Keep <a href="javascript:alert(1)">this</a></p>',
                'email' => 'not-an-email',
                'published_at' => '0000-00-00',
                'locale' => 'fr',
            ],
            [
                'title' => 'Clean page',
                'body' => '<p>Clean</p>',
                'email' => 'INFO@SPU.EDU.SY',
                'published_at' => '2024-01-01',
                'locale' => 'en',
            ],
        ]);
    }
}
