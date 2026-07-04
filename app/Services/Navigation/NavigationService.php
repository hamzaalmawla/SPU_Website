<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\DTOs\Navigation\MenuItemDTO;
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
        return $this->withResearchDropdown($this->menuService->getHeaderTree($locale, $currentPath), $currentPath);
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

    private function withResearchDropdown(NavigationTreeDTO $tree, ?string $currentPath): NavigationTreeDTO
    {
        return new NavigationTreeDTO(
            treeType: $tree->treeType,
            locale: $tree->locale,
            direction: $tree->direction,
            items: array_map(
                fn (MenuItemDTO $item): MenuItemDTO => $this->researchItemWithChildren($item, $tree->locale, $currentPath),
                $tree->items,
            ),
        );
    }

    private function researchItemWithChildren(MenuItemDTO $item, string $locale, ?string $currentPath): MenuItemDTO
    {
        if (! $this->isResearchMenuItem($item, $locale)) {
            return $item;
        }

        return new MenuItemDTO(
            id: $item->id,
            parentId: $item->parentId,
            label: $item->label,
            itemType: $item->itemType,
            groupKey: $item->groupKey,
            targetType: $item->targetType,
            locale: $item->locale,
            targetId: $item->targetId,
            url: $item->url,
            resolvedUrl: $item->resolvedUrl,
            target: $item->target,
            routeName: $item->routeName,
            cssToken: $item->cssToken,
            icon: $item->icon,
            isActive: $item->isActive,
            sortOrder: $item->sortOrder,
            depth: $item->depth,
            isEnabled: $item->isEnabled,
            isUtility: $item->isUtility,
            openInNewTab: $item->openInNewTab,
            children: $this->researchDropdownChildren($locale, $currentPath),
        );
    }

    private function isResearchMenuItem(MenuItemDTO $item, string $locale): bool
    {
        return $item->resolvedUrl === '/'.$locale.'/research'
            || $item->url === '/'.$locale.'/research'
            || $item->routeName === 'public.research.index';
    }

    /** @return array<int, MenuItemDTO> */
    private function researchDropdownChildren(string $locale, ?string $currentPath): array
    {
        return array_map(
            function (array $item) use ($locale, $currentPath): MenuItemDTO {
                $url = '/'.$locale.$item['path'];

                return new MenuItemDTO(
                    id: $item['id'],
                    parentId: null,
                    label: $item['label'][$locale],
                    itemType: 'header',
                    groupKey: 'header',
                    targetType: 'url',
                    locale: $locale,
                    targetId: null,
                    url: $url,
                    resolvedUrl: $url,
                    target: null,
                    routeName: null,
                    cssToken: null,
                    icon: null,
                    isActive: $currentPath !== null && trim($currentPath, '/') === trim($url, '/'),
                    sortOrder: $item['sort'],
                    depth: 1,
                    isEnabled: true,
                    isUtility: false,
                    openInNewTab: false,
                    children: [],
                );
            },
            $this->researchDropdownItems(),
        );
    }

    /** @return array<int, array{id: int, sort: int, path: string, label: array{ar: string, en: string}}> */
    private function researchDropdownItems(): array
    {
        return [
            ['id' => -601, 'sort' => 1, 'path' => '/research/expert-finder', 'label' => ['ar' => 'الباحث عن الخبراء', 'en' => 'Expert Finder']],
            ['id' => -602, 'sort' => 2, 'path' => '/research/conferences', 'label' => ['ar' => 'المؤتمرات والندوات', 'en' => 'Conferences & Seminars']],
            ['id' => -603, 'sort' => 3, 'path' => '/research/library', 'label' => ['ar' => 'مكتبة البحث', 'en' => 'Research Library']],
            ['id' => -604, 'sort' => 4, 'path' => '/research/policies', 'label' => ['ar' => 'السياسات والأخلاقيات', 'en' => 'Policies & Ethics']],
            ['id' => -605, 'sort' => 5, 'path' => '/research/office', 'label' => ['ar' => 'مكتب البحث', 'en' => 'Research Office']],
        ];
    }
}
