<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFaqApprovalPacketResultDTO;

interface LegacyFaqApprovalPacketServiceInterface
{
    public function build(string $input, string $approvedBy, string $disk = 'local', string $directory = 'legacy-import-exports/faq-approval-packets'): LegacyFaqApprovalPacketResultDTO;
}
