<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\About\AboutContentPageDTO;
use App\DTOs\About\AboutLandingDTO;
use App\DTOs\About\AboutVisionMissionDTO;
use App\DTOs\About\LeadershipDirectoryDTO;
use App\DTOs\About\PartnershipDirectoryDTO;
use App\DTOs\About\StaffDirectoryDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PersonDTO;
use Illuminate\Support\Collection;

interface AboutPageServiceInterface
{
    public function getAboutLanding(string $locale): AboutLandingDTO;

    public function getVisionMission(string $locale): AboutVisionMissionDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewVisionMission(string $locale, array $content): AboutVisionMissionDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewAboutLanding(string $locale, array $content): AboutLandingDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewContentPage(string $targetKey, string $locale, array $content): ?AboutContentPageDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;

    public function getContentPage(string $slug, string $locale): ?AboutContentPageDTO;

    public function getStaffDirectoryPage(string $locale): AboutContentPageDTO;

    public function getStaffDirectory(
        string $locale,
        ?string $requestedFaculty = null,
        int $requestedPage = 1,
    ): StaffDirectoryDTO;

    /** @return Collection<int, PersonDTO> */
    public function getLeadershipProfiles(string $locale): Collection;

    public function getLeadershipDirectory(string $locale, ?string $requestedFaculty = null): LeadershipDirectoryDTO;

    /** @return Collection<int, DirectorateDTO> */
    public function getDirectorates(string $locale): Collection;

    public function getDirectorate(string $slug, string $locale): ?DirectorateDTO;

    public function getPartnerships(
        string $locale,
        ?string $requestedCategory = null,
        ?string $requestedQuery = null,
        int $requestedPage = 1,
    ): PartnershipDirectoryDTO;

    /** @return array<int, array<string, string>> */
    public function getAboutSubPages(string $locale, ?string $excludeTargetKey = null): array;
}
