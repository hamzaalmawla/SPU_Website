<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyNewsImportServiceTest extends TestCase
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
        app(OldDatabaseConnection::class)->connection();
        $this->createLegacyTables();
        $this->insertLegacyRows();
        Storage::fake('local');
    }

    public function test_write_requires_news_approval_token(): void
    {
        Storage::disk('local')->put('reviewed.csv', $this->packet([
            $this->approvalRow(1, 3),
        ]));

        $this->expectException(InvalidArgumentException::class);

        app(LegacyNewsImportServiceInterface::class)->import(write: true, approval: 'wrong', input: 'reviewed.csv');
    }

    public function test_write_requires_an_approval_packet_and_inputless_dry_run_is_safe(): void
    {
        $service = app(LegacyNewsImportServiceInterface::class);
        $dryRun = $service->import(batch: 'safe-empty');

        $this->assertSame(0, $dryRun->scannedRows);
        $this->assertSame(0, $dryRun->importableRows);
        $this->assertDatabaseCount('news_articles', 0);

        $this->expectException(InvalidArgumentException::class);
        $service->import(write: true, approval: 'phase6-news');
    }

    public function test_news_import_uses_only_approved_ids_and_quarantines_replay_safe_articles(): void
    {
        Storage::disk('local')->put('private/reviewed-news.csv', $this->packet([
            $this->approvalRow(1, 3),
            $this->approvalRow(2, 4),
            $this->approvalRow(3, 3),
            $this->approvalRow(4, 3, decision: '', target: ''),
            $this->approvalRow(99, 3),
        ]));
        $service = app(LegacyNewsImportServiceInterface::class);

        $dryRun = $service->import(batch: 'news-dry-run', input: 'private/reviewed-news.csv');

        $this->assertSame(5, $dryRun->scannedRows);
        $this->assertSame(2, $dryRun->importableRows);
        $this->assertSame(3, $dryRun->skippedRows);
        $this->assertSame(1, $dryRun->skipReasonCounts['blank_approval_decision']);
        $this->assertSame(1, $dryRun->skipReasonCounts['missing_translation']);
        $this->assertSame(1, $dryRun->skipReasonCounts['missing_source']);
        $this->assertDatabaseCount('news_articles', 0);

        $written = $service->import(
            write: true,
            approval: 'phase6-news',
            batch: 'news-write',
            input: 'private/reviewed-news.csv',
        );
        $replayed = $service->import(
            write: true,
            approval: 'phase6-news',
            batch: 'news-replay',
            input: 'private/reviewed-news.csv',
        );

        $this->assertSame(2, $written->importedRows);
        $this->assertSame(2, $written->createdTranslations);
        $this->assertSame(1, $written->createdAttachments);
        $this->assertSame(0, $replayed->importedRows);
        $this->assertDatabaseCount('news_articles', 2);
        $this->assertDatabaseCount('news_article_translations', 2);
        $this->assertSame(['ar'], NewsArticleTranslation::query()->where('news_article_id', 1)->pluck('locale')->all());
        $this->assertSame(['en'], NewsArticleTranslation::query()->where('news_article_id', 2)->pluck('locale')->all());
        $this->assertStringNotContainsString('script', (string) NewsArticleTranslation::query()->where('news_article_id', 1)->value('body'));
        $this->assertSame(0, NewsArticle::query()->where('status', '!=', 'draft')->count());
        $this->assertSame(0, NewsArticle::query()->where('is_enabled', true)->count());
        $this->assertSame(0, NewsArticle::query()->whereNotNull('published_at')->count());
        $this->assertSame(0, NewsArticle::query()->whereNotNull('scheduled_at')->count());
        $this->assertSame(2, NewsArticleSeoMeta::query()->where('robots', 'noindex,nofollow')->count());
        $this->assertSame(1, NewsArticleAttachment::query()->whereNull('media_asset_id')->count());
        $this->assertSame(2, MigrationLog::query()->where('module', 'news')->where('status', 'success')->count());
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get('private/reviewed-news.csv')),
            MigrationLog::query()->where('module', 'news')->where('status', 'success')->firstOrFail()->metadata['approval_packet']['sha256'],
        );
        $this->assertDatabaseCount('legacy_exact_redirects', 0);

        $metadata = MigrationLog::query()->where('source_id', 1)->where('status', 'success')->firstOrFail()->metadata;
        $this->assertSame('private/reviewed-news.csv', $metadata['approval_packet']['path']);
        $this->assertSame(1, $metadata['legacy_visibility']);
        $this->assertSame('2024-01-10 12:00:00', $metadata['legacy_dates']['start_normalized']);
    }

    public function test_write_with_no_approved_rows_does_not_create_categories(): void
    {
        Storage::disk('local')->put('blank-review.csv', $this->packet([
            $this->approvalRow(1, 3, decision: '', target: ''),
        ]));

        $result = app(LegacyNewsImportServiceInterface::class)->import(
            write: true,
            approval: 'phase6-news',
            input: 'blank-review.csv',
        );

        $this->assertSame(0, $result->importedRows);
        $this->assertDatabaseCount('news_categories', 0);
        $this->assertDatabaseCount('news_articles', 0);
    }

    public function test_packet_mismatches_and_duplicate_approvals_are_rejected_before_import(): void
    {
        Storage::disk('local')->put('unsafe.csv', $this->packet([
            $this->approvalRow(1, 3),
            $this->approvalRow(1, 3),
            $this->approvalRow(2, 3),
            $this->approvalRow(3, 3, sourceTable: 'other'),
            $this->approvalRow(5, 3, subsite: 'admin'),
            $this->approvalRow(0, 3),
            $this->approvalRow(6, 9),
            $this->approvalRow(7, 3, decision: '', target: ''),
            $this->approvalRow(7, 3),
        ]));

        $result = app(LegacyNewsImportServiceInterface::class)->import(input: 'unsafe.csv');

        $this->assertSame(9, $result->scannedRows);
        $this->assertSame(0, $result->importableRows);
        $this->assertSame(4, $result->skipReasonCounts['duplicate_source_id']);
        $this->assertSame(1, $result->skipReasonCounts['source_service_mismatch']);
        $this->assertSame(1, $result->skipReasonCounts['source_table_mismatch']);
        $this->assertSame(1, $result->skipReasonCounts['subsite_mismatch']);
        $this->assertSame(1, $result->skipReasonCounts['invalid_source_id']);
        $this->assertSame(1, $result->skipReasonCounts['invalid_service_type']);
        $this->assertDatabaseCount('news_articles', 0);
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_mysql')->create('jx_categories', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('service_type');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_brief')->nullable();
            $table->text('en_brief')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
            $table->string('photo')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('category_order')->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
        });
        Schema::connection('legacy_mysql')->create('jx_items', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->string('photo')->nullable();
            $table->string('ar_file')->nullable();
            $table->string('en_file')->nullable();
            $table->integer('item_order')->default(0);
        });
    }

    private function insertLegacyRows(): void
    {
        $base = [
            'ar_name' => null, 'en_name' => null, 'ar_brief' => null, 'en_brief' => null,
            'ar_data' => null, 'en_data' => null, 'photo' => null, 'url' => null,
            'is_visible' => 1, 'category_order' => 0, 'start_date' => null, 'end_date' => null,
        ];
        app('db')->connection('legacy_mysql')->table('jx_categories')->insert([
            array_merge($base, [
                'id' => 1, 'service_type' => 3, 'ar_name' => 'خبر تجريبي', 'en_name' => '',
                'ar_brief' => 'ملخص', 'en_brief' => '', 'ar_data' => '<p>محتوى<script>bad()</script></p>',
                'en_data' => '', 'is_visible' => 1, 'category_order' => 1, 'start_date' => '2024-01-10 12:00:00',
            ]),
            array_merge($base, [
                'id' => 2, 'service_type' => 4, 'ar_name' => '', 'en_name' => 'Test announcement',
                'ar_brief' => '', 'en_brief' => 'Summary', 'ar_data' => '', 'en_data' => '<p>Body</p>',
                'is_visible' => 0, 'category_order' => 2,
            ]),
            array_merge($base, [
                'id' => 3, 'service_type' => 3, 'ar_name' => '', 'en_name' => 'under construction',
                'ar_brief' => '', 'en_brief' => '', 'ar_data' => '', 'en_data' => '',
                'is_visible' => 1, 'category_order' => 3,
            ]),
            array_merge($base, [
                'id' => 4, 'service_type' => 3, 'ar_name' => 'غير معتمد', 'en_name' => 'Not approved',
                'ar_brief' => '', 'en_brief' => '', 'ar_data' => '', 'en_data' => '',
                'is_visible' => 1, 'category_order' => 4,
            ]),
        ]);
        app('db')->connection('legacy_mysql')->table('jx_items')->insert([
            'id' => 10, 'category_id' => 1, 'ar_name' => 'مرفق', 'en_name' => 'Attachment',
            'photo' => null, 'ar_file' => 'legacy-document.pdf', 'en_file' => null, 'item_order' => 1,
        ]);
    }

    /** @param list<array<string, string|int>> $rows */
    private function packet(array $rows): string
    {
        $headers = ['source_table', 'source_id', 'subsite', 'service_type', 'approval_decision', 'approved_target'];
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): string|int => $row[$header], $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /** @return array<string, string|int> */
    private function approvalRow(
        int $id,
        int $service,
        string $decision = 'import',
        string $target = 'news',
        string $sourceTable = 'jx_categories',
        string $subsite = 'root',
    ): array {
        return [
            'source_table' => $sourceTable,
            'source_id' => $id,
            'subsite' => $subsite,
            'service_type' => $service,
            'approval_decision' => $decision,
            'approved_target' => $target,
        ];
    }
}
