<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyQuarantineExportServiceInterface;
use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyQuarantineExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyQuarantineExportServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LegacyQuarantineExportServiceInterface::class);
    }

    public function test_export_writes_filtered_csv_for_editor_review(): void
    {
        Storage::fake('local');
        $this->createRejections();

        $result = $this->service->export(module: 'news', format: 'csv');

        $this->assertSame('local', $result->disk);
        $this->assertSame('csv', $result->format);
        $this->assertSame('news', $result->module);
        $this->assertSame(2, $result->rowCount);
        $this->assertSame(['news' => 2], $result->moduleCounts);
        $this->assertSame(1, $result->reasonCounts['unsafe_html']);
        $this->assertSame(1, $result->reasonCounts['legacy_internal_link']);

        Storage::disk('local')->assertExists($result->path);
        $csv = Storage::disk('local')->get($result->path);

        $this->assertStringContainsString('module,source_table,source_id,reason_code', $csv);
        $this->assertStringContainsString('news,jx_news,10,unsafe_html', $csv);
        $this->assertStringContainsString('/index.php?dir=news&page=news&ser=10', $csv);
        $this->assertStringNotContainsString('static_pages', $csv);
    }

    public function test_export_writes_json_with_summary_and_rows(): void
    {
        Storage::fake('local');
        $this->createRejections();

        $result = $this->service->export(reasonCode: 'unsafe_html', format: 'json');

        $this->assertSame(1, $result->rowCount);
        Storage::disk('local')->assertExists($result->path);

        $payload = json_decode(Storage::disk('local')->get($result->path), true);

        $this->assertSame('unsafe_html', $payload['summary']['reason_code']);
        $this->assertSame(1, $payload['summary']['row_count']);
        $this->assertSame('body', $payload['rows'][0]['field']);
        $this->assertSame('Original body', $payload['rows'][0]['original_preview']);
    }

    /** @return void */
    private function createRejections(): void
    {
        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_news',
            'source_id' => 10,
            'reason_code' => 'unsafe_html',
            'reason_message' => 'body: unsafe HTML requires review.',
            'raw_summary' => [
                'field' => 'body',
                'decision' => 'quarantine',
                'issue_codes' => ['unsafe_html'],
                'original_preview' => 'Original body',
                'cleaned_preview' => 'Cleaned body',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_news',
            'source_id' => 10,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'jx_news.body contains legacy internal link.',
            'raw_summary' => [
                'field' => 'body',
                'legacy_path' => '/index.php?dir=news&page=news&ser=10',
                'review_type' => 'internal_link_continuity',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'static_pages',
            'source_table' => 'jx_pages',
            'source_id' => 5,
            'reason_code' => 'missing_required_value',
            'reason_message' => 'title: missing required title.',
            'raw_summary' => ['field' => 'title'],
        ]);
    }
}
