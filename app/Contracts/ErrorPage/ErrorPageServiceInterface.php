<?php

declare(strict_types=1);

namespace App\Contracts\ErrorPage;

use App\DTOs\ErrorPage\ErrorPageContentDTO;

/**
 * Builds error page copy and navigation offers without any infrastructure.
 *
 * Implementations of this contract must never query the database, read the
 * cache, or resolve CMS content: a 500 or 503 page is frequently rendered
 * because one of those is unavailable.
 */
interface ErrorPageServiceInterface
{
    /**
     * Build the bilingual payload for a status code.
     *
     * @param  string  $requestPath  Request path used to detect the locale.
     * @param  string|null  $acceptLanguage  Raw Accept-Language header value.
     */
    public function content(int $status, string $requestPath, ?string $acceptLanguage = null): ErrorPageContentDTO;

    /**
     * Whether a status code may be rendered inside the full public layout.
     *
     * Application-level statuses (404/403/419/429) presume healthy
     * infrastructure. Server-level statuses (500/503) do not.
     */
    public function supportsFullLayout(int $status): bool;
}
