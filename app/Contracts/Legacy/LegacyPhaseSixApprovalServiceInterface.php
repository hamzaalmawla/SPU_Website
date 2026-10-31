<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPhaseSixApprovalResultDTO;

interface LegacyPhaseSixApprovalServiceInterface
{
    public function approveMenuLinks(bool $write = false, ?string $approval = null): LegacyPhaseSixApprovalResultDTO;

    public function approvePages(bool $write = false, ?string $approval = null): LegacyPhaseSixApprovalResultDTO;
}
