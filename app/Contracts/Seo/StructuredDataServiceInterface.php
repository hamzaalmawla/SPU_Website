<?php

declare(strict_types=1);

namespace App\Contracts\Seo;

use App\DTOs\Seo\StructuredDataDTO;

/**
 * Builds schema.org JSON-LD documents for public pages.
 *
 * Organisation details are sourced from the settings service rather than
 * hardcoded, so editors control what search engines see.
 */
interface StructuredDataServiceInterface
{
    /**
     * CollegeOrUniversity document for the university itself.
     */
    public function organisation(string $locale): StructuredDataDTO;

    /**
     * WebSite document, including a SearchAction when a search route exists.
     */
    public function website(string $locale): StructuredDataDTO;

    /**
     * BreadcrumbList document for a section or detail page.
     *
     * @param  array<int, array{name: string, url: string}>  $trail  Ordered
     *                                                               crumbs, excluding the implicit homepage crumb which is prepended.
     */
    public function breadcrumbs(string $locale, array $trail): StructuredDataDTO;

    /**
     * The homepage document: an @graph pairing the organisation and the site.
     */
    public function homepage(string $locale): StructuredDataDTO;
}
