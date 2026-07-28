<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCentralCouncilImportServiceInterface;
use App\Models\Faculty\Council;
use App\Models\Person\CouncilMember;
use App\Models\Person\CouncilMemberTranslation;
use App\Models\Shared\MigrationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyCentralCouncilImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $connection = 'legacy_central_council_import_testing';

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
        DB::connection($this->connection)->table('jx_councils')->insert([
            $this->row(10, 1, ['email' => 'board@example.test', 'ar_data' => '<p>سيرة<script>bad()</script></p>', 'en_data' => '<p>Bio<script>bad()</script></p>']),
            $this->row(20, 2, ['ar_name' => null, 'en_name' => 'Council Person', 'phone' => '111', 'mobile' => '222']),
            $this->row(21, 2, ['ar_name' => 'عضو فقط', 'en_name' => 'Under Construction']),
            $this->row(30, 3),
            $this->row(40, 1, ['ar_name' => null, 'en_name' => 'Under Construction']),
        ]);
        DB::connection($this->connection)->table('jx_councils1')->insert([
            'id' => 999, 'service_type' => 1, 'ar_name' => 'Sentinel', 'en_name' => 'Sentinel', 'email' => 'sentinel@example.test',
        ]);
    }

    public function test_approved_services_write_disabled_lazy_councils_members_and_evidence_without_redirects(): void
    {
        $path = $this->packet([$this->approval(10, 1), $this->approval(20, 2), $this->approval(21, 2)]);
        $service = app(LegacyCentralCouncilImportServiceInterface::class);

        $dryRun = $service->import($path, batch: 'dry');
        $this->assertSame(3, $dryRun->importable);
        $this->assertDatabaseCount('councils', 0);

        $result = $service->import($path, write: true, approval: 'central-councils-import', batch: 'central-test');

        $this->assertSame(3, $result->imported);
        $this->assertSame(2, $result->councilsCreated);
        $this->assertSame(3, $result->membersCreated);
        $this->assertSame(8, $result->translationsCreated);
        $this->assertDatabaseHas('councils', ['slug' => 'university-board', 'type' => 'board', 'sort_order' => 1, 'is_enabled' => false]);
        $this->assertDatabaseHas('councils', ['slug' => 'university-council', 'type' => 'university_council', 'sort_order' => 2, 'is_enabled' => false]);
        $this->assertSame(3, CouncilMember::query()->whereNull('faculty_member_id')->where('is_enabled', false)->count());
        $this->assertSame(1, CouncilMemberTranslation::query()->where('full_name', 'Council Person')->count());
        $this->assertSame(0, CouncilMemberTranslation::query()->where('full_name', 'Under Construction')->count());
        $this->assertStringNotContainsString('<script', (string) CouncilMemberTranslation::query()->where('full_name', 'English 10')->value('bio'));

        $log = MigrationLog::query()->where('module', 'central_council_members')->where('source_id', 10)->firstOrFail();
        $this->assertSame('council_members', $log->target_table);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($path)), $log->metadata['approval_packet']['sha256']);
        $this->assertSame($path, $log->metadata['approval_packet']['path']);
        $this->assertSame('local', $log->metadata['approval_packet']['disk']);
        $this->assertSame('board@example.test', $log->metadata['legacy_email']);
        $this->assertSame('photo.jpg', $log->metadata['legacy_photo']);
        $this->assertSame('cv.pdf', $log->metadata['legacy_cv']);
        $this->assertSame('ar-cv.pdf', $log->metadata['legacy_ar_cv']);
        $this->assertSame(1, $log->metadata['legacy_service']);
        $this->assertFalse($log->metadata['faculty_identity_linked']);
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $this->assertSame(0, MigrationLog::query()->where('source_table', 'jx_councils1')->count());

        $replay = $service->import($path, write: true, approval: 'central-councils-import');
        $this->assertSame(0, $replay->imported);
        $this->assertSame(3, $replay->reasonCounts['already_mapped']);
        $this->assertDatabaseCount('council_members', 3);
    }

    public function test_packet_rejections_and_source_verification_have_deterministic_reasons(): void
    {
        $path = $this->packet([
            $this->approval(10, 1, ['approval_decision' => '']),
            $this->approval(60, 2, ['approval_decision' => 'reject']),
            $this->approval(61, 2, ['approved_target' => 'faculty_members']),
            $this->approval(30, 3),
            $this->approval(62, 1, ['source_table' => 'jx_councils1']),
            $this->approval(50, 1, ['candidate_target_module' => 'faculty_members']),
            $this->approval(51, 1, ['candidate_faculty_slug' => 'medicine']),
            $this->approval(52, 1),
            $this->approval(52, 1),
            $this->approval(20, 1),
            $this->approval(53, 1),
            $this->approval(-1, 1),
        ]);

        $result = app(LegacyCentralCouncilImportServiceInterface::class)->import($path);

        $this->assertSame(0, $result->importable);
        $this->assertSame(1, $result->reasonCounts['blank_approval_decision']);
        $this->assertSame(1, $result->reasonCounts['approval_decision_not_import']);
        $this->assertSame(1, $result->reasonCounts['approved_target_not_council_members']);
        $this->assertSame(1, $result->reasonCounts['invalid_service_type']);
        $this->assertSame(1, $result->reasonCounts['source_table_mismatch']);
        $this->assertSame(1, $result->reasonCounts['target_module_mismatch']);
        $this->assertSame(1, $result->reasonCounts['faculty_scope_mismatch']);
        $this->assertSame(2, $result->reasonCounts['duplicate_source_id']);
        $this->assertSame(1, $result->reasonCounts['source_service_mismatch']);
        $this->assertSame(1, $result->reasonCounts['missing_source']);
        $this->assertSame(1, $result->reasonCounts['invalid_source_id']);
        $this->assertDatabaseCount('councils', 0);
    }

    public function test_missing_names_empty_approval_and_manual_council_conflicts_create_nothing(): void
    {
        $service = app(LegacyCentralCouncilImportServiceInterface::class);
        $missingName = $service->import($this->packet([$this->approval(40, 1)]), write: true, approval: 'central-councils-import');
        $this->assertSame(1, $missingName->reasonCounts['missing_usable_name']);
        $this->assertDatabaseCount('councils', 0);

        $empty = $service->import($this->packet([$this->approval(10, 1, ['approval_decision' => ''])]), write: true, approval: 'central-councils-import');
        $this->assertSame(0, $empty->councilsCreated);
        $this->assertDatabaseCount('councils', 0);

        $enabled = Council::query()->create(['slug' => 'university-board', 'type' => 'board', 'sort_order' => 99, 'is_enabled' => true]);
        $wrongType = Council::query()->create(['slug' => 'university-council', 'type' => 'manual', 'sort_order' => 98, 'is_enabled' => false]);
        $conflicts = $service->import($this->packet([$this->approval(10, 1), $this->approval(20, 2)]), write: true, approval: 'central-councils-import');

        $this->assertSame(2, $conflicts->reasonCounts['manual_council_conflict']);
        $this->assertSame('board', $enabled->fresh()->type);
        $this->assertTrue($enabled->fresh()->is_enabled);
        $this->assertSame(99, $enabled->fresh()->sort_order);
        $this->assertSame('manual', $wrongType->fresh()->type);
        $this->assertDatabaseCount('council_members', 0);
        $this->assertDatabaseCount('council_translations', 0);
    }

    public function test_input_and_header_guards_are_strict(): void
    {
        $service = app(LegacyCentralCouncilImportServiceInterface::class);
        $this->assertSame(0, $service->import()->scanned);

        try {
            $service->import(write: true, approval: 'wrong');
            $this->fail('Expected write token validation.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('councils', 0);
        }

        Storage::disk('local')->put('approved/bad.csv', "source_table,source_id,source_id\njx_councils,10,10\n");
        $this->expectException(InvalidArgumentException::class);
        $service->import('approved/bad.csv');
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(int $id, int $service, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'parent' => 0, 'service_type' => $service, 'council_order' => $id,
            'ar_name' => 'عربي '.$id, 'en_name' => 'English '.$id, 'is_visible' => 1, 'is_link' => 0, 'url' => null,
            'photo' => 'photo.jpg', 'ar_position' => 'منصب', 'en_position' => 'Position',
            'ar_specialization' => null, 'en_specialization' => null, 'phone' => null, 'mobile' => '555',
            'email' => null, 'cv' => 'cv.pdf', 'ar_cv' => 'ar-cv.pdf', 'academic_rank' => 2,
            'ar_data' => '<p>سيرة</p>', 'en_data' => '<p>Bio</p>',
        ], $overrides);
    }

    /** @param array<string, string|int> $overrides @return array<string, string|int> */
    private function approval(int $id, int $service, array $overrides = []): array
    {
        return array_merge([
            'source_table' => 'jx_councils', 'source_id' => $id, 'service_type' => $service,
            'candidate_target_module' => 'councils', 'candidate_faculty_slug' => '',
            'approval_decision' => 'import', 'approved_target' => 'council_members',
        ], $overrides);
    }

    /** @param array<int, array<string, string|int>> $rows */
    private function packet(array $rows): string
    {
        $headers = ['source_table', 'source_id', 'service_type', 'candidate_target_module', 'candidate_faculty_slug', 'approval_decision', 'approved_target'];
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
