<?php

declare(strict_types=1);

namespace App\Contracts\Navigation;

use App\DTOs\Navigation\NavigationPayloadDTO;
use App\DTOs\Navigation\NavigationTreeDTO;

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
     */
    public function getFullNavigationPayload(string $locale, ?string $currentPath = null): NavigationPayloadDTO;
}
