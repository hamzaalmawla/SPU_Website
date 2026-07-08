<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyFacultyProfileImportServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyFacultyProfileImportServiceTest extends TestCase
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
            $table->unsignedInteger('parent')->default(0);
            $table->unsignedInteger('service_type')->nullable();
            $table->unsignedInteger('council_order')->default(0);
            $table->string('photo')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('ar_position')->nullable();
            $table->string('en_position')->nullable();
            $table->string('ar_specialization')->nullable();
            $table->string('en_specialization')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('cv')->nullable();
            $table->unsignedInteger('academic_rank')->nullable();
        });

        app('db')->connection('legacy_mysql')->table('jx_councils1')->insert([
            [
                'id' => 10,
                'ar_name' => 'د. طبيب مستورد',
                'en_name' => 'Imported Doctor',
                'ar_data' => '<p>سيرة</p>',
                'en_data' => '<p>Bio</p>',
                'parent' => 0,
                'service_type' => 4,
                'council_order' => 2,
                'photo' => 'doctor.jpg',
                'is_visible' => 1,
                'ar_position' => 'عضو هيئة تدريس',
                'en_position' => 'Faculty Member',
                'ar_specialization' => 'طب داخلي',
                'en_specialization' => 'Internal Medicine',
                'phone' => '123',
                'mobile' => null,
                'email' => 'doctor@example.com',
                'cv' => 'legacy-cv.pdf',
                'academic_rank' => 3,
            ],
            [
                'id' => 11,
                'ar_name' => 'عميد مؤجل',
                'en_name' => 'Deferred Dean',
                'ar_data' => null,
                'en_data' => null,
                'parent' => 0,
                'service_type' => 3,
                'council_order' => 1,
                'photo' => 'dean.jpg',
                'is_visible' => 1,
                'ar_position' => 'عميد',
                'en_position' => 'Dean',
                'ar_specialization' => null,
                'en_specialization' => null,
                'phone' => null,
                'mobile' => null,
                'email' => null,
                'cv' => null,
                'academic_rank' => 1,
            ],
        ]);
    }

    public function test_dry_run_does_not_create_faculty_members(): void
    {
        $result = app(LegacyFacultyProfileImportServiceInterface::class)->import(batch: 'faculty-test');

        $this->assertFalse($result->written);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(1, $result->skipReasonCounts['deferred_non_faculty_staff_row']);
        $this->assertSame(0, FacultyMember::query()->count());
        $this->assertSame(0, MigrationLog::query()->count());
    }

    public function test_write_requires_approval_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LegacyFacultyProfileImportServiceInterface::class)->import(write: true, approval: 'wrong');
    }

    public function test_write_imports_disabled_without_media_and_is_idempotent(): void
    {
        $service = app(LegacyFacultyProfileImportServiceInterface::class);

        $first = $service->import(write: true, approval: 'phase6-faculty-members', batch: 'faculty-test');
        $second = $service->import(write: true, approval: 'phase6-faculty-members', batch: 'faculty-test-2');

        $this->assertSame(1, $first->importedRows);
        $this->assertSame(0, $second->importedRows);
        $this->assertSame(1, FacultyMember::query()->where('is_enabled', false)->whereNull('photo_media_id')->whereNull('cv_media_id')->count());
        $this->assertSame(2, FacultyMemberTranslation::query()->count());
        $this->assertSame('Imported Doctor', FacultyMemberTranslation::query()->where('locale', 'en')->value('full_name'));
        $this->assertSame(2, MigrationLog::query()->count());
        $this->assertSame(1, MigrationLog::query()->where('status', 'success')->count());
        $this->assertSame('doctor.jpg', MigrationLog::query()->where('status', 'success')->firstOrFail()->metadata['legacy_photo']);
        $this->assertSame(2, $second->skipReasonCounts['already_processed']);
    }
}
