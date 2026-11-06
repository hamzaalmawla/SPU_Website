<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyIntegrityInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyIntegrityInspectionServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_integrity_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_integrity_testing', $connection);
        config()->set('old_database.integrity_inspection_rules', [
            'news' => [
                'orphans' => [[
                    'child_table' => 'jx_items',
                    'child_id_column' => 'id',
                    'child_parent_column' => 'category_id',
                    'parent_table' => 'jx_categories',
                    'parent_id_column' => 'id',
                ]],
                'duplicates' => [[
                    'table' => 'jx_categories',
                    'id_column' => 'id',
                    'columns' => ['service_type', 'ar_title'],
                ]],
            ],
        ]);
        DB::purge('legacy_integrity_testing');

        $this->service = app(LegacyIntegrityInspectionServiceInterface::class);
    }

    public function test_integrity_report_detects_orphans_and_duplicates_without_writing_by_default(): void
    {
        $this->createLegacyNewsTables();

        $result = $this->service->inspect('news');

        $this->assertSame('integrity_blockers_found', $result->status);
        $this->assertFalse($result->recordedQuarantine);
        $this->assertSame(2, $result->scannedRules);
        $this->assertSame(1, $result->duplicateGroups);
        $this->assertSame(2, $result->duplicateRows);
        $this->assertSame(1, $result->orphanRows);
        $this->assertSame(3, $result->blockedRows);
        $this->assertSame(0, $result->recordedRows);
        $this->assertSame(1, $result->issueCounts['orphaned_child']);
        $this->assertSame(2, $result->issueCounts['duplicate_legacy_content']);
        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_integrity_report_can_record_quarantine_rows_duplicate_safely(): void
    {
        $this->createLegacyNewsTables();

        $first = $this->service->inspect('news', recordQuarantine: true);
        $second = $this->service->inspect('news', recordQuarantine: true);

        $this->assertSame(3, $first->recordedRows);
        $this->assertSame(0, $second->recordedRows);
        $this->assertDatabaseCount('migration_rejections', 3);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'news',
            'source_table' => 'jx_items',
            'source_id' => 10,
            'reason_code' => 'orphaned_child',
        ]);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 1,
            'reason_code' => 'duplicate_legacy_content',
        ]);
    }

    public function test_integrity_report_passes_when_no_blockers_exist(): void
    {
        $this->createCleanLegacyNewsTables();

        $result = $this->service->inspect('news');

        $this->assertSame('integrity_passed', $result->status);
        $this->assertSame(0, $result->blockedRows);
        $this->assertSame([], $result->issueCounts);
    }

    public function test_duplicate_rules_can_ignore_placeholder_values(): void
    {
        config()->set('old_database.integrity_inspection_rules.news.duplicates', [[
            'table' => 'jx_categories',
            'id_column' => 'id',
            'columns' => ['service_type', 'ar_title'],
            'ignored_values' => ['Under Construction'],
        ]]);
        $this->createNewsSchema();

        DB::connection('legacy_integrity_testing')->table('jx_categories')->insert([
            ['id' => 1, 'service_type' => 3, 'ar_title' => 'Under Construction'],
            ['id' => 2, 'service_type' => 3, 'ar_title' => ' under construction '],
            ['id' => 3, 'service_type' => 3, 'ar_title' => 'خبر مكرر'],
            ['id' => 4, 'service_type' => 3, 'ar_title' => 'خبر مكرر'],
        ]);

        $result = $this->service->inspect('news');

        $this->assertSame(1, $result->duplicateGroups);
        $this->assertSame(2, $result->duplicateRows);
    }

    public function test_unknown_module_reports_unconfigured_status(): void
    {
        $result = $this->service->inspect('missing');

        $this->assertSame('unknown_or_unconfigured_module', $result->status);
        $this->assertSame(['No integrity inspection rules are configured for this module.'], $result->warnings);
    }

    private function createLegacyNewsTables(): void
    {
        $this->createNewsSchema();

        DB::connection('legacy_integrity_testing')->table('jx_categories')->insert([
            ['id' => 1, 'service_type' => 3, 'ar_title' => 'خبر مكرر'],
            ['id' => 2, 'service_type' => 3, 'ar_title' => ' خبر مكرر '],
        ]);

        DB::connection('legacy_integrity_testing')->table('jx_items')->insert([
            ['id' => 10, 'category_id' => 999, 'ar_title' => 'Orphan'],
            ['id' => 11, 'category_id' => 1, 'ar_title' => 'Child'],
        ]);
    }

    private function createCleanLegacyNewsTables(): void
    {
        $this->createNewsSchema();

        DB::connection('legacy_integrity_testing')->table('jx_categories')->insert([
            ['id' => 1, 'service_type' => 3, 'ar_title' => 'خبر 1'],
            ['id' => 2, 'service_type' => 4, 'ar_title' => 'خبر 2'],
        ]);

        DB::connection('legacy_integrity_testing')->table('jx_items')->insert([
            ['id' => 10, 'category_id' => 1, 'ar_title' => 'Child'],
        ]);
    }

    private function createNewsSchema(): void
    {
        Schema::connection('legacy_integrity_testing')->create('jx_categories', function ($schema): void {
            $schema->increments('id');
            $schema->integer('service_type')->nullable();
            $schema->string('ar_title')->nullable();
        });

        Schema::connection('legacy_integrity_testing')->create('jx_items', function ($schema): void {
            $schema->increments('id');
            $schema->integer('category_id')->nullable();
            $schema->string('ar_title')->nullable();
        });
    }
}
