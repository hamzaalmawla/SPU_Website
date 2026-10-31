<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\Models\Legacy\LegacyReviewItem;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyPhaseSixSettingsMappingServiceTest extends TestCase
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

        foreach (['jx_config', 'jx_config1'] as $table) {
            Schema::connection('legacy_mysql')->create($table, function ($schema): void {
                $schema->increments('id');
                $schema->string('name');
                $schema->string('label')->nullable();
                $schema->text('value')->nullable();
            });
        }
    }

    public function test_settings_mapping_report_splits_safe_duplicate_unsafe_and_backlog_rows(): void
    {
        Storage::fake('local');
        $this->insertLegacySetting('jx_config', 1, 'facebook_link', 'Facebook', 'https://facebook.example/spu');
        $this->insertLegacySetting('jx_config', 2, 'twitter_link', 'Twitter', 'https://twitter.example/old');
        $this->insertLegacySetting('jx_config1', 3, 'twitter_link', 'Twitter', 'https://twitter.example/new');
        $this->insertLegacySetting('jx_config', 4, 'instagram_link', 'Instagram', 'javascript:alert(1)');
        $this->insertLegacySetting('jx_config', 5, 'mailer_pause', 'Mailer Pause', '1');

        foreach ([
            ['jx_config', 1],
            ['jx_config', 2],
            ['jx_config1', 3],
            ['jx_config', 4],
            ['jx_config', 5],
        ] as [$table, $id]) {
            $this->createReviewItem($table, $id);
        }

        $result = app(LegacyPhaseSixSettingsMappingServiceInterface::class)->export();

        $this->assertSame(5, $result->scannedRows);
        $this->assertSame(1, $result->safeMappingRows);
        $this->assertSame(1, $result->backlogRows);
        $this->assertSame(2, $result->duplicateConflictRows);
        $this->assertSame(1, $result->unsafeValueRows);
        $this->assertSame(1, $result->statusCounts['safe_mapping']);
        $this->assertSame(2, $result->statusCounts['duplicate_conflict']);
        $this->assertSame(1, $result->statusCounts['unsafe_value']);
        $this->assertSame(1, $result->statusCounts['unmapped_setting_backlog']);
        $this->assertSame(1, $result->targetCounts['footer.social_contact']);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    private function insertLegacySetting(string $table, int $id, string $name, string $label, string $value): void
    {
        app('db')->connection('legacy_mysql')->table($table)->insert([
            'id' => $id,
            'name' => $name,
            'label' => $label,
            'value' => $value,
        ]);
    }

    private function createReviewItem(string $table, int $id): void
    {
        LegacyReviewItem::query()->create([
            'module' => 'settings',
            'source_table' => $table,
            'source_id' => $id,
            'legacy_key' => $table.':'.$id,
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
    }
}
