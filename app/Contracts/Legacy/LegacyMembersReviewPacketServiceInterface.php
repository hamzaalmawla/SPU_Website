<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyMembersReviewPacketResultDTO;

interface LegacyMembersReviewPacketServiceInterface
{
    /** @param array<int, int|string> $services */
    public function export(
        array $services = [],
        string $disk = 'local',
        string $directory = 'legacy-import-exports/members-review-packets',
    ): LegacyMembersReviewPacketResultDTO;
}
