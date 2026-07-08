<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyInternalLinkExtractionResultDTO;

interface LegacyInternalLinkExtractionServiceInterface
{
    public function extract(string $module, bool $recordReviewRows = false, ?int $limit = null): LegacyInternalLinkExtractionResultDTO;
}
