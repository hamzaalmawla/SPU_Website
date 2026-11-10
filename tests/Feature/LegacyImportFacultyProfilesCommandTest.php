<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faculty\Faculty;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportFacultyProfilesCommandTest extends TestCase
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

        Faculty::query()->create(['slug' => 'medicine', 'sort_order' => 1, 'is_enabled' => true]);

        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_councils1', function ($table): void {
            $table->increments('id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
            $table->string('email')->nullable();
            $table->string('cv')->nullable();
            $table->unsignedInteger('service_type')->nullable();
            $table->unsignedInteger('council_order')->default(0);
        });

        app('db')->connection('legacy_mysql')->table('jx_councils1')->insert([
            'id' => 10,
            'ar_name' => 'د. طبيب مستورد',
            'en_name' => 'Imported Doctor',
            'ar_data' => null,
            'en_data' => null,
            'email' => null,
            'cv' => null,
            'service_type' => 4,
            'council_order' => 1,
        ]);
    }

    public function test_command_runs_faculty_profiles_dry_run(): void
    {
        $this->artisan('legacy-import:faculty-profiles --batch=faculty-test')
            ->expectsOutputToContain('Legacy Faculty Profile Import')
            ->expectsOutputToContain('Written: no')
            ->expectsOutputToContain('Importable rows: 1')
            ->assertSuccessful();
    }
}
