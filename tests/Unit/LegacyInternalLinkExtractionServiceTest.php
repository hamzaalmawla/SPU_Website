<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyInternalLinkExtractionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyInternalLinkExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyInternalLinkExtractionServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_internal_links_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_internal_links_testing', $connection);
        config()->set('old_database.internal_link_extraction_fields', [
            'pages' => [[
                'table' => 'jx_pages',
                'id_column' => 'id',
                'columns' => ['body', 'url'],
            ]],
        ]);
        DB::purge('legacy_internal_links_testing');

        $this->service = app(LegacyInternalLinkExtractionServiceInterface::class);
    }

    public function test_extracts_internal_links_without_writing_by_default(): void
    {
        $this->createLegacyPagesTable();

        $result = $this->service->extract('pages');

        $this->assertSame('internal_links_found', $result->status);
        $this->assertFalse($result->recordedReviewRows);
        $this->assertSame(2, $result->scannedRows);
        $this->assertSame(4, $result->scannedFields);
        $this->assertSame(5, $result->extractedLinks);
        $this->assertSame(5, $result->uniqueLinks);
        $this->assertContains('/index.php?page=show&dir=news&service=3&cat_id=10&lang=1', $result->sampleLinks);
        $this->assertContains('/med/index.php?page=show&dir=items&cat_id=8&ser=1&lang=2', $result->sampleLinks);
        $this->assertContains('/downloads/files/guide.pdf', $result->sampleLinks);
        $this->assertContains('/images/news/photo.jpg', $result->sampleLinks);
        $this->assertContains('/dent/index.php?page=show&dir=items&cat_id=9', $result->sampleLinks);
        $this->assertDatabaseCount('migration_rejections', 0);
    }

    public function test_can_record_review_rows_duplicate_safely(): void
    {
        $this->createLegacyPagesTable();

        $first = $this->service->extract('pages', recordReviewRows: true);
        $second = $this->service->extract('pages', recordReviewRows: true);

        $this->assertSame(5, $first->recordedRows);
        $this->assertSame(0, $second->recordedRows);
        $this->assertDatabaseCount('migration_rejections', 5);
        $this->assertDatabaseHas('migration_rejections', [
            'module' => 'pages',
            'source_table' => 'jx_pages',
            'source_id' => 1,
            'reason_code' => 'legacy_internal_link',
        ]);
    }

    public function test_reports_no_internal_links_when_only_external_links_exist(): void
    {
        Schema::connection('legacy_internal_links_testing')->create('jx_pages', function ($schema): void {
            $schema->increments('id');
            $schema->text('body')->nullable();
            $schema->string('url')->nullable();
        });

        DB::connection('legacy_internal_links_testing')->table('jx_pages')->insert([
            ['body' => '<a href="https://example.com/outside">Outside</a><a href="mailto:test@example.com">Email</a>', 'url' => 'https://external.example/path'],
        ]);

        $result = $this->service->extract('pages');

        $this->assertSame('no_internal_links_found', $result->status);
        $this->assertSame(0, $result->extractedLinks);
    }

    public function test_unknown_module_reports_unconfigured_status(): void
    {
        $result = $this->service->extract('missing');

        $this->assertSame('unknown_or_unconfigured_module', $result->status);
        $this->assertSame(['No internal link extraction fields are configured for this module.'], $result->warnings);
    }

    private function createLegacyPagesTable(): void
    {
        Schema::connection('legacy_internal_links_testing')->create('jx_pages', function ($schema): void {
            $schema->increments('id');
            $schema->text('body')->nullable();
            $schema->string('url')->nullable();
        });

        DB::connection('legacy_internal_links_testing')->table('jx_pages')->insert([
            [
                'body' => '<a href="https://spu.edu.sy/index.php?page=show&amp;dir=news&amp;service=3&amp;cat_id=10&amp;lang=1">News</a><img src="/images/news/photo.jpg"><a href="https://example.com/external">External</a>',
                'url' => 'downloads/files/guide.pdf',
            ],
            [
                'body' => '<a href="/med/index.php?page=show&dir=items&cat_id=8&ser=1&lang=2">Med</a><a href="javascript:alert(1)">Bad</a>',
                'url' => 'dent/index.php?page=show&dir=items&cat_id=9',
            ],
        ]);
    }
}
