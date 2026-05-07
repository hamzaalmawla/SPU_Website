<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\MenuTreeNodeDTO;
use App\DTOs\NavigationTreeDTO;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\UrlSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Orchestrates CMS menu tree management for primary and utility navigation.
 *
 * Optimistic locking note (verified 2026-04-30):
 * ───────────────────────────────────────────────
 * MenuItem does NOT require a version column for optimistic locking because:
 * 1. Menu items are edited individually (single-item CRUD), not as a bulk draft payload.
 * 2. The admin panel uses Filament's built-in form lifecycle — each edit loads the current
 *    state and saves atomically; there is no long-lived draft that could go stale.
 * 3. Reorder operations (reorderTree) replace the entire tree sort order in a transaction,
 *    making partial overwrites impossible.
 * 4. Concurrent menu editing is extremely rare (typically one admin manages navigation).
 * If concurrent multi-editor menu editing becomes a requirement in a future phase,
 * a version column can be added to menu_items at that time.
 *
 * Index coverage for hot-path queries (verified 2026-04-30):
 * ──────────────────────────────────────────────────────────
 * buildTree() (public navigation rendering):
 *   → whereNull('parent_id') + where('group_key', $gk) + forLocale($locale)
 *     + enabled() + orderBy('sort_order')
 *   → Covered by idx_menu_tree_lookup: (group_key, locale, parent_id, is_enabled, sort_order)
 *     from migration 2026_04_30_000002_add_composite_performance_indexes
 *
 * getAdminTree():
 *   → Same pattern as buildTree but without enabled() filter
 *   → idx_menu_tree_lookup still covers the leading columns (group_key, locale, parent_id)
 *
 * resolveItemUrl() → pageTarget eager-loaded via with('pageTarget.translations')
 *   → page_translations has UNIQUE(page_id, locale) — covered
 *
 * nextSortOrder():
 *   → where('group_key', $gk) + where('locale', $locale) + where('parent_id', $pid) + max('sort_order')
 *   → Covered by idx_menu_tree_lookup leading columns
 *
 * Target lookup:
 *   → Covered by idx_menu_target_lookup: (target_kind, target_id)
 */
final class MenuService implements MenuServiceInterface
{
    private const MAX_DEPTH = 2;

    private const SUPPORTED_TARGET_TYPES = [
        'page',
        'route',
        'url',
    ];

    private HtmlSanitizer $htmlSanitizer;

    private ?AuthFactory $authFactory;

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
        private readonly AuditServiceInterface $auditService,
        ?HtmlSanitizer $htmlSanitizer = null,
        ?AuthFactory $authFactory = null,
    ) {
        $this->htmlSanitizer = $htmlSanitizer ?? new HtmlSanitizer;
        $this->authFactory = $authFactory;
    }

    public function createItem(MenuItemDataDTO $payload): MenuItemDTO
    {
        $this->authorizeMenu('create', MenuItem::class);

        $locale = $this->normalizeLocale($payload->locale);
        $this->assertGroupKey($payload->groupKey);
        $this->assertItemType($payload->itemType, $payload->groupKey);

        $item = DB::transaction(function () use ($payload, $locale): MenuItem {
            $parent = $this->parentForPayload($payload->parentId, $payload->groupKey, $locale);
            $depth = $parent instanceof MenuItem ? ((int) $parent->depth + 1) : 0;

            $this->assertDepthAllowed($depth);
            $this->assertTargetConfiguration($payload, $locale);

            $item = MenuItem::query()->create([
                'parent_id' => $parent?->getKey(),
                'type' => $payload->itemType,
                'label' => $this->htmlSanitizer->sanitize($payload->label),
                'locale' => $locale,
                'target_kind' => $payload->targetType,
                'target_id' => $payload->targetType === 'page' ? $payload->targetId : null,
                'url' => $payload->targetType === 'url' ? $this->sanitizeTargetUrl($payload->url) : null,
                'target' => $payload->target,
                'route_name' => $payload->targetType === 'route' ? $payload->routeName : null,
                'css_token' => $payload->cssToken,
                'icon' => $payload->icon,
                'group_key' => $payload->groupKey,
                'is_enabled' => $payload->isEnabled,
                'is_utility' => $payload->groupKey === 'utility',
                'open_in_new_tab' => $payload->openInNewTab,
                'sort_order' => $payload->sortOrder > 0
                    ? $payload->sortOrder
                    : $this->nextSortOrder($payload->groupKey, $locale, $parent?->getKey()),
                'depth' => $depth,
            ]);

            $item->loadMissing('pageTarget.translations', 'children.pageTarget.translations');

            return $item;
        });

        $this->invalidateNavigationCaches([$locale]);
        $this->auditService->log(
            action: 'menu.created',
            userId: $this->currentUserId(),
            entityType: MenuItem::class,
            entityId: (int) $item->getKey(),
            metadata: $this->auditMetadata($item),
        );

        return $this->mapItem($item, $locale, null);
    }

    public function updateItem(int $itemId, MenuItemDataDTO $payload): bool
    {
        $item = MenuItem::query()->with('children.children')->find($itemId);

        if (! $item instanceof MenuItem) {
            return false;
        }

        $this->authorizeMenu('update', $item);

        $locale = $this->normalizeLocale($payload->locale);
        $this->assertGroupKey($payload->groupKey);
        $this->assertItemType($payload->itemType, $payload->groupKey);

        $originalLocale = is_string($item->locale) ? $item->locale : $locale;

        DB::transaction(function () use ($item, $payload, $locale): void {
            $parent = $this->parentForPayload($payload->parentId, $payload->groupKey, $locale, $item);
            $depth = $parent instanceof MenuItem ? ((int) $parent->depth + 1) : 0;

            $this->assertDepthAllowed($depth);
            $this->assertSubtreeDepthAllowed($item, $depth);
            $this->assertTargetConfiguration($payload, $locale);

            $item->forceFill([
                'parent_id' => $parent?->getKey(),
                'type' => $payload->itemType,
                'label' => $this->htmlSanitizer->sanitize($payload->label),
                'locale' => $locale,
                'target_kind' => $payload->targetType,
                'target_id' => $payload->targetType === 'page' ? $payload->targetId : null,
                'url' => $payload->targetType === 'url' ? $this->sanitizeTargetUrl($payload->url) : null,
                'target' => $payload->target,
                'route_name' => $payload->targetType === 'route' ? $payload->routeName : null,
                'css_token' => $payload->cssToken,
                'icon' => $payload->icon,
                'group_key' => $payload->groupKey,
                'is_enabled' => $payload->isEnabled,
                'is_utility' => $payload->groupKey === 'utility',
                'open_in_new_tab' => $payload->openInNewTab,
                'sort_order' => $payload->sortOrder > 0 ? $payload->sortOrder : (int) $item->sort_order,
                'depth' => $depth,
            ])->save();

            $this->syncChildDepths($item, $depth);
        });

        $this->invalidateNavigationCaches([$originalLocale, $locale]);
        $this->auditService->log(
            action: 'menu.updated',
            userId: $this->currentUserId(),
            entityType: MenuItem::class,
            entityId: $itemId,
            metadata: [
                'item_id' => $itemId,
                'group_key' => $payload->groupKey,
                'locale' => $locale,
                'target_kind' => $payload->targetType,
            ],
        );

        return true;
    }

    public function deleteItem(int $itemId): bool
    {
        $item = MenuItem::query()->with('children.children')->find($itemId);

        if (! $item instanceof MenuItem) {
            return false;
        }

        $this->authorizeMenu('delete', $item);

        $locale = is_string($item->locale) ? $item->locale : null;

        DB::transaction(function () use ($item): void {
            $this->deleteSubtree($item);
        });

        $this->invalidateNavigationCaches($locale !== null ? [$locale] : ['ar', 'en']);
        $this->auditService->log(
            action: 'menu.deleted',
            userId: $this->currentUserId(),
            entityType: MenuItem::class,
            entityId: $itemId,
            metadata: [
                'item_id' => $itemId,
                'group_key' => $item->group_key,
                'locale' => $locale,
            ],
        );

        return true;
    }

    /**
     * @param  array<int, MenuTreeNodeDTO>  $tree
     */
    public function reorderTree(string $treeType, array $tree): bool
    {
        $this->authorizeMenu('manage', MenuItem::class);

        $this->assertTreeType($treeType);

        $itemIds = $this->collectTreeItemIds($tree);
        $items = MenuItem::query()->whereIn('id', $itemIds)->get()->keyBy('id');

        if ($items->count() !== count($itemIds)) {
            return false;
        }

        DB::transaction(function () use ($tree, $treeType, $items): void {
            foreach (array_values($tree) as $index => $node) {
                if (! $node instanceof MenuTreeNodeDTO) {
                    throw new InvalidArgumentException('Tree reorder nodes must be MenuTreeNodeDTO instances.');
                }

                $this->applyTreeNode($node, null, 0, $index + 1, $treeType, $items->all());
            }
        });

        $locales = array_values(array_unique(array_filter($items->pluck('locale')->all(), static fn (mixed $locale): bool => is_string($locale) && $locale !== '')));

        $this->invalidateNavigationCaches($locales === [] ? ['ar', 'en'] : $locales);
        $this->auditService->log(
            action: 'menu.reordered',
            userId: $this->currentUserId(),
            entityType: MenuItem::class,
            metadata: [
                'tree_type' => $treeType,
                'item_ids' => $itemIds,
            ],
        );

        return true;
    }

    public function toggleItemState(int $itemId, bool $enabled): bool
    {
        $item = MenuItem::query()->find($itemId);

        if (! $item instanceof MenuItem) {
            return false;
        }

        $this->authorizeMenu('update', $item);

        $updated = MenuItem::query()->whereKey($itemId)->update(['is_enabled' => $enabled]) > 0;

        if (! $updated) {
            return false;
        }

        $locale = is_string($item->locale) ? $item->locale : null;
        $this->invalidateNavigationCaches($locale !== null ? [$locale] : ['ar', 'en']);
        $this->auditService->log(
            action: 'menu.toggled',
            userId: $this->currentUserId(),
            entityType: MenuItem::class,
            entityId: $itemId,
            metadata: [
                'item_id' => $itemId,
                'is_enabled' => $enabled,
                'group_key' => $item->group_key,
                'locale' => $locale,
            ],
        );

        return true;
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
        $locale = $this->normalizeLocale($locale);
        $tree = $this->cacheService->remember(
            $this->treeCacheKey($groupKey, $locale),
            fn (): NavigationTreeDTO => $this->buildTree($groupKey, $locale),
            (int) config('cache.navigation_ttl', 14400),
        );

        if (! $tree instanceof NavigationTreeDTO) {
            throw new InvalidArgumentException('Navigation cache returned an unexpected tree payload.');
        }

        return ($currentPath === null || $currentPath === '')
            ? $tree
            : $this->withActiveState($tree, $currentPath);
    }

    private function buildTree(string $groupKey, string $locale): NavigationTreeDTO
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
                    ->with([
                        'pageTarget.translations',
                        'children' => fn ($childQuery) => $childQuery
                            ->enabled()
                            ->forLocale($locale)
                            ->orderBy('sort_order')
                            ->with('pageTarget.translations'),
                    ]),
            ])
            ->orderBy('sort_order')
            ->get();

        $mappedItems = array_values(array_filter(array_map(
            fn (MenuItem $item): ?MenuItemDTO => $this->mapItem($item, $locale, null),
            $items->all(),
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
            $item->children->all(),
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

    private function withActiveState(NavigationTreeDTO $tree, string $currentPath): NavigationTreeDTO
    {
        return new NavigationTreeDTO(
            treeType: $tree->treeType,
            locale: $tree->locale,
            direction: $tree->direction,
            items: array_map(
                fn (MenuItemDTO $item): MenuItemDTO => $this->itemWithActiveState($item, $currentPath),
                $tree->items,
            ),
        );
    }

    private function itemWithActiveState(MenuItemDTO $item, string $currentPath): MenuItemDTO
    {
        $children = array_map(
            fn (MenuItemDTO $child): MenuItemDTO => $this->itemWithActiveState($child, $currentPath),
            $item->children,
        );

        $childIsActive = count(array_filter($children, static fn (MenuItemDTO $child): bool => $child->isActive)) > 0;
        $isActive = $this->isActive($item->resolvedUrl, $currentPath) || $childIsActive;

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
            isActive: $isActive,
            sortOrder: $item->sortOrder,
            depth: $item->depth,
            isEnabled: $item->isEnabled,
            isUtility: $item->isUtility,
            openInNewTab: $item->openInNewTab,
            children: $children,
        );
    }

    private function resolveItemUrl(MenuItem $item, string $locale): ?string
    {
        if ($item->target_kind === 'page' && $item->pageTarget instanceof Page) {
            return $this->resolvePageUrl($item->pageTarget, $locale);
        }

        if ($item->target_kind === 'route' && is_string($item->route_name) && $item->route_name !== '' && Route::has($item->route_name)) {
            $route = Route::getRoutes()->getByName($item->route_name);
            $parameters = $route !== null && in_array('locale', $route->parameterNames(), true)
                ? ['locale' => $locale]
                : [];

            return route($item->route_name, $parameters, false);
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

    private function normalizeLocale(?string $locale): string
    {
        if (! is_string($locale) || ! in_array($locale, ['ar', 'en'], true)) {
            throw new InvalidArgumentException('Menu items must use locale ar or en.');
        }

        return $locale;
    }

    private function assertGroupKey(string $groupKey): void
    {
        if (! in_array($groupKey, self::GROUP_KEYS, true)) {
            throw new InvalidArgumentException('Unsupported menu group key: '.$groupKey);
        }
    }

    private function assertItemType(string $itemType, string $groupKey): void
    {
        if (! in_array($itemType, self::TREE_TYPES, true) || $itemType !== $groupKey) {
            throw new InvalidArgumentException('Menu item type must match the current group key.');
        }
    }

    private function assertTreeType(string $treeType): void
    {
        if (! in_array($treeType, self::TREE_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported menu tree type: '.$treeType);
        }
    }

    private function assertDepthAllowed(int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Menu depth greater than 2 is not allowed.');
        }
    }

    private function assertSubtreeDepthAllowed(MenuItem $item, int $newDepth): void
    {
        $relativeDepth = $this->deepestRelativeChildDepth($item);

        if (($newDepth + $relativeDepth) > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Moving this menu item would exceed the maximum depth of 2.');
        }
    }

    private function assertTargetConfiguration(MenuItemDataDTO $payload, string $locale): void
    {
        if (! in_array($payload->targetType, self::SUPPORTED_TARGET_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported menu target type: '.$payload->targetType);
        }

        if ($payload->targetType === 'page') {
            if ($payload->targetId === null) {
                throw new InvalidArgumentException('Page menu items require a target page ID.');
            }

            $page = Page::query()->with('translations')->find($payload->targetId);

            if (! $page instanceof Page || $this->findTranslation($page, $locale) === null) {
                throw new InvalidArgumentException('Page menu items must reference a page available for the same locale.');
            }

            return;
        }

        if ($payload->targetType === 'route') {
            if (! is_string($payload->routeName) || $payload->routeName === '' || ! Route::has($payload->routeName)) {
                throw new InvalidArgumentException('Route menu items require a valid route name.');
            }

            return;
        }

        if ($this->sanitizeTargetUrl($payload->url) === null) {
            throw new InvalidArgumentException('URL menu items require a valid internal or absolute URL.');
        }
    }

    private function sanitizeTargetUrl(?string $url): ?string
    {
        return UrlSanitizer::sanitize($url);
    }

    private function currentUserId(): ?int
    {
        $user = $this->authFactory?->guard((string) config('auth.admin_guard', 'web'))->user();

        return $user !== null ? (int) $user->getAuthIdentifier() : null;
    }

    /** @param class-string|MenuItem $subject */
    private function authorizeMenu(string $ability, string|MenuItem $subject): void
    {
        $user = $this->authFactory?->guard((string) config('auth.admin_guard', 'web'))->user();

        if (! $user instanceof User || Gate::forUser($user)->denies($ability, $subject)) {
            throw new AuthorizationException('This user is not authorized to manage menu items.');
        }
    }

    private function parentForPayload(?int $parentId, string $groupKey, string $locale, ?MenuItem $item = null): ?MenuItem
    {
        if ($parentId === null) {
            return null;
        }

        $parent = MenuItem::query()->find($parentId);

        if (! $parent instanceof MenuItem) {
            throw new InvalidArgumentException('Parent menu item was not found.');
        }

        if ($item instanceof MenuItem && (int) $item->getKey() === (int) $parent->getKey()) {
            throw new InvalidArgumentException('A menu item cannot be its own parent.');
        }

        if ($item instanceof MenuItem && $this->isDescendantOf($parent, $item)) {
            throw new InvalidArgumentException('A menu item cannot be moved under one of its descendants.');
        }

        if ((string) $parent->group_key !== $groupKey) {
            throw new InvalidArgumentException('Parent menu items must stay within the same group.');
        }

        if ((string) $parent->locale !== $locale) {
            throw new InvalidArgumentException('Parent menu items must stay within the same locale tree.');
        }

        return $parent;
    }

    private function deepestRelativeChildDepth(MenuItem $item): int
    {
        $item->loadMissing('children.children');
        $deepest = 0;

        foreach ($item->children as $child) {
            $relativeDepth = 1 + $this->deepestRelativeChildDepth($child);
            $deepest = max($deepest, $relativeDepth);
        }

        return $deepest;
    }

    private function isDescendantOf(MenuItem $candidateParent, MenuItem $item): bool
    {
        $candidateParent->loadMissing('children.children');

        foreach ($candidateParent->children as $child) {
            if ((int) $child->getKey() === (int) $item->getKey() || $this->isDescendantOf($child, $item)) {
                return true;
            }
        }

        return false;
    }

    private function nextSortOrder(string $groupKey, string $locale, ?int $parentId): int
    {
        $currentMax = MenuItem::query()
            ->where('group_key', $groupKey)
            ->where('locale', $locale)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return ((int) $currentMax) + 1;
    }

    private function syncChildDepths(MenuItem $item, int $depth): void
    {
        $item->loadMissing('children.children');

        foreach ($item->children as $child) {
            $childDepth = $depth + 1;
            $this->assertDepthAllowed($childDepth);
            $child->forceFill(['depth' => $childDepth])->save();
            $this->syncChildDepths($child, $childDepth);
        }
    }

    private function deleteSubtree(MenuItem $item): void
    {
        $item->loadMissing('children.children');

        foreach ($item->children as $child) {
            $this->deleteSubtree($child);
        }

        $item->delete();
    }

    /**
     * @param  array<int, MenuTreeNodeDTO>  $tree
     * @return array<int, int>
     */
    private function collectTreeItemIds(array $tree): array
    {
        $ids = [];

        foreach ($tree as $node) {
            if (! $node instanceof MenuTreeNodeDTO) {
                throw new InvalidArgumentException('Tree reorder nodes must be MenuTreeNodeDTO instances.');
            }

            $ids[] = $node->itemId;

            if ($node->children !== []) {
                $ids = array_merge($ids, $this->collectTreeItemIds($node->children));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, MenuItem>  $items
     */
    private function applyTreeNode(MenuTreeNodeDTO $node, ?int $parentId, int $depth, int $sortOrder, string $treeType, array $items): void
    {
        $this->assertDepthAllowed($depth);

        $item = $items[$node->itemId] ?? null;

        if (! $item instanceof MenuItem) {
            throw new InvalidArgumentException('Menu tree node references an unknown item.');
        }

        if ((string) $item->group_key !== $treeType) {
            throw new InvalidArgumentException('Menu tree reorder cannot move items across groups.');
        }

        $item->forceFill([
            'parent_id' => $parentId,
            'depth' => $depth,
            'sort_order' => $sortOrder,
        ])->save();

        foreach (array_values($node->children) as $index => $childNode) {
            if (! $childNode instanceof MenuTreeNodeDTO) {
                throw new InvalidArgumentException('Tree reorder children must be MenuTreeNodeDTO instances.');
            }

            $this->applyTreeNode($childNode, (int) $item->getKey(), $depth + 1, $index + 1, $treeType, $items);
        }
    }

    private function treeCacheKey(string $groupKey, string $locale): string
    {
        return 'menu.tree.'.$groupKey.'.'.$locale;
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function invalidateNavigationCaches(array $locales): void
    {
        $normalizedLocales = array_values(array_unique(array_filter($locales, static fn (mixed $locale): bool => is_string($locale) && in_array($locale, ['ar', 'en'], true))));

        foreach ($normalizedLocales as $locale) {
            foreach (self::TREE_TYPES as $treeType) {
                $this->cacheService->forget($this->treeCacheKey($treeType, $locale));
            }

            $this->cacheService->forget('navigation.payload.'.$locale);
        }

        $this->cacheService->flushTags(['public-pages', 'public-shell', 'navigation']);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditMetadata(MenuItem $item): array
    {
        return [
            'item_id' => (int) $item->getKey(),
            'label' => (string) $item->label,
            'group_key' => $item->group_key,
            'locale' => $item->locale,
            'target_kind' => $item->target_kind,
            'parent_id' => $item->parent_id,
        ];
    }

    /**
     * Retrieve the full admin tree for a group and locale (including disabled items).
     *
     * @return list<MenuItemDTO>
     */
    public function getAdminTree(string $groupKey, string $locale): array
    {
        $rows = MenuItem::query()
            ->whereNull('parent_id')
            ->where('group_key', $groupKey)
            ->where('locale', $locale)
            ->with([
                'pageTarget.translations',
                'children' => fn ($q) => $q
                    ->where('locale', $locale)
                    ->orderBy('sort_order')
                    ->with([
                        'pageTarget.translations',
                        'children' => fn ($q2) => $q2
                            ->where('locale', $locale)
                            ->orderBy('sort_order')
                            ->with('pageTarget.translations'),
                    ]),
            ])
            ->orderBy('sort_order')
            ->get();

        return array_values(array_map(
            fn (MenuItem $item): MenuItemDTO => $this->mapAdminItem($item),
            $rows->all(),
        ));
    }

    /**
     * Find a single menu item by ID for admin editing.
     */
    public function findAdminItem(int $itemId): ?MenuItemDTO
    {
        $row = MenuItem::query()->find($itemId);

        if (! $row instanceof MenuItem) {
            return null;
        }

        $row->loadMissing('children.children', 'pageTarget.translations');

        return $this->mapAdminItem($row);
    }

    /**
     * Map a MenuItem model to a MenuItemDTO for admin use (includes disabled items).
     */
    private function mapAdminItem(MenuItem $item): MenuItemDTO
    {
        $children = array_values(array_map(
            fn (MenuItem $child): MenuItemDTO => $this->mapAdminItem($child),
            $item->children->all(),
        ));

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
            resolvedUrl: $item->url,
            target: $item->target,
            routeName: $item->route_name,
            cssToken: $item->css_token,
            icon: $item->icon,
            isActive: false,
            sortOrder: (int) $item->sort_order,
            depth: (int) $item->depth,
            isEnabled: (bool) $item->is_enabled,
            isUtility: (bool) $item->is_utility,
            openInNewTab: (bool) $item->open_in_new_tab,
            children: $children,
        );
    }
}
