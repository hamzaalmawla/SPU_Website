<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyFaqReviewPacketResultDTO;

interface LegacyFaqReviewPacketServiceInterface
{
    public function export(string $disk = 'local', string $directory = 'legacy-import-exports/faq-review-packets'): LegacyFaqReviewPacketResultDTO;
}
