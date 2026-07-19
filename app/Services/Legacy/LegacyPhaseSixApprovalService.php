<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixApprovalResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyPhaseSixApprovalService implements LegacyPhaseSixApprovalServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-menu-links';

    private const PAGES_APPROVAL_TOKEN = 'phase6-pages';

    public function approveMenuLinks(bool $write = false, ?string $approval = null): LegacyPhaseSixApprovalResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Approving Phase 6 menu links requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        return $this->approveLane(
            lane: 'menu_links',
            sourceTable: 'jx_sites',
            classification: 'redirect_to_equivalent',
            write: $write,
        );
    }

    public function approvePages(bool $write = false, ?string $approval = null): LegacyPhaseSixApprovalResultDTO
    {
        if ($write && $approval !== self::PAGES_APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Approving Phase 6 pages requires --approve='.self::PAGES_APPROVAL_TOKEN.'.');
        }

        return $this->approveLane(
            lane: 'pages',
            sourceTable: 'jx_site_static_pages',
            classification: 'archive_now_remodel_later',
            write: $write,
        );
    }

    private function approveLane(string $lane, string $sourceTable, string $classification, bool $write): LegacyPhaseSixApprovalResultDTO
    {
        $rows = LegacyReviewItem::query()
            ->where('source_table', $sourceTable)
            ->orderBy('source_id')
            ->get();
        $approvable = [];
        $blockedRows = 0;
        $blockerCounts = [];

        foreach ($rows as $row) {
            $blockers = $this->blockers($row, $classification);

            if ($blockers === []) {
                $approvable[] = $row;

                continue;
            }

            $blockedRows++;

            foreach ($blockers as $blocker) {
                $blockerCounts[$blocker] = ($blockerCounts[$blocker] ?? 0) + 1;
            }
        }

        $approvedRows = 0;

        if ($write) {
            DB::transaction(function () use ($approvable, $sourceTable, &$approvedRows): void {
                foreach ($approvable as $row) {
                    LegacyContentMapping::query()
                        ->where('source_table', $sourceTable)
                        ->where('source_id', $row->source_id)
                        ->where('mapping_status', 'proposed')
                        ->update([
                            'mapping_status' => 'approved',
                            'approved_at' => now(),
                        ]);

                    $row->forceFill([
                        'mapping_status' => 'approved',
                        'review_status' => 'mapping_already_approved',
                    ])->save();
                    $approvedRows++;
                }
            });
        }

        return new LegacyPhaseSixApprovalResultDTO(
            lane: $lane,
            written: $write,
            scannedRows: $rows->count(),
            approvableRows: count($approvable),
            approvedRows: $approvedRows,
            blockedRows: $blockedRows,
            blockerCounts: $blockerCounts,
        );
    }

    /** @return array<int, string> */
    private function blockers(LegacyReviewItem $row, string $classification): array
    {
        $blockers = [];

        if ((string) $row->review_status === 'mapping_already_approved' && (string) $row->mapping_status === 'approved') {
            return [];
        }

        if ((string) $row->review_status !== 'review_candidate') {
            $blockers[] = 'not_review_candidate';
        }

        if ((string) $row->mapping_status !== 'proposed') {
            $blockers[] = 'not_proposed_mapping';
        }

        if ((string) $row->classification !== $classification) {
            $blockers[] = 'unexpected_classification';
        }

        if (! in_array((string) $row->file_dependency, ['', 'none'], true)) {
            $blockers[] = 'blocked_file_dependency';
        }

        foreach ($row->blocked_reasons ?? [] as $reason) {
            $blockers[] = $reason;
        }

        return array_values(array_unique($blockers));
    }
}
