<?php

declare(strict_types=1);

namespace App\Contracts\Navigation;

/**
 * Lists the internal pages an editor can link a button or link to.
 */
interface PageLinkOptionServiceInterface
{
    /**
     * Every linkable internal page in the locale, at any depth.
     *
     * Keys are the stored link value (a locale-prefixed path such as
     * "/en/campus-life/career-development/jobs"); values are breadcrumb labels
     * in that locale ("Campus Life › Career Development › Job Board"). Ordered
     * so each page follows its parent.
     *
     * @return array<string, string>
     */
    public function options(string $locale): array;

    /**
     * Options whose label or path contains the search text, shallowest first.
     *
     * @return array<string, string>
     */
    public function search(string $locale, string $search, int $limit = 30): array;
}
