<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Navigation\NavigationActionDTO;
use App\DTOs\Navigation\NavigationPayloadDTO;
use App\DTOs\Navigation\NavigationTreeDTO;

/**
 * Index coverage for hot-path queries (verified 2026-04-30):
 * ──────────────────────────────────────────────────────────
 * NavigationService delegates all menu queries to MenuService.
 * See MenuService for index documentation on menu_items queries.
 *
 * Settings queries (via SettingsService) are covered by
 * UNIQUE(group_key, key, locale) on the settings table.
 */
final class NavigationService implements NavigationServiceInterface
{
    public function __construct(
        private readonly MenuServiceInterface $menuService,
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getHeaderNavigation(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        return $this->menuService->getHeaderTree($locale, $currentPath);
    }

    public function getFooterNavigation(string $locale): NavigationTreeDTO
    {
        return $this->menuService->getFooterTree($locale);
    }

    public function getUtilityNavigation(string $locale): NavigationTreeDTO
    {
        return $this->menuService->getUtilityTree($locale);
    }

    public function getFullNavigationPayload(string $locale, ?string $currentPath = null): NavigationPayloadDTO
    {
        $settings = $this->settingsService->getPublicSettings($locale);
        $applyCta = $settings->applyCta;

        return new NavigationPayloadDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            header: $this->getHeaderNavigation($locale, $currentPath),
            footer: $this->getFooterNavigation($locale),
            utility: $this->getUtilityNavigation($locale),
            languageSwitchLinks: $this->buildLanguageSwitchLinks($locale, $currentPath),
            applyCta: $applyCta->isEnabled ? new NavigationActionDTO($applyCta->label, $applyCta->url, $applyCta->target) : null,
            studentPortalUrl: $settings->studentPortalUrl,
            staffAccessUrl: $settings->staffAccessUrl,
            emergencyNotice: $settings->emergencyNotice,
            footerSettings: $settings->footer,
            socialContact: $settings->socialContact,
        );
    }

    /**
     * @return array<int, LanguageSwitchLinkDTO>
     */
    private function buildLanguageSwitchLinks(string $locale, ?string $currentPath): array
    {
        $contextPath = $this->extractContextPath($currentPath, $locale);
        $links = [];

        foreach (['ar', 'en'] as $candidateLocale) {
            $url = $contextPath === '' ? '/'.$candidateLocale : '/'.$candidateLocale.'/'.$contextPath;

            $links[] = new LanguageSwitchLinkDTO(
                locale: $candidateLocale,
                label: strtoupper($candidateLocale),
                url: $url,
                isCurrent: $candidateLocale === $locale,
            );
        }

        return $links;
    }

    private function extractContextPath(?string $currentPath, string $locale): string
    {
        if ($currentPath === null || $currentPath === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', trim($currentPath, '/'))));

        if (($segments[0] ?? null) === $locale) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }
}
