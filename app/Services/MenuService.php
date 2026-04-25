<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MenuServiceInterface;
use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\NavigationTreeDTO;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use BadMethodCallException;
use Illuminate\Support\Facades\Route;

final class MenuService implements MenuServiceInterface
{
    public function createItem(MenuItemDataDTO $payload): MenuItemDTO
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function updateItem(int $itemId, MenuItemDataDTO $payload): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function deleteItem(int $itemId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function reorderTree(string $treeType, array $tree): bool
    {
        throw new BadMethodCallException(__METHOD__.' is outside the current public-runtime phase.');
    }

    public function toggleItemState(int $itemId, bool $enabled): bool
    {
        return MenuItem::query()->whereKey($itemId)->update(['is_enabled' => $enabled]) > 0;
    }

    public function getHeaderTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        return $this->getTree('header', $locale, $currentPath);
    }

    public function getFooterTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        return $this->getTree('footer', $locale, $currentPath);
    }

    public function getUtilityTree(string $locale, ?string $currentPath = null): NavigationTreeDTO
    {
        return $this->getTree('utility', $locale, $currentPath);
    }

    private function getTree(string $groupKey, string $locale, ?string $currentPath): NavigationTreeDTO
    {
        $items = MenuItem::query()
            ->whereNull('parent_id')
            ->where('group_key', $groupKey)
            ->forLocale($locale)
            ->enabled()
            ->with([
                'pageTarget.translations',
                'children' => fn ($query) => $query
                    ->enabled()
                    ->forLocale($locale)
                    ->orderBy('sort_order')
                    ->with('pageTarget.translations'),
            ])
            ->orderBy('sort_order')
            ->get();

        $mappedItems = array_values(array_filter(array_map(
            fn (MenuItem $item): ?MenuItemDTO => $this->mapItem($item, $locale, $currentPath),
            $items->all()
        )));

        return new NavigationTreeDTO(
            treeType: $groupKey,
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            items: $mappedItems,
        );
    }

    private function mapItem(MenuItem $item, string $locale, ?string $currentPath): ?MenuItemDTO
    {
        $children = array_values(array_filter(array_map(
            fn (MenuItem $child): ?MenuItemDTO => $this->mapItem($child, $locale, $currentPath),
            $item->children->all()
        )));

        $resolvedUrl = $this->resolveItemUrl($item, $locale);

        if ($resolvedUrl === null && $children === []) {
            return null;
        }

        return new MenuItemDTO(
            id: (int) $item->getKey(),
            parentId: $item->parent_id !== null ? (int) $item->parent_id : null,
            label: (string) $item->label,
            itemType: (string) $item->type,
            groupKey: (string) ($item->group_key ?? $item->type),
            targetType: (string) $item->target_kind,
            locale: $item->locale,
            targetId: $item->target_id !== null ? (int) $item->target_id : null,
            url: $item->url,
            resolvedUrl: $resolvedUrl,
            target: $item->target,
            routeName: $item->route_name,
            cssToken: $item->css_token,
            icon: $item->icon,
            isActive: $this->isActive($resolvedUrl, $currentPath),
            sortOrder: (int) $item->sort_order,
            depth: (int) $item->depth,
            isEnabled: (bool) $item->is_enabled,
            isUtility: (bool) $item->is_utility,
            openInNewTab: (bool) $item->open_in_new_tab,
            children: $children,
        );
    }

    private function resolveItemUrl(MenuItem $item, string $locale): ?string
    {
        if ($item->target_kind === 'page' && $item->pageTarget instanceof Page) {
            return $this->resolvePageUrl($item->pageTarget, $locale);
        }

        if ($item->target_kind === 'route' && is_string($item->route_name) && $item->route_name !== '' && Route::has($item->route_name)) {
            return route($item->route_name, ['locale' => $locale], false);
        }

        return is_string($item->url) && $item->url !== '' ? $item->url : null;
    }

    private function resolvePageUrl(Page $page, string $locale): ?string
    {
        if (! $this->isRenderable($page, $locale)) {
            return null;
        }

        if ((bool) $page->is_homepage_shell) {
            return '/'.$locale;
        }

        $segments = [];

        foreach ($this->ancestorChain($page) as $ancestor) {
            $segments[] = (string) $ancestor->slug;
        }

        $segments[] = (string) $page->slug;

        return '/'.$locale.'/'.implode('/', array_filter($segments));
    }

    private function isRenderable(Page $page, string $locale): bool
    {
        if (! (bool) $page->is_enabled || $page->status !== 'published' || $page->published_at === null) {
            return false;
        }

        if ($page->publish_at !== null && $page->publish_at->isFuture()) {
            return false;
        }

        if ($this->findTranslation($page, $locale) === null) {
            return false;
        }

        foreach ($this->ancestorChain($page) as $ancestor) {
            if (! (bool) $ancestor->is_enabled || $ancestor->status !== 'published' || $ancestor->published_at === null) {
                return false;
            }

            if ($ancestor->publish_at !== null && $ancestor->publish_at->isFuture()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, Page>
     */
    private function ancestorChain(Page $page): array
    {
        $ancestors = [];
        $cursor = $page;

        while ($cursor->parent_id !== null) {
            $cursor->loadMissing('parent');

            if (! $cursor->parent instanceof Page) {
                return [];
            }

            $cursor = $cursor->parent;
            $ancestors[] = $cursor;
        }

        return array_reverse($ancestors);
    }

    private function findTranslation(Page $page, string $locale): ?PageTranslation
    {
        $page->loadMissing('translations');

        return $page->translations->firstWhere('locale', $locale);
    }

    private function isActive(?string $resolvedUrl, ?string $currentPath): bool
    {
        if ($resolvedUrl === null || $currentPath === null || $currentPath === '') {
            return false;
        }

        $resolvedPath = trim((string) parse_url($resolvedUrl, PHP_URL_PATH), '/');
        $normalizedCurrentPath = trim($currentPath, '/');

        return $resolvedPath !== ''
            && ($resolvedPath === $normalizedCurrentPath || str_starts_with($normalizedCurrentPath, $resolvedPath.'/'));
    }
}
