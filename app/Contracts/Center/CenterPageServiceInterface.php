<?php

declare(strict_types=1);

namespace App\Contracts\Center;

use App\DTOs\Center\CenterLandingPageDTO;
use App\DTOs\Center\CenterPageDTO;

/**
 * The public centers pages, built from the published content (or the default
 * content until a page is published).
 */
interface CenterPageServiceInterface
{
    public function getLanding(string $locale): CenterLandingPageDTO;

    /**
     * A center's page (subpage '') or one of its subpages. Filters: `faculty`
     * (a faculty slug) narrows a course list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPage(string $centerSlug, string $subpageSlug, string $locale, array $filters = []): ?CenterPageDTO;

    /** @param array<string, mixed> $payload */
    public function previewLanding(string $locale, array $payload): CenterLandingPageDTO;

    /**
     * The page a center target's draft shows: the center page, its staff page
     * or (for the courses target) the required or elective list.
     *
     * @param  array<string, mixed>  $payload
     */
    public function previewPage(string $targetKey, string $locale, array $payload, string $subpageSlug = ''): ?CenterPageDTO;

    /**
     * The center's staff profiles for the admin pickers, any publication state.
     *
     * @return array<int, string> profile id => name
     */
    public function staffOptions(string $centerSlug, string $locale): array;
}
