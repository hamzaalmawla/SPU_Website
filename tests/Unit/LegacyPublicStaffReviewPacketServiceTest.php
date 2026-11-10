<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPublicStaffReviewPacketServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPublicStaffReviewPacketServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $connection = 'legacy_public_staff_packet_testing';

    protected function setUp(): void
    {
        parent::setUp();
        $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false];
        config()->set('old_database.connection_name', $this->connection);
        config()->set('old_database.connection', $config);
        config()->set('database.connections.'.$this->connection, $config);
        DB::purge($this->connection);
        $this->createLegacyTables();
        Faculty::query()->create(['slug' => 'medicine', 'sort_order' => 1, 'is_enabled' => true]);
        Faculty::query()->create(['slug' => 'business', 'sort_order' => 2, 'is_enabled' => true]);
    }

    public function test_exports_audit_evidence_without_html_or_mutation(): void
    {
        Storage::fake('local');
        DB::connection($this->connection)->table('jx_councils')->insert([
            $this->row(1, 1, ['parent' => 999, 'is_visible' => 0, 'is_link' => 1, 'email' => 'https://profiles.example.test/a', 'ar_data' => '<p>SECRET CENTRAL HTML</p>']),
            $this->row(2, 4, ['ar_name' => 'طبيب مكرر', 'en_name' => 'Duplicate Doctor', 'email' => ' Doctor@Example.com ', 'ar_data' => '<p>SECRET MEDICINE HTML</p>']),
            $this->row(3, 13, ['ar_name' => null, 'en_name' => 'Business Dean', 'email' => 'not-an-email']),
            $this->row(4, 4, ['ar_name' => 'طبيب مكرر', 'en_name' => 'Other Doctor', 'email' => 'doctor@example.com']),
            $this->row(5, 5, ['ar_name' => 'عميد الأسنان', 'en_name' => 'Dentistry Dean']),
        ]);
        DB::connection($this->connection)->table('jx_councils1')->insert(['id' => 90, 'email' => 'doctor@example.com']);
        $faculty = Faculty::query()->where('slug', 'medicine')->firstOrFail();
        FacultyMember::query()->create([
            'slug' => 'existing-doctor', 'faculty_id' => $faculty->getKey(), 'email' => 'doctor@example.com',
            'sort_order' => 1, 'is_enabled' => false, 'publication_status' => 'draft',
        ]);
        DB::table('migration_logs')->insert([
            'module' => 'public_faculty_members', 'batch_name' => 'old', 'source_table' => 'jx_councils',
            'source_id' => 3, 'target_table' => 'faculty_members', 'target_id' => 20, 'status' => 'success', 'created_at' => now(),
        ]);

        $result = app(LegacyPublicStaffReviewPacketServiceInterface::class)->export(
            services: [1, 4, 5, 13], directory: 'staff-review',
        );

        $this->assertSame([1, 4, 5, 13], $result->selectedServices);
        $this->assertSame(5, $result->sourceRows);
        $this->assertSame(4, $result->packetCount);
        $central = $this->csvRows($this->packet($result->paths, 'service_01_'))[0];
        $medicine = $this->csvRows($this->packet($result->paths, 'service_04_'));
        $dentistry = $this->csvRows($this->packet($result->paths, 'service_05_'))[0];
        $business = $this->csvRows($this->packet($result->paths, 'service_13_'))[0];

        $this->assertSame('university_board', $central['context_semantic']);
        $this->assertSame('councils', $central['candidate_target_module']);
        $this->assertSame('', $central['candidate_faculty_slug']);
        $this->assertSame('/index.php?dir=councils&page=show&service=1&cat_id=1&lang=1', $central['legacy_ar_url_candidate']);
        $this->assertSame('https://profiles.example.test/a', $central['profile_url_candidate']);
        foreach (['hidden_source', 'external_link', 'url_in_email_field', 'orphan_parent', 'central_council_requires_separate_target'] as $blocker) {
            $this->assertStringContainsString($blocker, $central['blockers']);
        }
        $this->assertSame('medicine', $medicine[0]['candidate_faculty_slug']);
        $this->assertSame('/med/index.php?dir=councils&page=show&service=4&cat_id=2&lang=2', $medicine[0]['legacy_en_url_candidate']);
        $this->assertSame('2', $medicine[0]['duplicate_source_email_count']);
        $this->assertSame('1', $medicine[0]['councils1_valid_email_match_count']);
        $this->assertSame('1', $medicine[0]['current_email_match_count']);
        $this->assertStringContainsString('current_email_conflict', $medicine[0]['blockers']);
        $this->assertStringContainsString('duplicate_source_name', $medicine[0]['blockers']);
        $this->assertStringContainsString('missing_faculty_target', $dentistry['blockers']);
        $this->assertSame('business', $business['candidate_faculty_slug']);
        $this->assertSame('mapped_reconciliation_review', $business['review_status']);
        $this->assertSame('', $business['approval_decision']);
        $this->assertSame('', $business['approved_target']);
        $this->assertStringContainsString('invalid_email', $business['blockers']);
        $exports = implode("\n", array_map(static fn (string $path): string => Storage::disk('local')->get($path), $result->paths));
        $this->assertStringNotContainsString('SECRET CENTRAL HTML', $exports);
        $this->assertStringNotContainsString('SECRET MEDICINE HTML', $exports);
        $this->assertSame(5, DB::connection($this->connection)->table('jx_councils')->count());
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
    }

    public function test_rejects_invalid_service_before_writing_files(): void
    {
        Storage::fake('local');
        $this->expectException(InvalidArgumentException::class);

        try {
            app(LegacyPublicStaffReviewPacketServiceInterface::class)->export(services: [15]);
        } finally {
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(int $id, int $service, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'parent' => 0, 'service_type' => $service, 'council_order' => $id,
            'ar_name' => 'اسم عربي '.$id, 'en_name' => 'English '.$id, 'is_visible' => 1, 'is_link' => 0,
            'url' => null, 'photo' => null, 'ar_position' => null, 'en_position' => null,
            'ar_specialization' => null, 'en_specialization' => null, 'phone' => null, 'mobile' => null,
            'email' => null, 'cv' => null, 'ar_cv' => null, 'academic_rank' => null, 'ar_data' => null, 'en_data' => null,
        ], $overrides);
    }

    private function createLegacyTables(): void
    {
        Schema::connection($this->connection)->create('jx_councils', function ($table): void {
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
        Schema::connection($this->connection)->create('jx_councils1', function ($table): void {
            $table->integer('id')->primary();
            $table->string('email')->nullable();
        });
    }

    /** @param array<int, string> $paths */
    private function packet(array $paths, string $needle): string
    {
        $path = collect($paths)->first(static fn (string $path): bool => str_contains(basename($path), $needle));
        $this->assertIsString($path);

        return Storage::disk('local')->get($path);
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);

        return $rows;
    }
}
