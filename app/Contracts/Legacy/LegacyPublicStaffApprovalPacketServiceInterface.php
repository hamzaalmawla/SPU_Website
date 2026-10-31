<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyPublicStaffApprovalPacketResultDTO;

interface LegacyPublicStaffApprovalPacketServiceInterface
{
    /** @param list<string> $inputs */
    public function build(array $inputs, string $approvedBy, string $disk = 'local', string $directory = 'legacy-import-exports/public-staff-approval-packets', bool $central = false): LegacyPublicStaffApprovalPacketResultDTO;
}
