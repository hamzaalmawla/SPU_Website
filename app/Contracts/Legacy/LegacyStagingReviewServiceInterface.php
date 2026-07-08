<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyStagingReviewResultDTO;

interface LegacyStagingReviewServiceInterface
{
    public function build(
        ?string $module = null,
        bool $write = false,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/staging-review',
    ): LegacyStagingReviewResultDTO;
}
