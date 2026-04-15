<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\NavigationTreeDTO;

/**
 * Builds public navigation payloads from menu trees and settings.
 */
interface NavigationServiceInterface
{
    /**
     * Build the localized header navigation tree.
     */
    public function getHeaderNavigation(string $locale, ?string $currentPath = null): NavigationTreeDTO;

    /**
     * Build the localized footer navigation tree.
     */
    public function getFooterNavigation(string $locale): NavigationTreeDTO;

    /**
     * Build the localized utility navigation tree.
     */
    public function getUtilityNavigation(string $locale): NavigationTreeDTO;

    /**
     * Build the full public navigation payload.
     *
     * @return array<string, mixed>
     */
    public function getFullNavigationPayload(string $locale, ?string $currentPath = null): array;
}
