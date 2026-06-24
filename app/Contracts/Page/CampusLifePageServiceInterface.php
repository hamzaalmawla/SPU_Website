<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;

interface CampusLifePageServiceInterface
{
    public function getLanding(string $locale): CampusLifePageDTO;

    public function getSection(string $slug, string $locale): ?CampusLifeSectionDTO;
}
