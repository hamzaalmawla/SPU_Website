<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyClassificationReportServiceTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_classification_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_classification_testing', $connection);
        config()->set('old_database.classification_rules', [
            'news' => [
                'jx_categories' => [
                    'bucket' => 'canonical_rebuild_now',
                    'rule_key' => 'test_news_rule',
                    'high_risk' => true,
                    'identity_columns' => ['ar_name', 'en_name'],
                    'file_columns' => ['photo'],
                    'date_columns' => ['start_date'],
                    'notes' => 'test rule',
                ],
            ],
            'unknowns' => [
                'legacy_unknowns' => [
                    'bucket' => 'not_a_bucket',
                    'rule_key' => 'bad_rule',
                    'identity_columns' => ['name'],
                    'notes' => 'bad bucket quarantines',
                ],
            ],
        ]);
        DB::purge('legacy_classification_testing');
    }

    public function test_export_classifies_rows_and_writes_artifacts(): void
    {
        Storage::fake('local');
        $this->createLegacyTables();
        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 1,
            'reason_code' => 'unsafe_html',
            'reason_message' => 'unsafe',
            'raw_summary' => ['field' => 'ar_data'],
        ]);

        $result = app(LegacyClassificationReportServiceInterface::class)->export(module: 'news');

        $this->assertSame('classification_report_created', $result->status);
        $this->assertSame(1, $result->tableCount);
        $this->assertSame(2, $result->sourceRowCount);
        $this->assertSame(2, $result->classifiedRowCount);
        $this->assertSame(0, $result->unknownRowCount);
        $this->assertSame(1, $result->highRiskTablesCovered);
        $this->assertSame(2, $result->bucketCounts['canonical_rebuild_now']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $mapping = Storage::disk('local')->get($result->paths[2]);
        $this->assertStringContainsString('canonical_rebuild_now', $mapping);
        $this->assertStringContainsString('unsafe_html', $mapping);
        $this->assertStringContainsString('missing_external_source_root', $mapping);
    }

    public function test_invalid_bucket_is_quarantined_not_importable(): void
    {
        Storage::fake('local');
        $this->createLegacyTables();

        $result = app(LegacyClassificationReportServiceInterface::class)->export(module: 'unknowns');

        $this->assertSame(1, $result->unknownRowCount);
        $this->assertSame(1, $result->bucketCounts['quarantine']);
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_classification_testing')->create('jx_categories', function ($schema): void {
            $schema->increments('id');
            $schema->string('ar_name')->nullable();
            $schema->string('en_name')->nullable();
            $schema->string('photo')->nullable();
            $schema->string('start_date')->nullable();
        });

        DB::connection('legacy_classification_testing')->table('jx_categories')->insert([
            ['id' => 1, 'ar_name' => 'News AR', 'en_name' => 'News', 'photo' => '/images/news.jpg', 'start_date' => '2024-01-01'],
            ['id' => 2, 'ar_name' => 'Other AR', 'en_name' => 'Other', 'photo' => null, 'start_date' => null],
        ]);

        Schema::connection('legacy_classification_testing')->create('legacy_unknowns', function ($schema): void {
            $schema->increments('id');
            $schema->string('name')->nullable();
        });

        DB::connection('legacy_classification_testing')->table('legacy_unknowns')->insert([
            ['id' => 1, 'name' => 'Unknown'],
        ]);
    }
}
