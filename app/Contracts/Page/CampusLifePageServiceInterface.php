<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\CampusLife\CampusLifePageDTO;
use App\DTOs\CampusLife\CampusLifeSectionDTO;

interface CampusLifePageServiceInterface
{
    public function getLanding(string $locale): CampusLifePageDTO;

    /** @param array<string, mixed> $landing */
    public function buildPreviewLanding(string $locale, array $landing): CampusLifePageDTO;

    public function getSection(string $slug, string $locale): ?CampusLifeSectionDTO;

    /** @param array<string, mixed> $section */
    public function buildPreviewSection(string $targetKey, string $locale, array $section): ?CampusLifeSectionDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;
}
