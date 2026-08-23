<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Content\ProfilePageDTO;

interface ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $source, string $slug): ?ProfilePageDTO;

    /** @return array<int, ProfilePageDTO> */
    public function getPublicProfiles(string $locale): array;

    public function resolveLegacyProfile(string $locale, string $identifier): ?ProfilePageDTO;
}
