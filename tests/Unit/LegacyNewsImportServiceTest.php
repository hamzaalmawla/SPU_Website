<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
    }

    public function test_write_requires_news_approval_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LegacyNewsImportServiceInterface::class)->import(write: true, approval: 'wrong');
    }

    public function test_news_import_is_dry_run_by_default_and_replay_safe(): void
    {
        $service = app(LegacyNewsImportServiceInterface::class);
        $dryRun = $service->import(batch: 'news-dry-run');

        $this->assertSame(3, $dryRun->scannedRows);
        $this->assertSame(2, $dryRun->importableRows);
        $this->assertSame(0, NewsArticle::query()->count());

        $written = $service->import(write: true, approval: 'phase6-news', batch: 'news-write');
        $replayed = $service->import(write: true, approval: 'phase6-news', batch: 'news-replay');

        $this->assertSame(2, $written->importedRows);
        $this->assertSame(4, $written->createdTranslations);
        $this->assertSame(1, $written->createdAttachments);
        $this->assertSame(0, $replayed->importedRows);
        $this->assertSame(2, NewsArticle::query()->count());
        $this->assertSame(4, NewsArticleTranslation::query()->count());
        $this->assertSame(1, NewsArticleAttachment::query()->whereNull('media_asset_id')->count());
        $this->assertSame(2, MigrationLog::query()->where('module', 'news')->where('status', 'success')->count());
        $this->assertSame('خبر تجريبي', NewsArticleTranslation::query()->where('locale', 'en')->orderBy('id')->value('title'));
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
        app('db')->connection('legacy_mysql')->table('jx_categories')->insert([
            [
                'id' => 1,
                'service_type' => 3,
                'ar_name' => 'خبر تجريبي',
                'en_name' => '',
                'ar_brief' => 'ملخص',
                'en_brief' => '',
                'ar_data' => '<p>محتوى</p>',
                'en_data' => '',
                'is_visible' => 1,
                'category_order' => 1,
            ],
            [
                'id' => 2,
                'service_type' => 4,
                'ar_name' => '',
                'en_name' => 'Test announcement',
                'ar_brief' => '',
                'en_brief' => 'Summary',
                'ar_data' => '',
                'en_data' => '<p>Body</p>',
                'is_visible' => 0,
                'category_order' => 2,
            ],
            [
                'id' => 3,
                'service_type' => 3,
                'ar_name' => '',
                'en_name' => 'under construction',
                'ar_brief' => '',
                'en_brief' => '',
                'ar_data' => '',
                'en_data' => '',
                'is_visible' => 1,
                'category_order' => 3,
            ],
        ]);
        app('db')->connection('legacy_mysql')->table('jx_items')->insert([
            'id' => 10,
            'category_id' => 1,
            'ar_name' => 'مرفق',
            'en_name' => 'Attachment',
            'photo' => null,
            'ar_file' => 'legacy-document.pdf',
            'en_file' => null,
            'item_order' => 1,
        ]);
    }
}
