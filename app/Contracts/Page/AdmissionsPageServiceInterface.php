<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Admissions\AdmissionsPageDTO;
use App\DTOs\Admissions\AdmissionsSectionDTO;

interface AdmissionsPageServiceInterface
{
    public function getLanding(string $locale): AdmissionsPageDTO;

    public function getSection(string $slug, string $locale): ?AdmissionsSectionDTO;

    public function buildPreviewLanding(string $locale, array $landing): AdmissionsPageDTO;

    public function buildPreviewSection(string $targetKey, string $locale, array $section): ?AdmissionsSectionDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(string $targetKey): array;
}
