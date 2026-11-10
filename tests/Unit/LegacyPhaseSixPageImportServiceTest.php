<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPhaseSixPageImportServiceTest extends TestCase
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
        config()->set('old_database.cleaning_inspection_fields.static_pages', [[
            'table' => 'jx_site_static_pages',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'ar_page_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_page_data', 'type' => 'html', 'required' => false],
                ['column' => 'ar_comment', 'type' => 'text', 'required' => false],
                ['column' => 'en_comment', 'type' => 'text', 'required' => false],
                ['column' => 'ar_brief', 'type' => 'text', 'required' => false],
                ['column' => 'en_brief', 'type' => 'text', 'required' => false],
            ],
        ]]);

        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_site_static_pages', function ($table): void {
            $table->increments('id');
            $table->text('ar_page_data')->nullable();
            $table->text('en_page_data')->nullable();
            $table->string('ar_comment')->nullable();
            $table->string('en_comment')->nullable();
            $table->string('ar_brief')->nullable();
            $table->string('en_brief')->nullable();
        });
        app('db')->connection('legacy_mysql')->table('jx_site_static_pages')->insert([
            'id' => 10,
            'ar_page_data' => '<p>محتوى</p>',
            'en_page_data' => '<p>Body</p>',
            'ar_comment' => 'تعليق',
            'en_comment' => 'Comment',
            'ar_brief' => 'صفحة قديمة',
            'en_brief' => 'Legacy Page',
        ]);
        $this->createApprovedReviewItem();
    }

    public function test_dry_run_does_not_create_pages(): void
    {
        $result = app(LegacyPhaseSixPageImportServiceInterface::class)->import(batch: 'pages-test');

        $this->assertFalse($result->written);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(0, Page::query()->count());
    }

    public function test_write_requires_approval_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LegacyPhaseSixPageImportServiceInterface::class)->import(write: true, approval: 'wrong');
    }

    public function test_write_imports_disabled_draft_page_and_is_idempotent(): void
    {
        $service = app(LegacyPhaseSixPageImportServiceInterface::class);

        $first = $service->import(write: true, approval: 'phase6-pages', batch: 'pages-test');
        $second = $service->import(write: true, approval: 'phase6-pages', batch: 'pages-test-2');

        $this->assertSame(1, $first->importedRows);
        $this->assertSame(1, $first->createdPages);
        $this->assertSame(2, $first->createdTranslations);
        $this->assertSame(1, Page::query()->where('status', 'draft')->where('is_enabled', false)->count());
        $this->assertSame(2, PageTranslation::query()->count());
        $this->assertSame(1, MigrationLog::query()->where('status', 'success')->count());
        $this->assertSame(0, $second->importedRows);
        $this->assertSame(1, $second->skipReasonCounts['already_imported']);
        $this->assertSame('pages', LegacyContentMapping::query()->value('target_table'));
        $this->assertNotNull(LegacyContentMapping::query()->value('target_id'));
    }

    private function createApprovedReviewItem(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'static_pages',
            'source_table' => 'jx_site_static_pages',
            'source_id' => 10,
            'legacy_key' => 'page:10',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'approved',
            'target_module' => 'static_pages',
            'target_type' => 'archive_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
        LegacyReviewItem::query()->create([
            'module' => 'static_pages',
            'source_table' => 'jx_site_static_pages',
            'source_id' => 10,
            'legacy_key' => 'page:10',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'approved',
            'review_status' => 'mapping_already_approved',
            'target_module' => 'static_pages',
            'target_type' => 'archive_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_continuity_review',
            'blocked_reasons' => [],
        ]);
    }
}
