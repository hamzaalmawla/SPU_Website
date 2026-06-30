<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\About\AboutContentPageDTO;
use App\DTOs\About\AboutLandingDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\PersonDTO;
use Illuminate\Support\Collection;

interface AboutPageServiceInterface
{
    public function getAboutLanding(string $locale): AboutLandingDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewAboutLanding(string $locale, array $content): AboutLandingDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewContentPage(string $targetKey, string $locale, array $content): ?AboutContentPageDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO;

    public function getStaffDirectoryPage(string $locale): AboutContentPageDTO;

    /** @return Collection<int, PersonDTO> */
    public function getLeadershipProfiles(string $locale): Collection;

    /** @return Collection<int, DirectorateDTO> */
    public function getDirectorates(string $locale): Collection;

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO;

    /** @return Collection<int, PartnershipDTO> */
    public function getPartnerships(string $locale): Collection;
}
