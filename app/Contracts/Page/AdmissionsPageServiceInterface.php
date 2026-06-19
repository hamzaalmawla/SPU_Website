<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Admissions\AdmissionsPageDTO;
use App\DTOs\Admissions\AdmissionsSectionDTO;

interface AdmissionsPageServiceInterface
{
    public function getLanding(string $locale): AdmissionsPageDTO;

    public function getSection(string $slug, string $locale): ?AdmissionsSectionDTO;
}
