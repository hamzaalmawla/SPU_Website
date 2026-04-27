<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\LegacyExactRedirect;
use App\Models\LegacyPatternRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for continuity:export-url-inventory command.
 *
 * Requirements: 27.1
 */
class UrlInventoryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exports_json_with_seeded_data(): void
    {
        Storage::fake('local');

        LegacyExactRedirect::create([
            'legacy_path' => '/old-page',
            'destination_url' => '/ar/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyPatternRule::create([
            'pattern' => '#^/dept/(.+)$#',
            'replacement' => '/ar/departments/$1',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->artisan('continuity:export-url-inventory', ['--format' => 'json'])
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('continuity-exports');
        $this->assertNotEmpty($files, 'Export file must be created');

        $jsonFile = collect($files)->first(fn (string $f): bool => str_ends_with($f, '.json'));
        $this->assertNotNull($jsonFile);

        $content = json_decode(Storage::disk('local')->get($jsonFile), true);
        $this->assertSame(2, $content['total']);
    }

    public function test_command_exports_csv_format(): void
    {
        Storage::fake('local');

        LegacyExactRedirect::create([
            'legacy_path' => '/csv-test',
            'destination_url' => '/ar/csv-dest',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:export-url-inventory', ['--format' => 'csv'])
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('continuity-exports');
        $csvFile = collect($files)->first(fn (string $f): bool => str_ends_with($f, '.csv'));
        $this->assertNotNull($csvFile);
    }

    public function test_command_handles_empty_data(): void
    {
        Storage::fake('local');

        $this->artisan('continuity:export-url-inventory', ['--format' => 'json'])
            ->assertSuccessful();
    }
}
