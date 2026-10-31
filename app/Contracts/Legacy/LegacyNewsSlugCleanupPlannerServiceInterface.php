<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyNewsSlugCleanupPlanDTO;

interface LegacyNewsSlugCleanupPlannerServiceInterface
{
    public function plan(?int $limit = 50, int $maxSlugLength = 80): LegacyNewsSlugCleanupPlanDTO;
}
