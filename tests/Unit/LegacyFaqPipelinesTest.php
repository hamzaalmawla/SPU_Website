<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyFaqApprovalPacketServiceInterface;
use App\Contracts\Legacy\LegacyFaqImportServiceInterface;
use App\Contracts\Legacy\LegacyFaqReviewPacketServiceInterface;
use App\Models\Content\Faq;
use App\Models\Content\FaqTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyFaqPipelinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_faqs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('subject')->nullable();
            $table->text('question')->nullable();
            $table->text('answer')->nullable();
            $table->integer('faq_order')->nullable();
            $table->string('post_date')->nullable();
            $table->boolean('is_visible')->default(false);
            $table->integer('lang')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
        });
        $this->seedRows();
    }

    public function test_packet_separates_private_candidates_from_metadata_only_backlog(): void
    {
        MigrationLog::query()->create([
            'module' => 'old', 'batch_name' => 'old', 'source_table' => 'jx_faqs', 'source_id' => 8,
            'target_table' => 'faqs', 'target_id' => 88, 'status' => 'success',
        ]);

        $result = app(LegacyFaqReviewPacketServiceInterface::class)->export(directory: 'faq-packets');

        $this->assertSame(8, $result->totalRows);
        $this->assertSame(5, $result->candidateRows);
        $this->assertSame(3, $result->backlogRows);
        $candidate = Storage::disk('local')->get($this->path($result->paths, 'faq_candidates.csv'));
        $backlog = Storage::disk('local')->get($this->path($result->paths, 'faq_backlog.csv'));
        $this->assertStringContainsString('duplicate_supported_question', $candidate);
        $this->assertStringContainsString('content_contains_contact_pattern', $candidate);
        $this->assertStringContainsString('mapped_reconciliation_review', $candidate);
        $this->assertStringContainsString('Clean question', $candidate);
        $this->assertStringNotContainsString('<script', $candidate);
        $this->assertStringNotContainsString('Mso', $candidate);
        $this->assertStringContainsString('unsupported_locale', $backlog);
        $this->assertStringContainsString('hidden_source', $backlog);
        $this->assertSame(1, $result->reasonCounts['missing_answer']);
        $this->assertSame(1, $result->reasonCounts['hidden_source']);
        $this->assertSame(1, $result->reasonCounts['unsupported_locale']);
        $this->assertStringNotContainsString('BACKLOG_SECRET', $backlog);
        $this->assertStringNotContainsString('subject', explode("\n", $backlog)[0]);
        $this->assertStringContainsString(',approval_decision,approved_target,', explode("\n", $candidate)[0]);
        foreach (['first_name', 'last_name', 'email', 'country', 'phone'] as $piiHeader) {
            $this->assertNotContains($piiHeader, str_getcsv(explode("\n", $candidate)[0]));
            $this->assertNotContains($piiHeader, str_getcsv(explode("\n", $backlog)[0]));
        }
        $this->assertStringNotContainsString('submitter@example.test', $candidate.$backlog);
        $this->assertStringNotContainsString('PII-FIRST', $candidate.$backlog);
        $this->assertDatabaseCount('faqs', 0);
        $manifest = json_decode(Storage::disk('local')->get($this->path($result->paths, 'manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['first_name', 'last_name', 'email', 'country', 'phone'], $manifest['pii_fields_excluded']);
        $this->assertTrue($manifest['private_evidence']);
        $this->assertFalse($manifest['public_feature_complete']);
    }

    public function test_import_reverifies_source_and_writes_only_disabled_single_locale_rows_without_pii_values(): void
    {
        Storage::disk('local')->put('approved-faq.csv', $this->packet([
            $this->approval(1, 'ar', 1), $this->approval(2, 'en', 2), $this->approval(7, 'ar', 1),
        ]));
        $service = app(LegacyFaqImportServiceInterface::class);

        $dry = $service->import('approved-faq.csv', batch: 'dry');
        $this->assertSame(2, $dry->importableRows);
        $this->assertSame(1, $dry->skipReasonCounts['content_contact_blocked']);
        $this->assertDatabaseCount('faq_categories', 0);

        $written = $service->import('approved-faq.csv', write: true, approval: 'legacy-faq-import', batch: 'faq-write');
        $replay = $service->import('approved-faq.csv', write: true, approval: 'legacy-faq-import', batch: 'faq-replay');

        $this->assertSame(2, $written->importedRows);
        $this->assertSame(0, $replay->importedRows);
        $this->assertDatabaseHas('faq_categories', ['slug' => 'legacy-faq-review', 'is_enabled' => false, 'sort_order' => 0]);
        $this->assertSame(0, Faq::query()->where('is_enabled', true)->count());
        $this->assertSame(0, Faq::query()->where('is_featured', true)->count());
        $this->assertSame(['ar', 'en'], FaqTranslation::query()->orderBy('locale')->pluck('locale')->all());
        $this->assertSame(2, FaqTranslation::query()->count());
        $this->assertDatabaseCount('legacy_exact_redirects', 0);
        $log = MigrationLog::query()->where('module', 'legacy_faqs')->where('source_id', 1)->firstOrFail();
        $this->assertSame(hash('sha256', Storage::disk('local')->get('approved-faq.csv')), $log->metadata['approval_packet']['sha256']);
        $this->assertTrue($log->metadata['has_submitter_name']);
        $this->assertTrue($log->metadata['has_submitter_email']);
        $metadata = json_encode($log->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('submitter@example.test', $metadata);
        $this->assertStringNotContainsString('PII-FIRST', $metadata);
        $this->assertStringNotContainsString('9639999999', $metadata);
    }

    public function test_approval_packet_keeps_only_blocker_free_identity_and_hash_fields(): void
    {
        $review = app(LegacyFaqReviewPacketServiceInterface::class)->export(directory: 'faq-review');
        $candidate = $this->path($review->paths, 'faq_candidates.csv');

        $result = app(LegacyFaqApprovalPacketServiceInterface::class)->build($candidate, 'reviewer', directory: 'faq-approved');
        $second = app(LegacyFaqApprovalPacketServiceInterface::class)->build($candidate, 'reviewer', directory: 'faq-approved');

        $this->assertSame(5, $result->scannedRows);
        $this->assertSame(1, $result->approvedRows);
        $this->assertSame(4, $result->rejectedRows);
        $this->assertNotSame($result->paths[2], $second->paths[2]);
        $approved = Storage::disk('local')->get($this->path($result->paths, 'approved_faqs.csv'));
        $headers = str_getcsv(explode("\n", $approved)[0]);
        $this->assertContains('question_sha256', $headers);
        $this->assertContains('answer_sha256', $headers);
        foreach (['question', 'answer', 'subject', 'first_name', 'last_name', 'email', 'country', 'phone'] as $excluded) {
            $this->assertNotContains($excluded, $headers);
        }
        $this->assertStringNotContainsString('submitter@example.test', $approved);
        $this->assertStringNotContainsString('PII-FIRST', $approved);
        $this->assertStringNotContainsString('English question', $approved);

        DB::connection('legacy_mysql')->table('jx_faqs')->where('id', 2)->update(['answer' => 'Changed after approval']);
        $dryRun = app(LegacyFaqImportServiceInterface::class)->import($this->path($result->paths, 'approved_faqs.csv'));
        $this->assertSame(0, $dryRun->importableRows);
        $this->assertSame(1, $dryRun->skipReasonCounts['source_content_changed_after_review']);
    }

    public function test_import_gates_duplicates_mappings_category_conflicts_and_empty_approvals(): void
    {
        $service = app(LegacyFaqImportServiceInterface::class);
        Storage::disk('local')->put('empty.csv', $this->packet([$this->approval(1, 'ar', 1, '', '')]));
        $empty = $service->import('empty.csv', write: true, approval: 'legacy-faq-import');
        $this->assertSame(0, $empty->importedRows);
        $this->assertDatabaseCount('faq_categories', 0);

        Storage::disk('local')->put('duplicates.csv', $this->packet([$this->approval(1, 'ar', 1), $this->approval(6, 'ar', 1)]));
        $duplicates = $service->import('duplicates.csv');
        $this->assertSame(0, $duplicates->importableRows);
        $this->assertSame(2, $duplicates->skipReasonCounts['duplicate_approved_question']);

        $currentFaq = Faq::query()->create(['faq_category_id' => null, 'sort_order' => 0, 'is_enabled' => false, 'is_featured' => false]);
        FaqTranslation::query()->create(['faq_id' => $currentFaq->getKey(), 'locale' => 'en', 'question' => 'English question', 'answer' => 'Current answer', 'keywords' => null]);
        Storage::disk('local')->put('current.csv', $this->packet([$this->approval(2, 'en', 2)]));
        $current = $service->import('current.csv');
        $this->assertSame(1, $current->skipReasonCounts['current_question_conflict']);
        $currentFaq->forceDelete();

        MigrationLog::query()->create([
            'module' => 'old', 'batch_name' => 'old', 'source_table' => 'jx_faqs', 'source_id' => 8,
            'target_table' => 'faqs', 'target_id' => 88, 'status' => 'success',
        ]);
        Storage::disk('local')->put('mapped.csv', $this->packet([$this->approval(8, 'ar', 1)]));
        $mapped = $service->import('mapped.csv');
        $this->assertSame(1, $mapped->skipReasonCounts['already_mapped']);

        DB::table('faq_categories')->insert(['slug' => 'legacy-faq-review', 'sort_order' => 0, 'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('one.csv', $this->packet([$this->approval(2, 'en', 2)]));
        $conflict = $service->import('one.csv', write: true, approval: 'legacy-faq-import');
        $this->assertSame(1, $conflict->skipReasonCounts['review_category_conflict']);
        $this->assertDatabaseCount('faqs', 0);

        $this->expectException(InvalidArgumentException::class);
        $service->import('one.csv', write: true, approval: 'wrong');
    }

    public function test_import_rejects_approval_packets_without_content_hashes(): void
    {
        Storage::disk('local')->put(
            'missing-hashes.csv',
            "source_table,source_id,locale,legacy_lang,approval_decision,approved_target\njx_faqs,1,ar,1,import,faqs\n",
        );

        $this->expectException(InvalidArgumentException::class);
        app(LegacyFaqImportServiceInterface::class)->import('missing-hashes.csv');
    }

    private function seedRows(): void
    {
        $base = ['subject' => null, 'question' => null, 'answer' => null, 'faq_order' => 0, 'post_date' => '2024-01-01', 'is_visible' => 1, 'lang' => 1,
            'first_name' => null, 'last_name' => null, 'email' => null, 'country' => null, 'phone' => null];
        DB::connection('legacy_mysql')->table('jx_faqs')->insert([
            array_merge($base, ['id' => 1, 'subject' => '<b>Subject</b>', 'question' => '<p class="MsoNormal">Clean&nbsp;question</p><script>BAD</script>', 'answer' => '<!--[if gte mso 9]>WORD<![endif]--><p>Clean answer</p>', 'first_name' => 'PII-FIRST', 'last_name' => 'PII-LAST', 'email' => 'submitter@example.test', 'country' => 'PII-COUNTRY', 'phone' => '9639999999']),
            array_merge($base, ['id' => 2, 'lang' => 2, 'question' => '<p>English question</p>', 'answer' => '<p>English answer</p>']),
            array_merge($base, ['id' => 3, 'is_visible' => 0, 'subject' => 'BACKLOG_SECRET_SUBJECT', 'question' => 'BACKLOG_SECRET_QUESTION', 'answer' => 'BACKLOG_SECRET_ANSWER']),
            array_merge($base, ['id' => 4, 'lang' => 7, 'subject' => 'BACKLOG_SECRET_UNSUPPORTED', 'question' => 'BACKLOG_SECRET_Q7', 'answer' => 'BACKLOG_SECRET_A7']),
            array_merge($base, ['id' => 5, 'question' => 'Missing answer question', 'answer' => '']),
            array_merge($base, ['id' => 6, 'question' => 'Clean question', 'answer' => 'Duplicate answer']),
            array_merge($base, ['id' => 7, 'question' => 'Call 123 456 789 for details', 'answer' => 'Contact answer']),
            array_merge($base, ['id' => 8, 'question' => 'Mapped question', 'answer' => 'Mapped answer', 'post_date' => 'not-a-date']),
        ]);
    }

    /** @param list<array<string, string|int>> $rows */
    private function packet(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        $headers = ['source_table', 'source_id', 'locale', 'legacy_lang', 'approval_decision', 'approved_target', 'question_sha256', 'answer_sha256'];
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $id = (int) $row['source_id'];
            $row['question_sha256'] = hash('sha256', $this->cleanedFaqValue($id, 'question'));
            $row['answer_sha256'] = hash('sha256', $this->cleanedFaqValue($id, 'answer'));
            fputcsv($stream, array_map(static fn (string $header): mixed => $row[$header], $headers));
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private function cleanedFaqValue(int $id, string $field): string
    {
        $values = [
            1 => ['question' => 'Clean question', 'answer' => 'Clean answer'],
            2 => ['question' => 'English question', 'answer' => 'English answer'],
            6 => ['question' => 'Clean question', 'answer' => 'Duplicate answer'],
            7 => ['question' => 'Call 123 456 789 for details', 'answer' => 'Contact answer'],
            8 => ['question' => 'Mapped question', 'answer' => 'Mapped answer'],
        ];

        return $values[$id][$field] ?? 'missing-source-value';
    }

    /** @return array<string, string|int> */
    private function approval(int $id, string $locale, int $lang, string $decision = 'import', string $target = 'faqs'): array
    {
        return ['source_table' => 'jx_faqs', 'source_id' => $id, 'locale' => $locale, 'legacy_lang' => $lang, 'approval_decision' => $decision, 'approved_target' => $target];
    }

    /** @param list<string> $paths */
    private function path(array $paths, string $suffix): string
    {
        return (string) collect($paths)->first(fn (string $path): bool => str_ends_with($path, $suffix));
    }
}
