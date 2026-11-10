<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyNewsApprovalPacketResultDTO;

interface LegacyNewsApprovalPacketServiceInterface
{
    /** @param list<string> $inputs */
    public function build(
        array $inputs,
        string $approvedBy,
        string $disk = 'local',
        string $directory = 'legacy-import-exports/news-approval-packets',
        bool $allowArabicFallback = false,
    ): LegacyNewsApprovalPacketResultDTO;
}
