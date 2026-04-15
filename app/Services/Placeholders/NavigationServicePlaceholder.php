<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\NavigationServiceInterface;
use App\DTOs\NavigationPayloadDTO;
use App\DTOs\NavigationTreeDTO;
use BadMethodCallException;

/**
 * Placeholder implementation for the navigation service contract.
 */
final class NavigationServicePlaceholder implements NavigationServiceInterface
{
    public function getHeaderNavigation(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getFooterNavigation(string $locale): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getUtilityNavigation(string $locale): NavigationTreeDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getFullNavigationPayload(string $locale, ?string $currentPath = null): NavigationPayloadDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
