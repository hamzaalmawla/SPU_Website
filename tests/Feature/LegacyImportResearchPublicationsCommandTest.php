<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportResearchPublicationsCommandTest extends TestCase
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
        Schema::connection('legacy_mysql')->create('jx_member_categories', function ($table): void {
            $table->increments('id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
            $table->unsignedInteger('service_type')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('url')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        Schema::connection('legacy_mysql')->create('jx_member_items', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('member_category_id')->nullable();
            $table->unsignedInteger('service_type')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('en_file')->nullable();
            $table->string('ar_file')->nullable();
            $table->boolean('is_accepted')->default(true);
        });
        app('db')->connection('legacy_mysql')->table('jx_member_categories')->insert([
            'id' => 10,
            'ar_name' => 'بحث منشور',
            'en_name' => 'Published Research',
            'ar_data' => null,
            'en_data' => null,
            'service_type' => 1,
            'is_visible' => 1,
            'url' => null,
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function test_command_runs_research_publications_dry_run(): void
    {
        $this->artisan('legacy-import:research-publications --batch=research-test --limit=1')
            ->expectsOutputToContain('Legacy Research Publication Import')
            ->expectsOutputToContain('Written: no')
            ->expectsOutputToContain('Importable rows: 1')
            ->assertSuccessful();
    }
}
