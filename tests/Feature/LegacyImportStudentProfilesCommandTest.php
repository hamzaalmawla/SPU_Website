<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faculty\Faculty;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportStudentProfilesCommandTest extends TestCase
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
        config()->set('old_database.cleaning_inspection_fields.alumni', [[
            'table' => 'jx_graduated_students',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ],
        ]]);

        Faculty::query()->create(['slug' => 'medicine', 'sort_order' => 1, 'is_enabled' => true]);
        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_graduated_students', function ($table): void {
            $table->increments('id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('section_id')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('record_order')->default(0);
            $table->unsignedInteger('year')->nullable();
            $table->unsignedInteger('s_order')->nullable();
            $table->string('grade')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedInteger('date_year')->nullable();
            $table->dateTime('post_date')->nullable();
        });
        app('db')->connection('legacy_mysql')->table('jx_graduated_students')->insert([
            'id' => 10,
            'ar_name' => 'أحمد علي',
            'en_name' => 'Ahmad Ali',
            'department_id' => 2,
            'section_id' => 1,
            'is_visible' => 1,
            'record_order' => 1,
            'year' => 2022,
            's_order' => 1,
            'grade' => 'Bachelor',
            'photo' => 'old-photo.jpg',
            'date_year' => 2023,
            'post_date' => '2024-01-01 10:00:00',
        ]);
    }

    public function test_command_runs_student_profile_dry_run(): void
    {
        $this->artisan('legacy-import:student-profiles alumni --batch=student-test')
            ->expectsOutputToContain('Legacy Student Profile Import')
            ->expectsOutputToContain('Written: no')
            ->expectsOutputToContain('Importable rows: 1')
            ->assertSuccessful();
    }
}
