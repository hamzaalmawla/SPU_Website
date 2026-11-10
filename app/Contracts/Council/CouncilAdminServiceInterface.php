<?php

declare(strict_types=1);

namespace App\Contracts\Council;

/** Admin writes that span the council list and its CMS content. */
interface CouncilAdminServiceInterface
{
    /**
     * Adds a council and saves its first draft (its name in both languages and
     * the default headings), together or not at all. Returns the council's id.
     */
    public function createCouncil(string $slug, int $sortOrder, bool $isEnabled, string $titleAr, string $titleEn, int $userId): int;
}
