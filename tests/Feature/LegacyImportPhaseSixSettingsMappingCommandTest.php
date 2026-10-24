<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\LegacyReviewItem;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportPhaseSixSettingsMappingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_config', function ($schema): void {
            $schema->increments('id');
            $schema->string('name');
            $schema->string('label')->nullable();
            $schema->text('value')->nullable();
        });
        Schema::connection('legacy_mysql')->create('jx_config1', function ($schema): void {
            $schema->increments('id');
            $schema->string('name');
            $schema->string('label')->nullable();
            $schema->text('value')->nullable();
        });
    }

    public function test_command_exports_settings_mapping_report(): void
    {
        Storage::fake('local');
        app('db')->connection('legacy_mysql')->table('jx_config')->insert([
            'id' => 1,
            'name' => 'student_gate_link',
            'label' => 'Student Portal',
            'value' => 'https://students.example/login',
        ]);
        LegacyReviewItem::query()->create([
            'module' => 'settings',
            'source_table' => 'jx_config',
            'source_id' => 1,
            'legacy_key' => 'config:1',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'settings',
            'target_type' => 'setting_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'not_applicable',
            'blocked_reasons' => [],
        ]);

        $this->artisan('legacy-import:phase6-settings-mapping')
            ->expectsOutputToContain('Phase 6 Settings Mapping Report')
            ->expectsOutputToContain('Scanned rows: 1')
            ->expectsOutputToContain('Safe mapping rows: 1')
            ->assertSuccessful();
    }
}
