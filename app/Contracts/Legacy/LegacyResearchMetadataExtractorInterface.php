<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyResearchMetadataDTO;

interface LegacyResearchMetadataExtractorInterface
{
    public function extract(?string $html, ?string $explicitKeywords, ?string $title): LegacyResearchMetadataDTO;
}
