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
        $this->assertSame(0, $first->placeholderRowsDisabled);
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
        $this->assertSame(['ar'], HonorStudent::query()->firstOrFail()->translations()->pluck('locale')->all());
        $this->assertSame(['ياسمين نبيل المولا'], HonorStudent::query()->firstOrFail()->translations()->pluck('full_name')->all());
    }

    public function test_write_disables_only_known_seeded_placeholders_and_logs_cleanup(): void
    {
        $medicine = Faculty::query()->where('slug', 'medicine')->firstOrFail();
        Alumni::query()->create([
            'student_identifier' => 'medicine-alumni-2023', 'faculty_id' => $medicine->getKey(),
            'graduation_year' => 2023, 'is_featured' => true, 'is_enabled' => true,
        ]);
        $unrelated = Alumni::query()->create([
            'student_identifier' => 'verified-current-record', 'faculty_id' => $medicine->getKey(),
            'graduation_year' => 2024, 'is_featured' => false, 'is_enabled' => true,
        ]);

        $result = app(LegacyStudentProfileImportServiceInterface::class)->import(
            'alumni', write: true, approval: 'phase6-alumni', batch: 'placeholder-cleanup-test',
        );

        $this->assertSame(1, $result->placeholderRowsDisabled);
        $this->assertFalse(Alumni::query()->where('student_identifier', 'medicine-alumni-2023')->value('is_enabled'));
        $this->assertTrue($unrelated->fresh()->is_enabled);
        $this->assertDatabaseHas('migration_logs', [
            'module' => 'student_profile_placeholder_cleanup', 'batch_name' => 'placeholder-cleanup-test',
            'source_table' => 'application_seed', 'target_table' => 'alumni', 'status' => 'success',
        ]);
    }

    public function test_publication_enables_only_provenance_backed_visible_imports(): void
    {
        $medicine = Faculty::query()->where('slug', 'medicine')->firstOrFail();
        $placeholder = Alumni::query()->create([
            'student_identifier' => 'medicine-alumni-2023', 'faculty_id' => $medicine->getKey(),
            'graduation_year' => 2023, 'is_featured' => true, 'is_enabled' => true,
        ]);
        $service = app(LegacyStudentProfileImportServiceInterface::class);
        $service->import('alumni', write: true, approval: 'phase6-alumni', batch: 'publication-import');

        $dryRun = $service->publishImported('alumni', batch: 'publication-dry');
        $this->assertSame(1, $dryRun->importedMappings);
        $this->assertSame(1, $dryRun->eligibleRows);
        $this->assertSame(0, $dryRun->enabledRows);

        try {
            $service->publishImported('alumni', write: true, approval: 'wrong');
            $this->fail('Expected publication approval token validation.');
        } catch (InvalidArgumentException) {
            $this->assertFalse($placeholder->fresh()->is_enabled);
        }

        $published = $service->publishImported(
            'alumni', write: true, approval: 'publish-legacy-alumni', batch: 'publication-write',
        );
        $replay = $service->publishImported(
            'alumni', write: true, approval: 'publish-legacy-alumni', batch: 'publication-replay',
        );

        $this->assertSame(1, $published->enabledRows);
        $this->assertSame(0, $replay->enabledRows);
        $this->assertSame(1, $replay->alreadyEnabledRows);
        $this->assertFalse($placeholder->fresh()->is_enabled);
        $this->assertSame(1, Alumni::query()->whereNull('student_identifier')->where('is_enabled', true)->count());
        $this->assertDatabaseHas('migration_logs', [
            'module' => 'student_profile_publication', 'batch_name' => 'publication-write',
            'source_table' => 'jx_graduated_students', 'target_table' => 'alumni', 'status' => 'success',
        ]);
    }

    public function test_publication_keeps_hidden_source_record_disabled(): void
    {
        app('db')->connection('legacy_mysql')->table('jx_graduated_students')->where('id', 10)->update(['is_visible' => 0]);
        $service = app(LegacyStudentProfileImportServiceInterface::class);
        $service->import('alumni', write: true, approval: 'phase6-alumni', batch: 'hidden-import');

        $result = $service->publishImported(
            'alumni', write: true, approval: 'publish-legacy-alumni', batch: 'hidden-publication',
        );

        $this->assertSame(0, $result->visibleSourceRows);
        $this->assertSame(0, $result->enabledRows);
        $this->assertSame(1, $result->blockedRows);
        $this->assertSame(0, Alumni::query()->where('is_enabled', true)->count());
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
