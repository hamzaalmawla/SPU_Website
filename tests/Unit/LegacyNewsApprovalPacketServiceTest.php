<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsApprovalPacketServiceInterface;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyNewsApprovalPacketServiceTest extends TestCase
{
    public function test_builder_approves_only_visible_complete_unique_root_news_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('service3.csv', $this->packet([
            $this->row(1, 'First safe story', contentLength: 100),
            $this->row(2, 'Duplicate title', contentLength: 50),
            $this->row(3, 'Duplicate title', contentLength: 60),
            $this->row(4, 'Hidden story', visible: 0, contentLength: 70, blockers: 'hidden_source'),
        ]));

        $result = app(LegacyNewsApprovalPacketServiceInterface::class)->build(
            inputs: ['service3.csv'],
            approvedBy: 'project-owner',
        );

        $this->assertSame(4, $result->scannedRows);
        $this->assertSame(1, $result->approvedRows);
        $this->assertSame(3, $result->rejectedRows);
        $this->assertSame(1, $result->serviceCounts[3]);
        $this->assertSame(2, $result->rejectionCounts['duplicate_en_name']);
        $this->assertSame(1, $result->rejectionCounts['hidden_source']);

        $approved = Storage::disk('local')->get($result->paths[0]);
        $rejected = Storage::disk('local')->get($result->paths[1]);
        $manifest = Storage::disk('local')->get($result->paths[2]);
        $this->assertStringContainsString('import,news', $approved);
        $this->assertStringContainsString('project-owner', $approved);
        $this->assertStringNotContainsString('Hidden story', $approved);
        $this->assertStringContainsString('duplicate_en_name', $rejected);
        $this->assertStringContainsString('"writes_content": false', $manifest);
    }

    public function test_builder_can_explicitly_approve_arabic_fallback_without_creating_an_english_translation(): void
    {
        Storage::fake('local');
        $row = $this->row(10, 'Under Construction', contentLength: 100, blockers: 'under_construction_translation');
        $row['ar_name'] = 'خبر عربي موثق';
        $row['ar_content_length'] = 120;
        Storage::disk('local')->put('fallback.csv', $this->packet([$row]));

        $result = app(LegacyNewsApprovalPacketServiceInterface::class)->build(
            inputs: ['fallback.csv'],
            approvedBy: 'project-owner',
            allowArabicFallback: true,
        );

        $this->assertSame(1, $result->approvedRows);
        $approved = Storage::disk('local')->get($result->paths[0]);
        $manifest = Storage::disk('local')->get($result->paths[2]);
        $this->assertStringContainsString('arabic_fallback_approved', $approved);
        $this->assertStringContainsString('"allow_arabic_fallback": true', $manifest);
    }

    /** @param list<array<string, string|int>> $rows */
    private function packet(array $rows): string
    {
        $headers = [
            'source_table', 'source_id', 'subsite', 'service_type', 'context_semantic', 'recommended_action',
            'blockers', 'approval_decision', 'approved_target', 'ar_name', 'en_name', 'is_visible', 'is_link',
            'ar_content_length', 'en_content_length', 'child_total_count', 'is_orphan', 'existing_target_mapping',
        ];
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): string|int => $row[$header], $headers));
        }

        rewind($stream);
        $payload = stream_get_contents($stream);
        fclose($stream);

        return is_string($payload) ? $payload : '';
    }

    /** @return array<string, string|int> */
    private function row(int $id, string $title, int $visible = 1, int $contentLength = 0, string $blockers = ''): array
    {
        return [
            'source_table' => 'jx_categories', 'source_id' => $id, 'subsite' => 'root', 'service_type' => '3',
            'context_semantic' => 'news', 'recommended_action' => 'news_import_review', 'blockers' => $blockers,
            'approval_decision' => '', 'approved_target' => '', 'ar_name' => '', 'en_name' => $title,
            'is_visible' => $visible, 'is_link' => 0, 'ar_content_length' => 0, 'en_content_length' => $contentLength,
            'child_total_count' => 0, 'is_orphan' => 0, 'existing_target_mapping' => 0,
        ];
    }
}
