<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportUrlContinuityInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_url_continuity_inventory(): void
    {
        Storage::fake('local');
        MigrationRejection::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => ['legacy_path' => '/index.php?lang=1'],
        ]);

        $this->artisan('legacy-import:url-continuity-inventory links --without-files')
            ->expectsOutputToContain('Legacy URL Continuity Inventory')
            ->expectsOutputToContain('Rows: 1')
            ->assertSuccessful();
    }
}
