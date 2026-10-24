<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyCleanedRowServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyCleanedRowServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('old_database.cleaning_inspection_fields', [
            'news' => [[
                'table' => 'jx_categories',
                'id_column' => 'id',
                'fields' => [
                    ['column' => 'ar_name', 'type' => 'text', 'required' => true],
                    ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                    ['column' => 'start_date', 'type' => 'date', 'required' => false],
                ],
            ]],
        ]);

        $this->service = app(LegacyCleanedRowServiceInterface::class);
    }

    public function test_clean_row_blocks_unsafe_html_without_approved_decision_action(): void
    {
        $result = $this->service->cleanRow('news', 'jx_categories', [
            'ar_name' => "  خبر\u{00A0}مهم  ",
            'ar_data' => '<p>Keep</p><script>alert(1)</script>',
            'start_date' => '0000-00-00',
        ]);

        $this->assertFalse($result->canImportPublicly);
        $this->assertSame(['ar_data'], $result->blockedFields);
        $this->assertSame('خبر مهم', $result->values['ar_name']);
        $this->assertNull($result->values['start_date']);
        $this->assertArrayNotHasKey('ar_data', $result->values);
        $this->assertSame(1, $result->issueCounts['unsafe_html']);
    }

    public function test_clean_row_allows_sanitized_html_with_approved_decision_action(): void
    {
        $result = $this->service->cleanRow('news', 'jx_categories', [
            'ar_name' => 'خبر مهم',
            'ar_data' => '<p>Keep</p><script>alert(1)</script>',
            'start_date' => '2024-01-02',
        ], [
            'ar_data' => 'auto_accept_sanitized_html',
        ]);

        $this->assertTrue($result->canImportPublicly);
        $this->assertSame([], $result->blockedFields);
        $this->assertSame('<p>Keep</p>', $result->values['ar_data']);
        $this->assertSame('2024-01-02', $result->values['start_date']);
        $this->assertSame('auto_accept_sanitized_html', collect($result->decisions)->firstWhere('field', 'ar_data')['approved_action']);
    }

    public function test_clean_row_ignores_unconfigured_tables(): void
    {
        $result = $this->service->cleanRow('news', 'jx_items', ['ar_name' => 'Raw']);

        $this->assertTrue($result->canImportPublicly);
        $this->assertSame([], $result->values);
        $this->assertSame([], $result->decisions);
    }
}
