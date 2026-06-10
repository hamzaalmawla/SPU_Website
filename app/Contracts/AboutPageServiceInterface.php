<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\AboutContentPageDTO;
use App\DTOs\AboutLandingDTO;
use App\DTOs\DirectorateDTO;
use App\DTOs\PartnershipDTO;
use App\DTOs\PersonDTO;
use Illuminate\Support\Collection;

interface AboutPageServiceInterface
{
    public function getAboutLanding(string $locale): AboutLandingDTO;

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO;

    /** @return Collection<int, PersonDTO> */
    public function getLeadershipProfiles(string $locale): Collection;

    /** @return Collection<int, DirectorateDTO> */
    public function getDirectorates(string $locale): Collection;

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO;

    /** @return Collection<int, PartnershipDTO> */
    public function getPartnerships(string $locale): Collection;
}
