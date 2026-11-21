<?php

declare(strict_types=1);

namespace App\Contracts\Council;

/**
 * The teaching staff a council member can be picked from, and what the
 * council page shows for a picked member (read live from their profile).
 */
interface CouncilStaffServiceInterface
{
    /**
     * Every teaching staff profile, by id, named in the given language.
     *
     * @return array<int, string>
     */
    public function staffOptions(string $locale): array;

    /**
     * The card for each profile id that can be shown: name, academic rank,
     * photo, email and profile URL. With $publicOnly, profiles not published on
     * the site are left out.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{name: string, rank: string, image: ?string, email: string, url: string, isPublic: bool}>
     */
    public function staffCards(array $ids, string $locale, bool $publicOnly = true): array;
}
