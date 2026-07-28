<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCareerLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyCareerLinkReviewPacketServiceInterface;
use App\Models\Career\CareerLink;
use App\Models\Career\CareerLinkTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyCareerLinkPipelinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_job_sites', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->string('url')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('record_order')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
        });
        DB::connection('legacy_mysql')->table('jx_job_sites')->insert([
            ['id' => 1, 'ar_name' => '<b>عمل عربي</b>', 'en_name' => null, 'url' => 'https://jobs.example/a', 'photo' => 'legacy/jobs/a.jpg', 'is_visible' => 1, 'record_order' => 2, 'ar_data' => '<p class="Mso">وصف</p><script>BAD</script>', 'en_data' => null],
            ['id' => 2, 'ar_name' => null, 'en_name' => 'English Jobs', 'url' => 'http://jobs.example/b', 'photo' => null, 'is_visible' => 1, 'record_order' => 3, 'ar_data' => null, 'en_data' => '<p>Description</p>'],
            ['id' => 3, 'ar_name' => 'مكرر', 'en_name' => null, 'url' => 'https://jobs.example/a', 'photo' => null, 'is_visible' => 1, 'record_order' => 4, 'ar_data' => null, 'en_data' => null],
            ['id' => 4, 'ar_name' => 'Unsafe', 'en_name' => null, 'url' => 'javascript:alert(1)', 'photo' => null, 'is_visible' => 1, 'record_order' => 5, 'ar_data' => null, 'en_data' => null],
            ['id' => 5, 'ar_name' => 'Hidden', 'en_name' => null, 'url' => 'https://jobs.example/hidden', 'photo' => null, 'is_visible' => 0, 'record_order' => 6, 'ar_data' => null, 'en_data' => null],
        ]);
    }

    public function test_packet_classifies_urls_duplicates_hidden_rows_and_photo_evidence_without_writes(): void
    {
        MigrationLog::query()->create([
            'module' => 'old', 'batch_name' => 'old', 'source_table' => 'jx_job_sites', 'source_id' => 2,
            'target_table' => 'career_links', 'target_id' => 20, 'status' => 'success',
        ]);
        $result = app(LegacyCareerLinkReviewPacketServiceInterface::class)->export(directory: 'career-packets');
        $csv = Storage::disk('local')->get($this->path($result->paths, '.csv'));

        $this->assertSame(5, $result->totalRows);
        $this->assertSame(0, $result->candidateRows);
        $this->assertStringContainsString('duplicate_url', $csv);
        $this->assertStringContainsString('invalid_or_unsafe_url', $csv);
        $this->assertStringContainsString('hidden_source', $csv);
        $this->assertStringContainsString('existing_target_mapping', $csv);
        $this->assertStringContainsString('legacy/jobs/a.jpg', $csv);
        $this->assertStringContainsString(',approval_decision,approved_target,', explode("\n", $csv)[0]);
        $this->assertStringNotContainsString('<script', $csv);
        $this->assertDatabaseCount('career_links', 0);
    }

    public function test_import_uses_verified_source_and_creates_disabled_external_one_locale_links_idempotently(): void
    {
        Storage::disk('local')->put('career-approved.csv', $this->packet([1, 2]));
        $service = app(LegacyCareerLinkImportServiceInterface::class);
        $dry = $service->import('career-approved.csv');
        $this->assertSame(2, $dry->importableRows);
        $this->assertDatabaseCount('career_links', 0);

        $written = $service->import('career-approved.csv', write: true, approval: 'legacy-career-links-import', batch: 'career-write');
        $replay = $service->import('career-approved.csv', write: true, approval: 'legacy-career-links-import', batch: 'career-replay');

        $this->assertSame(2, $written->importedRows);
        $this->assertSame(0, $replay->importedRows);
        $this->assertSame(0, CareerLink::query()->where('is_enabled', true)->count());
        $this->assertSame(2, CareerLink::query()->where('is_external', true)->count());
        $this->assertSame(['ar', 'en'], CareerLinkTranslation::query()->orderBy('locale')->pluck('locale')->all());
        $this->assertSame(2, CareerLinkTranslation::query()->count());
        $this->assertStringNotContainsString('script', (string) CareerLinkTranslation::query()->where('locale', 'ar')->value('description'));
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $log = MigrationLog::query()->where('module', 'legacy_career_links')->where('source_id', 1)->firstOrFail();
        $this->assertSame('legacy/jobs/a.jpg', $log->metadata['legacy_photo_path']);
        $this->assertFalse($log->metadata['media_imported']);
        $this->assertSame(hash('sha256', Storage::disk('local')->get('career-approved.csv')), $log->metadata['approval_packet']['sha256']);
    }

    public function test_import_blocks_unsafe_hidden_duplicate_current_and_empty_approvals(): void
    {
        $service = app(LegacyCareerLinkImportServiceInterface::class);
        Storage::disk('local')->put('blocked.csv', $this->packet([1, 3, 4, 5]));
        $blocked = $service->import('blocked.csv');
        $this->assertSame(2, $blocked->skipReasonCounts['duplicate_approved_url']);
        $this->assertSame(1, $blocked->skipReasonCounts['invalid_or_unsafe_url']);
        $this->assertSame(1, $blocked->skipReasonCounts['hidden_source']);

        CareerLink::query()->create(['url' => 'http://jobs.example/b', 'is_external' => true, 'sort_order' => 0, 'is_enabled' => false]);
        Storage::disk('local')->put('current.csv', $this->packet([2]));
        $current = $service->import('current.csv');
        $this->assertSame(1, $current->skipReasonCounts['current_url_conflict']);

        Storage::disk('local')->put('empty.csv', "source_table,source_id,approval_decision,approved_target\njx_job_sites,1,,\n");
        $empty = $service->import('empty.csv', write: true, approval: 'legacy-career-links-import');
        $this->assertSame(0, $empty->importedRows);
        $this->assertSame(1, CareerLink::query()->count());

        $this->expectException(InvalidArgumentException::class);
        $service->import('current.csv', write: true, approval: 'wrong');
    }

    /** @param list<int> $ids */
    private function packet(array $ids): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['source_table', 'source_id', 'approval_decision', 'approved_target']);
        foreach ($ids as $id) {
            fputcsv($stream, ['jx_job_sites', $id, 'import', 'career_links']);
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /** @param list<string> $paths */
    private function path(array $paths, string $suffix): string
    {
        return (string) collect($paths)->first(fn (string $path): bool => str_ends_with($path, $suffix));
    }
}
