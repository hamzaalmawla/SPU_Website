<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCareerLinkReviewPacketResultDTO;

interface LegacyCareerLinkReviewPacketServiceInterface
{
    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/career-link-review-packets'): LegacyCareerLinkReviewPacketResultDTO;
}
