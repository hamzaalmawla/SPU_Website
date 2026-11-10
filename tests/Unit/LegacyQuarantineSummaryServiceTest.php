<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyQuarantineSummaryServiceInterface;
use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyQuarantineSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_export_groups_rows_and_marks_decision_items(): void
    {
        Storage::fake('local');
        $this->createRejections();

        $result = app(LegacyQuarantineSummaryServiceInterface::class)->export(module: 'news');

        $this->assertSame('news', $result->module);
        $this->assertSame(5, $result->rowCount);
        $this->assertSame(5, $result->summaryGroupCount);
        $this->assertSame(0, $result->needsDecisionGroupCount);
        $this->assertCount(3, $result->paths);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $markdown = Storage::disk('local')->get($result->paths[0]);
        $groups = Storage::disk('local')->get($result->paths[1]);
        $decisions = Storage::disk('local')->get($result->paths[2]);

        $this->assertStringContainsString('Legacy Quarantine Review Summary', $markdown);
        $this->assertStringContainsString('auto_redirect_candidate', $groups);
        $this->assertStringContainsString('auto_skip_invalid_contact_until_verified', $groups);
        $this->assertStringContainsString('/en', $groups);
        $this->assertStringContainsString('auto_approve_cleaned', $groups);
        $this->assertStringContainsString('module,reason_code,suggested_action', $decisions);
        $this->assertStringNotContainsString('base64_inline_image', $decisions);
        $this->assertStringNotContainsString('invalid_email', $decisions);
        $this->assertStringContainsString('Embedded image is stored inside old HTML', $groups);
        $this->assertStringNotContainsString('word cleanup', $decisions);
    }

    private function createRejections(): void
    {
        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 1,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'field' => 'ar_data',
                'legacy_path' => '/index.php?lang=1',
                'review_type' => 'internal_link_continuity',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 2,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'field' => 'en_data',
                'legacy_path' => '/index.php?lang=1',
                'review_type' => 'internal_link_continuity',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 3,
            'reason_code' => 'base64_inline_image',
            'reason_message' => 'inline image',
            'raw_summary' => [
                'field' => 'ar_data',
                'issue_codes' => ['base64_inline_image', 'html_sanitized'],
                'original_preview' => '<p><img src="data:image/png;base64,aaa"></p>',
                'cleaned_preview' => '<p></p>',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 4,
            'reason_code' => 'word_html_cleaned',
            'reason_message' => 'word cleanup',
            'raw_summary' => [
                'field' => 'ar_data',
                'issue_codes' => ['word_html_cleaned'],
                'original_preview' => '<p class="MsoNormal">Text</p>',
                'cleaned_preview' => '<p>Text</p>',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_admins',
            'source_id' => 5,
            'reason_code' => 'invalid_email',
            'reason_message' => 'invalid email',
            'raw_summary' => [
                'field' => 'email',
                'original_preview' => 'person@example.edu Phone',
            ],
        ]);
    }
}
