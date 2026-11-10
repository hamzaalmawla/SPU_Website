<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPhaseSixApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_approve_menu_links(): void
    {
        $this->createMenuLinkCandidate();

        $result = app(LegacyPhaseSixApprovalServiceInterface::class)->approveMenuLinks();

        $this->assertFalse($result->written);
        $this->assertSame(1, $result->approvableRows);
        $this->assertSame(0, $result->approvedRows);
        $this->assertSame('proposed', LegacyContentMapping::query()->value('mapping_status'));
    }

    public function test_write_requires_approval_token(): void
    {
        $this->createMenuLinkCandidate();

        $this->expectException(InvalidArgumentException::class);

        app(LegacyPhaseSixApprovalServiceInterface::class)->approveMenuLinks(write: true, approval: 'wrong');
    }

    public function test_write_approves_menu_link_candidates(): void
    {
        $this->createMenuLinkCandidate();

        $result = app(LegacyPhaseSixApprovalServiceInterface::class)->approveMenuLinks(write: true, approval: 'phase6-menu-links');

        $this->assertTrue($result->written);
        $this->assertSame(1, $result->approvedRows);
        $this->assertSame('approved', LegacyContentMapping::query()->value('mapping_status'));
        $this->assertSame('approved', LegacyReviewItem::query()->value('mapping_status'));
        $this->assertSame('mapping_already_approved', LegacyReviewItem::query()->value('review_status'));
    }

    public function test_write_approves_page_candidates(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'static_pages',
            'source_table' => 'jx_site_static_pages',
            'source_id' => 10,
            'legacy_key' => 'page:10',
            'classification' => 'archive_now_remodel_later',
            'mapping_status' => 'proposed',
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
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'static_pages',
            'target_type' => 'archive_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_continuity_review',
            'blocked_reasons' => [],
        ]);

        $result = app(LegacyPhaseSixApprovalServiceInterface::class)->approvePages(write: true, approval: 'phase6-pages');

        $this->assertSame('pages', $result->lane);
        $this->assertSame(1, $result->approvedRows);
        $this->assertSame('approved', LegacyContentMapping::query()->value('mapping_status'));
    }

    private function createMenuLinkCandidate(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
        LegacyReviewItem::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'proposed',
            'review_status' => 'review_candidate',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_redirect_review',
            'blocked_reasons' => [],
        ]);
    }
}
