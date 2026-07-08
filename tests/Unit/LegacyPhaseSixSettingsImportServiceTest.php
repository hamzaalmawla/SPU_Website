<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Settings\Setting;
use App\Models\Shared\MigrationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPhaseSixSettingsImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_collapses_duplicate_safe_rows_without_writing_settings(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('legacy-import-exports/phase6-settings/test_safe_mappings.csv', $this->safeMappingsCsv());

        $result = app(LegacyPhaseSixSettingsImportServiceInterface::class)->import(
            inputPath: 'legacy-import-exports/phase6-settings/test_safe_mappings.csv',
            batch: 'settings-test',
        );

        $this->assertFalse($result->written);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(1, $result->duplicateCollapsedRows);
        $this->assertSame(0, Setting::query()->count());
    }

    public function test_write_requires_approval_token(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('legacy-import-exports/phase6-settings/test_safe_mappings.csv', $this->safeMappingsCsv());

        $this->expectException(InvalidArgumentException::class);

        app(LegacyPhaseSixSettingsImportServiceInterface::class)->import(
            inputPath: 'legacy-import-exports/phase6-settings/test_safe_mappings.csv',
            write: true,
            approval: 'wrong',
        );
    }

    public function test_write_imports_social_setting_and_logs_duplicate_sources(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('legacy-import-exports/phase6-settings/test_safe_mappings.csv', $this->safeMappingsCsv());
        $this->createMappingRows();
        Cache::put('settings.public.ar', 'stale');
        Cache::put('settings.social_contact.ar', 'stale');
        Cache::put('settings.student_portal_url', 'stale');

        $result = app(LegacyPhaseSixSettingsImportServiceInterface::class)->import(
            inputPath: 'legacy-import-exports/phase6-settings/test_safe_mappings.csv',
            write: true,
            approval: 'phase6-settings',
            batch: 'settings-test',
        );

        $this->assertTrue($result->written);
        $this->assertSame(1, $result->importedRows);
        $this->assertSame(2, Setting::query()->where('group_key', 'footer')->where('key', 'social_contact')->count());
        $this->assertSame(2, MigrationLog::query()->where('module', 'settings')->where('status', 'success')->count());
        $this->assertSame(2, LegacyReviewItem::query()->where('mapping_status', 'approved')->count());
        $this->assertSame('settings', LegacyContentMapping::query()->value('target_table'));
        $this->assertFalse(Cache::has('settings.public.ar'));
        $this->assertFalse(Cache::has('settings.social_contact.ar'));
        $this->assertFalse(Cache::has('settings.student_portal_url'));
    }

    private function safeMappingsCsv(): string
    {
        return implode("\n", [
            'mapping_status_detail,reason,source_table,source_id,legacy_name,legacy_label,legacy_value,normalized_value,target_group,target_key,target_locale,value_shape,review_status,source_mapping_status,classification,file_dependency,blocked_reasons',
            'safe_mapping,"ok",jx_config,49,facebook_link,"Facebook",https://facebook.example,https://facebook.example,footer,social_contact,ar|en,social_link:facebook,review_candidate,proposed,archive_now_remodel_later,none,',
            'safe_mapping,"ok",jx_config1,49,facebook_link,"Facebook",https://facebook.example,https://facebook.example,footer,social_contact,ar|en,social_link:facebook,review_candidate,proposed,archive_now_remodel_later,none,',
        ])."\n";
    }

    private function createMappingRows(): void
    {
        foreach ([['jx_config', 49], ['jx_config1', 49]] as [$table, $id]) {
            LegacyContentMapping::query()->create([
                'module' => 'settings',
                'source_table' => $table,
                'source_id' => $id,
                'legacy_key' => $table.':'.$id,
                'classification' => 'archive_now_remodel_later',
                'mapping_status' => 'proposed',
                'target_module' => 'settings',
                'target_type' => 'setting_candidate',
                'confidence' => 'medium',
                'file_dependency' => 'none',
                'phase3_reasons' => [],
            ]);
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
}
