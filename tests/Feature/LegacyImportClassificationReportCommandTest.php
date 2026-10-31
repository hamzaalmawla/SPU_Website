<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportClassificationReportCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_classification_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_classification_command_testing', $connection);
        config()->set('old_database.classification_rules', [
            'news' => [
                'jx_categories' => [
                    'bucket' => 'canonical_rebuild_now',
                    'rule_key' => 'test_news_rule',
                    'high_risk' => true,
                    'identity_columns' => ['ar_name'],
                    'notes' => 'test rule',
                ],
            ],
        ]);
        DB::purge('legacy_classification_command_testing');
    }

    public function test_command_exports_classification_report(): void
    {
        Storage::fake('local');
        Schema::connection('legacy_classification_command_testing')->create('jx_categories', function ($schema): void {
            $schema->increments('id');
            $schema->string('ar_name')->nullable();
        });
        DB::connection('legacy_classification_command_testing')->table('jx_categories')->insert(['id' => 1, 'ar_name' => 'News AR']);

        $this->artisan('legacy-import:classification-report news')
            ->expectsOutputToContain('Legacy Classification Report')
            ->expectsOutputToContain('Classified rows: 1')
            ->assertSuccessful();
    }

    public function test_command_rejects_unknown_module(): void
    {
        $this->artisan('legacy-import:classification-report missing --json')
            ->assertExitCode(2);
    }
}
