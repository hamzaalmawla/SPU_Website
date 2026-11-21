<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportPhaseSixSettingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_settings_import_dry_run(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('legacy-import-exports/phase6-settings/test_safe_mappings.csv', implode("\n", [
            'mapping_status_detail,reason,source_table,source_id,legacy_name,legacy_label,legacy_value,normalized_value,target_group,target_key,target_locale,value_shape,review_status,source_mapping_status,classification,file_dependency,blocked_reasons',
            'safe_mapping,"ok",jx_config,227,student_gate_link,"Student Portal",https://students.example,https://students.example,navigation,student_portal_url,,text_url,review_candidate,proposed,archive_now_remodel_later,none,',
        ])."\n");

        $this->artisan('legacy-import:phase6-settings --input=legacy-import-exports/phase6-settings/test_safe_mappings.csv')
            ->expectsOutputToContain('Phase 6 Settings Import')
            ->expectsOutputToContain('Written: no')
            ->expectsOutputToContain('Importable rows: 1')
            ->assertSuccessful();
    }
}
