<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyReviewCandidateReportResultDTO;

interface LegacyReviewCandidateReportServiceInterface
{
    public function export(
        ?string $module = null,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/review-candidates',
    ): LegacyReviewCandidateReportResultDTO;
}
