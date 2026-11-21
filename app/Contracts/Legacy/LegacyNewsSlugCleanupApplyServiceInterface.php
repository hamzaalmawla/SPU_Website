<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyNewsSlugCleanupApplyResultDTO;

interface LegacyNewsSlugCleanupApplyServiceInterface
{
    public function apply(?int $limit = null): LegacyNewsSlugCleanupApplyResultDTO;
}
