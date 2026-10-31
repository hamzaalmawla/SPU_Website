<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportCategoryMatrixCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config()->set('old_database.connection_name', 'legacy_category_matrix_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_category_matrix_command_testing', $connection);
        DB::purge('legacy_category_matrix_command_testing');
    }

    public function test_command_exports_category_matrix_to_requested_directory(): void
    {
        Storage::fake('local');
        Schema::connection('legacy_category_matrix_command_testing')->create('jx_categories', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('service_type')->nullable();
        });
        DB::connection('legacy_category_matrix_command_testing')->table('jx_categories')->insert(['id' => 1, 'service_type' => 1]);

        $this->artisan('legacy-import:category-matrix --dir=audits/categories')
            ->expectsOutputToContain('Legacy jx_categories Matrix Export')
            ->expectsOutputToContain('Source rows: 1')
            ->expectsOutputToContain('Output rows: 1')
            ->assertSuccessful();

        $files = Storage::disk('local')->files('audits/categories');
        $this->assertCount(2, $files);
        $this->assertTrue(collect($files)->contains(static fn (string $path): bool => str_ends_with($path, '.csv')));
        $this->assertTrue(collect($files)->contains(static fn (string $path): bool => str_ends_with($path, '.json')));
    }
}
