<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCategoryReviewPacketResultDTO;

interface LegacyCategoryReviewPacketServiceInterface
{
    /**
     * @param  array<int, string>  $subsites
     * @param  array<int, int|string>  $services
     */
    public function export(
        array $subsites = [],
        array $services = [],
        string $disk = 'local',
        string $directory = 'legacy-import-exports/category-review-packets',
    ): LegacyCategoryReviewPacketResultDTO;
}
