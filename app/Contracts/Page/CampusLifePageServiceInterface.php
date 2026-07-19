<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\CampusLife\CampusLifeJobDTO;
use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;

interface CampusLifePageServiceInterface
{
    public function getLanding(string $locale): CampusLifePageDTO;

    /** @param array<string, mixed> $landing */
    public function buildPreviewLanding(string $locale, array $landing): CampusLifePageDTO;

    public function getSection(string $slug, string $locale): ?CampusLifeSectionDTO;

    /** @param array<string, mixed> $filters */
    public function getCareerJobBoard(string $locale, array $filters = []): CampusLifeSectionDTO;

    public function getCareerJobDetail(string $slug, string $locale): ?CampusLifeSectionDTO;

    public function getCareerJobApplication(string $locale, ?string $slug): ?CampusLifeSectionDTO;

    public function findOpenCareerJob(string $slug, string $locale): ?CampusLifeJobDTO;

    /** @param array<string, mixed> $content @param array<string, mixed> $filters */
    public function buildPreviewCareerJobs(string $locale, array $content, array $filters = []): CampusLifeSectionDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewCareerJob(string $locale, array $content, string $slug): ?CampusLifeSectionDTO;

    /** @param array<string, mixed> $section */
    public function buildPreviewSection(string $targetKey, string $locale, array $section): ?CampusLifeSectionDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;
}
