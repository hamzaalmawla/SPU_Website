<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyStudentProfileImportServiceTest extends TestCase
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
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ],
        ]]);
        config()->set('old_database.cleaning_inspection_fields.honor_students', [[
            'table' => 'jx_good_students',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ],
        ]]);

        Faculty::query()->create(['slug' => 'medicine', 'sort_order' => 1, 'is_enabled' => true]);
        app(OldDatabaseConnection::class)->connection();
        $this->createLegacyStudentTable('jx_graduated_students');
        $this->createLegacyStudentTable('jx_good_students');
        $this->insertLegacyRows();
    }

    public function test_alumni_dry_run_does_not_create_records(): void
    {
        $result = app(LegacyStudentProfileImportServiceInterface::class)->import('alumni', batch: 'student-test');

        $this->assertFalse($result->written);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(1, $result->duplicateSkippedRows);
        $this->assertSame(0, Alumni::query()->count());
    }

    public function test_write_requires_lane_approval_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LegacyStudentProfileImportServiceInterface::class)->import('alumni', write: true, approval: 'wrong');
    }

    public function test_alumni_write_imports_disabled_without_photo_and_logs_metadata(): void
    {
        $cacheService = $this->createMock(CacheServiceInterface::class);
        $cacheService->expects($this->once())
            ->method('flushTags')
            ->with(['facilities', 'public-pages', 'seo', 'sitemap'])
            ->willReturn(false);
        $cacheService->expects($this->once())
            ->method('flushAll')
            ->willReturn(true);
        $this->app->instance(CacheServiceInterface::class, $cacheService);

        $service = app(LegacyStudentProfileImportServiceInterface::class);

        $first = $service->import('alumni', write: true, approval: 'phase6-alumni', batch: 'student-test');
        $second = $service->import('alumni', write: true, approval: 'phase6-alumni', batch: 'student-test-2');

        $this->assertSame(1, $first->importedRows);
        $this->assertSame(0, $second->importedRows);
        $this->assertSame(2, $second->skipReasonCounts['already_processed']);
        $this->assertSame(1, Alumni::query()->where('is_enabled', false)->whereNull('photo_media_id')->whereNull('degree')->count());
        $this->assertSame(2, AlumniTranslation::query()->count());

        $log = MigrationLog::query()->where('status', 'success')->firstOrFail();
        $metadata = $log->metadata;
        $this->assertIsArray($metadata);

        $this->assertSame('old-photo.jpg', $metadata['legacy_photo']);
        $this->assertSame(1, $metadata['legacy_section_id']);
    }

    public function test_honor_students_write_imports_disabled_without_photo(): void
    {
        config()->set('legacy_student_profile_overrides.honor_students', [
            20 => [
                'ar_name' => 'ياسمين نبيل المولا',
                'en_name' => null,
                'grade' => '89.60',
            ],
            21 => [
                'ar_name' => 'ياسمين نبيل المولا',
                'en_name' => null,
            ],
        ]);

        $result = app(LegacyStudentProfileImportServiceInterface::class)->import(
            'honor_students',
            write: true,
            approval: 'phase6-honor-students',
            batch: 'honor-test',
        );

        $this->assertSame(1, $result->importedRows);
        $this->assertSame(1, $result->duplicateSkippedRows);
        $this->assertSame(1, HonorStudent::query()->where('is_enabled', false)->whereNull('photo_media_id')->count());
        $this->assertSame('2024 / 1', HonorStudent::query()->value('academic_year'));
        $this->assertSame('89.60', HonorStudent::query()->value('gpa'));
        $this->assertSame(
            ['ياسمين نبيل المولا', 'ياسمين نبيل المولا'],
            HonorStudent::query()->firstOrFail()->translations()->orderBy('locale')->pluck('full_name')->all(),
        );
    }

    private function createLegacyStudentTable(string $table): void
    {
        Schema::connection('legacy_mysql')->create($table, function ($table): void {
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
    }

    private function insertLegacyRows(): void
    {
        app('db')->connection('legacy_mysql')->table('jx_graduated_students')->insert([
            [
                'id' => 10,
                'ar_name' => '  أحمد علي  ',
                'en_name' => '  Ahmad Ali  ',
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
            ],
            [
                'id' => 11,
                'ar_name' => 'أحمد علي',
                'en_name' => 'Ahmad Ali',
                'department_id' => 2,
                'section_id' => 1,
                'is_visible' => 1,
                'record_order' => 2,
                'year' => 2022,
                's_order' => 2,
                'grade' => 'Bachelor',
                'photo' => 'duplicate-photo.jpg',
                'date_year' => 2023,
                'post_date' => '2024-01-02 10:00:00',
            ],
        ]);

        app('db')->connection('legacy_mysql')->table('jx_good_students')->insert([
            [
                'id' => 20,
                'ar_name' => 'سارة علي',
                'en_name' => 'Sara Ali',
                'department_id' => 2,
                'section_id' => 1,
                'is_visible' => 1,
                'record_order' => 1,
                'year' => 1,
                's_order' => 1,
                'grade' => '%95.5',
                'photo' => 'honor-photo.jpg',
                'date_year' => 2024,
                'post_date' => '2024-01-03 10:00:00',
            ],
            [
                'id' => 21,
                'ar_name' => 'سارة علي',
                'en_name' => 'Sara A.',
                'department_id' => 2,
                'section_id' => 1,
                'is_visible' => 1,
                'record_order' => 2,
                'year' => 1,
                's_order' => 2,
                'grade' => '%95.5',
                'photo' => 'honor-duplicate-photo.jpg',
                'date_year' => 2024,
                'post_date' => '2024-01-04 10:00:00',
            ],
        ]);
    }
}
