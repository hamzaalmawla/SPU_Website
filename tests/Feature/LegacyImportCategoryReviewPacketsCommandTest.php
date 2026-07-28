<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportCategoryReviewPacketsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config()->set('old_database.connection_name', 'legacy_category_review_command_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_category_review_command_testing', $connection);
        DB::purge('legacy_category_review_command_testing');
        Schema::connection('legacy_category_review_command_testing')->create('jx_categories', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('parent')->nullable();
            $table->integer('service_type');
            $table->integer('category_order')->nullable();
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->integer('is_visible')->nullable();
            $table->integer('is_link')->nullable();
            $table->string('url')->nullable();
            $table->string('photo')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->longText('ar_data')->nullable();
            $table->longText('en_data')->nullable();
        });
        Schema::connection('legacy_category_review_command_testing')->create('jx_items', function ($table): void {
            $table->integer('id')->primary();
            $table->integer('category_id');
            $table->integer('is_visible')->nullable();
            $table->string('photo')->nullable();
            $table->string('ar_file')->nullable();
            $table->string('en_file')->nullable();
        });
    }

    public function test_command_outputs_json_and_succeeds(): void
    {
        Storage::fake('local');
        DB::connection('legacy_category_review_command_testing')->table('jx_categories')->insert([
            'id' => 1, 'parent' => 0, 'service_type' => 4, 'category_order' => 1,
            'ar_name' => 'Announcement', 'en_name' => 'Announcement', 'is_visible' => 1,
            'is_link' => 0, 'url' => null, 'photo' => null, 'start_date' => null,
            'end_date' => null, 'ar_data' => 'body', 'en_data' => null,
        ]);

        $exitCode = Artisan::call('legacy-import:category-review-packets', [
            '--subsite' => ['root'], '--service' => ['4'], '--dir' => 'command-review', '--json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('"selected_services": [', $output);
        $this->assertStringContainsString('"packet_count": 1', $output);
        $this->assertStringContainsString('"announcement_import_review": 1', $output);
        $this->assertCount(3, Storage::disk('local')->allFiles('command-review'));
    }

    public function test_command_returns_invalid_and_writes_nothing_for_unsupported_filter(): void
    {
        Storage::fake('local');

        $this->artisan('legacy-import:category-review-packets --subsite=public --service=3')
            ->expectsOutputToContain('Unsupported subsite [public]')
            ->assertExitCode(2);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
