<?php

declare(strict_types=1);

namespace App\Contracts\Homepage;

use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;

interface HomepageContentSelectionServiceInterface
{
    public function hydrateSection(HomepageSectionDTO $section, string $locale): HomepageSectionDTO;

    public function hydratePayload(HomepageSectionDataDTO $payload, string $sectionKey, string $locale): HomepageSectionDataDTO;

    public function hasValidManualSelection(HomepageSectionDataDTO $payload, string $sectionKey, string $locale): bool;
}
