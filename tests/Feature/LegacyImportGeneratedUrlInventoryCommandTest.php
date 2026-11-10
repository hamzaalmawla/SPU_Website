<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportGeneratedUrlInventoryCommandTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_generated_url_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_generated_url_command_testing', $connection);
        DB::purge('legacy_generated_url_command_testing');
    }

    public function test_command_exports_generated_url_inventory(): void
    {
        Storage::fake('local');
        Schema::connection('legacy_generated_url_command_testing')->create('jx_site_static_pages', function ($schema): void {
            $schema->increments('id');
            $schema->text('ar_page_data')->nullable();
        });
        DB::connection('legacy_generated_url_command_testing')->table('jx_site_static_pages')->insert([
            'id' => 1,
            'ar_page_data' => 'Legacy page',
        ]);

        $this->artisan('legacy-import:generated-url-inventory jx_site_static_pages')
            ->expectsOutputToContain('Generated Legacy URL Inventory')
            ->expectsOutputToContain('Generated URL rows: 1')
            ->assertSuccessful();
    }
}
