<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPublicStaffImportServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Shared\MigrationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPublicStaffImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $connection = 'legacy_public_staff_import_testing';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config()->set('old_database.connection_name', $this->connection);
        config()->set('old_database.connection', $config);
        config()->set('database.connections.'.$this->connection, $config);
        DB::purge($this->connection);
        $this->createLegacyTables();
        foreach (['medicine', 'dentistry', 'pharmacy', 'ai-engineering', 'petroleum', 'business'] as $index => $slug) {
            Faculty::query()->create(['slug' => $slug, 'sort_order' => $index + 1, 'is_enabled' => true]);
        }
        DB::connection($this->connection)->table('jx_councils')->insert([
            $this->row(10, 4, ['email' => 'doctor@example.com', 'ar_data' => '<p>سيرة<script>bad()</script></p>', 'en_data' => '<p>Bio<script>bad()</script></p>']),
            $this->row(11, 13, ['ar_name' => 'عميد الأعمال', 'en_name' => 'Under Construction', 'email' => 'https://profile.example.test']),
            $this->row(12, 4, ['ar_name' => 'Conflict', 'en_name' => 'Conflict', 'email' => 'current@example.com']),
            $this->row(13, 4, ['ar_name' => 'Duplicate A', 'en_name' => 'Same Name', 'email' => 'same@example.com']),
            $this->row(14, 4, ['ar_name' => 'Duplicate B', 'en_name' => 'Same Name', 'email' => 'SAME@example.com']),
            $this->row(15, 4, ['ar_name' => 'Same Arabic', 'en_name' => 'Unique One', 'email' => 'one@example.com']),
            $this->row(16, 4, ['ar_name' => 'Same Arabic', 'en_name' => 'Unique Two', 'email' => 'two@example.com']),
        ]);
        DB::connection($this->connection)->table('jx_councils1')->insert([
            'id' => 999, 'service_type' => 4, 'ar_name' => 'Never Imported', 'en_name' => 'Never Imported', 'email' => 'rogue@example.com',
        ]);
    }

    public function test_approved_rows_import_as_disabled_drafts_without_locale_synthesis_and_replay_safely(): void
    {
        $path = $this->packet([
            $this->approval(10, 4, 'medicine'),
            $this->approval(11, 13, 'business'),
        ]);
        $service = app(LegacyPublicStaffImportServiceInterface::class);
        $dryRun = $service->import($path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $first = $service->import($path, write: true, approval: 'public-staff-import', batch: 'staff-test');
        $this->assertTrue(Storage::disk('local')->exists($path));
        $replay = $service->import($path, write: true, approval: 'public-staff-import', batch: 'staff-replay');

        $this->assertSame(2, $dryRun->importableRows);
        $this->assertSame(2, $first->importedRows);
        $this->assertSame(0, $replay->importedRows);
        $this->assertSame(2, $replay->skipReasonCounts['already_mapped']);
        $this->assertSame(2, FacultyMember::query()->where('is_enabled', false)->where('publication_status', 'draft')->whereNull('published_at')->whereNull('photo_media_id')->whereNull('cv_media_id')->count());
        $business = FacultyMember::query()->whereHas('faculty', fn ($query) => $query->where('slug', 'business'))->firstOrFail();
        $this->assertSame(1, $business->translations()->count());
        $this->assertSame('ar', $business->translations()->value('locale'));
        $this->assertNull($business->email);
        $doctorBio = FacultyMemberTranslation::query()->where('locale', 'en')->where('full_name', 'English 10')->value('bio');
        $this->assertStringNotContainsString('<script', (string) $doctorBio);
        $log = MigrationLog::query()->where('source_id', 10)->where('source_table', 'jx_councils')->firstOrFail();
        $this->assertSame('public_faculty_members', $log->module);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($path)), $log->metadata['approval_packet']['sha256']);
        $this->assertSame('photo.jpg', $log->metadata['legacy_photo']);
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $this->assertSame(0, MigrationLog::query()->where('source_table', 'jx_councils1')->count());
    }

    public function test_packet_gates_and_identity_conflicts_are_deterministic(): void
    {
        $medicine = Faculty::query()->where('slug', 'medicine')->firstOrFail();
        FacultyMember::query()->create(['slug' => 'current', 'faculty_id' => $medicine->getKey(), 'email' => 'current@example.com', 'is_enabled' => false, 'sort_order' => 0, 'publication_status' => 'draft']);
        $path = $this->packet([
            $this->approval(10, 4, 'medicine', ''),
            $this->approval(11, 1, '', 'import'),
            $this->approval(12, 4, 'medicine'),
            $this->approval(13, 4, 'medicine'),
            $this->approval(14, 4, 'medicine'),
            $this->approval(15, 4, 'medicine'),
            $this->approval(16, 4, 'medicine'),
            $this->approval(99, 4, 'business'),
            $this->approval(99, 4, 'business'),
        ]);

        $result = app(LegacyPublicStaffImportServiceInterface::class)->import($path);

        $this->assertSame(0, $result->importableRows);
        $this->assertSame(1, $result->skipReasonCounts['blank_approval_decision']);
        $this->assertSame(1, $result->skipReasonCounts['central_service_not_importable']);
        $this->assertSame(1, $result->skipReasonCounts['current_email_conflict']);
        $this->assertSame(2, $result->skipReasonCounts['duplicate_approved_email']);
        $this->assertSame(2, $result->skipReasonCounts['duplicate_approved_name']);
        $this->assertSame(2, $result->skipReasonCounts['duplicate_source_id']);
        $this->assertDatabaseCount('faculty_members', 1);
    }

    public function test_rejects_token_missing_input_headers_and_packet_source_mismatches(): void
    {
        $service = app(LegacyPublicStaffImportServiceInterface::class);
        $this->assertSame(0, $service->import()->scannedRows);
        try {
            $service->import(write: true, approval: 'wrong');
            $this->fail('Expected token gate.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('faculty_members', 0);
        }
        $bad = $this->packet([array_merge($this->approval(10, 4, 'business'), ['source_table' => 'jx_councils1'])]);
        $result = $service->import($bad);
        $this->assertSame(1, $result->skipReasonCounts['source_table_mismatch']);
        $mismatch = $this->packet([$this->approval(10, 3, 'medicine')]);
        $this->assertSame(1, $service->import($mismatch)->skipReasonCounts['source_service_mismatch']);
        $wrongFaculty = $this->packet([$this->approval(10, 4, 'business')]);
        $this->assertSame(1, $service->import($wrongFaculty)->skipReasonCounts['faculty_mapping_mismatch']);
        Storage::disk('local')->put('approved/missing-headers.csv', "source_table,source_id\njx_councils,10\n");
        $this->expectException(InvalidArgumentException::class);
        $service->import('approved/missing-headers.csv');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(int $id, int $service, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'parent' => 0, 'service_type' => $service, 'council_order' => $id,
            'ar_name' => 'عربي '.$id, 'en_name' => 'English '.$id, 'is_visible' => 1, 'is_link' => 0, 'url' => null,
            'photo' => 'photo.jpg', 'ar_position' => 'منصب', 'en_position' => 'Position',
            'ar_specialization' => 'تخصص', 'en_specialization' => 'Specialty', 'phone' => null, 'mobile' => '555',
            'email' => null, 'cv' => 'cv.pdf', 'ar_cv' => 'ar-cv.pdf', 'academic_rank' => 2,
            'ar_data' => '<p>سيرة</p>', 'en_data' => '<p>Bio</p>',
        ], $overrides);
    }

    /** @return array<string, string|int> */
    private function approval(int $id, int $service, string $faculty, string $decision = 'import'): array
    {
        return ['source_table' => 'jx_councils', 'source_id' => $id, 'service_type' => $service, 'candidate_faculty_slug' => $faculty, 'approval_decision' => $decision, 'approved_target' => 'faculty_members'];
    }

    /** @param array<int, array<string, string|int>> $rows */
    private function packet(array $rows): string
    {
        $headers = ['source_table', 'source_id', 'service_type', 'candidate_faculty_slug', 'approval_decision', 'approved_target'];
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): mixed => $row[$header], $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $path = 'approved/'.md5((string) $csv).'.csv';
        Storage::disk('local')->put($path, (string) $csv);

        return $path;
    }

    private function createLegacyTables(): void
    {
        foreach (['jx_councils', 'jx_councils1'] as $name) {
            Schema::connection($this->connection)->create($name, function ($table): void {
                $table->integer('id')->primary();
                $table->integer('parent')->nullable();
                $table->integer('service_type');
                $table->integer('council_order')->nullable();
                $table->string('ar_name')->nullable();
                $table->string('en_name')->nullable();
                $table->integer('is_visible')->nullable();
                $table->integer('is_link')->nullable();
                $table->string('url')->nullable();
                $table->string('photo')->nullable();
                $table->string('ar_position')->nullable();
                $table->string('en_position')->nullable();
                $table->string('ar_specialization')->nullable();
                $table->string('en_specialization')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('email')->nullable();
                $table->string('cv')->nullable();
                $table->string('ar_cv')->nullable();
                $table->integer('academic_rank')->nullable();
                $table->longText('ar_data')->nullable();
                $table->longText('en_data')->nullable();
            });
        }
    }
}
