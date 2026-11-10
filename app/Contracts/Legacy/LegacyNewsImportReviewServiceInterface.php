<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyNewsImportReviewDTO;

interface LegacyNewsImportReviewServiceInterface
{
    public function review(): LegacyNewsImportReviewDTO;
}
