<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\Legacy\LegacyFileInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for continuity:export-file-inventory command.
 *
 * Requirements: 27.3
 */
class FileInventoryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_mapped_and_unmapped_files(): void
    {
        Storage::fake('local');

        LegacyFileInventory::create([
            'legacy_path' => '/files/mapped.pdf',
            'current_path' => '/media/mapped.pdf',
            'status' => 'mapped',
        ]);

        LegacyFileInventory::create([
            'legacy_path' => '/files/unmapped.doc',
            'current_path' => null,
            'status' => 'unmapped',
        ]);

        $this->artisan('continuity:export-file-inventory', ['--format' => 'json'])
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('continuity-exports');
        $jsonFile = collect($files)->first(fn (string $f): bool => str_ends_with($f, '.json'));
        $this->assertNotNull($jsonFile);

        $content = json_decode(Storage::disk('local')->get($jsonFile), true);
        $this->assertSame(2, $content['total']);
        $this->assertSame(1, $content['summary']['mapped']);
        $this->assertSame(1, $content['summary']['unmapped']);
    }

    public function test_command_handles_empty_inventory(): void
    {
        Storage::fake('local');

        $this->artisan('continuity:export-file-inventory', ['--format' => 'json'])
            ->assertSuccessful();
    }
}
