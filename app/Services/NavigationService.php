<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MenuServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\LanguageSwitchLinkDTO;
use App\DTOs\NavigationActionDTO;
use App\DTOs\NavigationPayloadDTO;
use App\DTOs\NavigationTreeDTO;

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
        $applyCta = $this->settingsService->getApplyCtaTarget($locale);
        $emergencyNotice = $this->settingsService->getEmergencyNotice($locale);
        $footerSettings = $this->settingsService->getFooterSettings($locale);
        $socialContact = $this->settingsService->getSocialContactSettings($locale);

        return new NavigationPayloadDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            header: $this->getHeaderNavigation($locale, $currentPath),
            footer: $this->getFooterNavigation($locale),
            utility: $this->getUtilityNavigation($locale),
            languageSwitchLinks: $this->buildLanguageSwitchLinks($locale, $currentPath),
            applyCta: $applyCta->isEnabled ? new NavigationActionDTO($applyCta->label, $applyCta->url, $applyCta->target) : null,
            studentPortalUrl: $this->settingsService->getStudentPortalUrl(),
            staffAccessUrl: $this->settingsService->getStaffAccessUrl(),
            emergencyNotice: $emergencyNotice,
            footerSettings: $footerSettings,
            socialContact: $socialContact,
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
