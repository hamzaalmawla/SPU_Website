<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Navigation\MenuServiceInterface;
use App\DTOs\Navigation\MenuItemDataDTO;
use App\DTOs\Navigation\MenuItemDTO;
use App\DTOs\Navigation\MenuTreeNodeDTO;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * Filament custom page for managing navigation menus (header, footer, utility).
 *
 * All business logic is delegated to MenuServiceInterface.
 */
class ManageMenu extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $slug = 'manage-menu';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.manage-menu';

    private const MAX_DEPTH = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public string $activeGroup = 'header';

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    public array $menuTrees = [];

    /** @var array<string, array<string, list<MenuItemDTO>>> */
    private array $menuTreeDTOs = [];

    public bool $isEditing = false;

    public ?int $editingItemId = null;

    /** @var array<string, mixed> */
    public array $editForm = [];

    private MenuServiceInterface $menuService;

    public function boot(MenuServiceInterface $menuService): void
    {
        $this->menuService = $menuService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-menu');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.menu_builder');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_menus');
    }

    public function mount(): void
    {
        $this->loadMenuTrees();
    }

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->addItemAction(),
        ];
    }

    private function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label('Add Menu Item')
            ->icon('heroicon-o-plus')
            ->form($this->itemFormSchema())
            ->action(function (array $data): void {
                $this->createItem($data);
            });
    }

    // ──────────────────────────────────────────────
    // Public Livewire Methods (called from Blade)
    // ──────────────────────────────────────────────

    public function switchGroup(string $group): void
    {
        Gate::authorize('manage-menu');

        if (in_array($group, MenuServiceInterface::GROUP_KEYS, true)) {
            $this->activeGroup = $group;
            $this->loadMenuTrees($group);
        }
    }

    public function editItem(int $itemId): void
    {
        Gate::authorize('manage-menu');

        $item = $this->findItemInTrees($itemId);

        if ($item === null) {
            Notification::make()
                ->title('Menu item not found.')
                ->danger()
                ->send();

            return;
        }

        $this->editingItemId = $item->id;
        $this->editForm = [
            'label' => $item->label,
            'locale' => $item->locale ?? 'ar',
            'group_key' => $item->groupKey,
            'target_type' => $item->targetType,
            'target_id' => $item->targetId,
            'url' => $item->url ?? '',
            'parent_id' => $item->parentId,
            'is_enabled' => $item->isEnabled,
            'open_in_new_tab' => $item->openInNewTab,
        ];
        $this->isEditing = true;
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
        $this->editingItemId = null;
        $this->editForm = [];
    }

    public function updateEditingItem(): void
    {
        if ($this->editingItemId === null) {
            return;
        }

        $this->updateItem($this->editingItemId, $this->editForm);
        $this->cancelEdit();
    }

    public function updateItem(int $itemId, array $formData): void
    {
        Gate::authorize('manage-menu');

        $item = $this->findItemInTrees($itemId);

        if ($item === null) {
            Notification::make()
                ->title('Menu item not found.')
                ->danger()
                ->send();

            return;
        }

        try {
            $payload = new MenuItemDataDTO(
                label: trim((string) ($formData['label'] ?? $item->label)),
                itemType: $item->itemType,
                groupKey: $item->groupKey,
                locale: $item->locale,
                targetType: (string) ($formData['target_type'] ?? $item->targetType),
                parentId: isset($formData['parent_id']) && $formData['parent_id'] !== '' ? (int) $formData['parent_id'] : null,
                targetId: ($formData['target_type'] ?? $item->targetType) === 'page'
                    ? (isset($formData['target_id']) && $formData['target_id'] !== '' ? (int) $formData['target_id'] : $item->targetId)
                    : null,
                url: ($formData['target_type'] ?? $item->targetType) === 'url'
                    ? (string) ($formData['url'] ?? '')
                    : null,
                isEnabled: (bool) ($formData['is_enabled'] ?? $item->isEnabled),
                openInNewTab: (bool) ($formData['open_in_new_tab'] ?? $item->openInNewTab),
                sortOrder: $item->sortOrder,
            );

            $this->menuService->updateItem($itemId, $payload);

            Notification::make()
                ->title('Menu item updated.')
                ->success()
                ->send();

            $this->loadMenuTrees();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to update menu item.')
                ->body('Please review the menu item and try again.')
                ->danger()
                ->send();
        }
    }

    public function deleteItem(int $itemId): void
    {
        Gate::authorize('manage-menu');

        try {
            $this->menuService->deleteItem($itemId);

            Notification::make()
                ->title('Menu item deleted.')
                ->success()
                ->send();

            $this->loadMenuTrees();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to delete menu item.')
                ->body('The menu item could not be deleted.')
                ->danger()
                ->send();
        }
    }

    public function toggleItem(int $itemId, bool $enabled): void
    {
        Gate::authorize('manage-menu');

        try {
            $this->menuService->toggleItemState($itemId, $enabled);

            Notification::make()
                ->title($enabled ? 'Menu item enabled.' : 'Menu item disabled.')
                ->success()
                ->send();

            $this->loadMenuTrees();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to toggle menu item.')
                ->body('The menu item state could not be changed.')
                ->danger()
                ->send();
        }
    }

    /**
     * Handle reorder from the Blade tree UI.
     *
     * @param  array<int, array{id: int, children?: array<int, array{id: int}>}>  $orderedTree
     */
    public function reorderItems(array $orderedTree, string $locale): void
    {
        Gate::authorize('manage-menu');

        try {
            $this->assertSubmittedTreeMatchesActiveLocale($orderedTree, $locale);
            $treeNodes = $this->buildTreeNodesFromOrder($orderedTree);
            $this->menuService->reorderTree($this->activeGroup, $treeNodes);

            Notification::make()
                ->title('Menu order saved.')
                ->success()
                ->send();

            $this->loadMenuTrees();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to reorder menu.')
                ->body('The menu order could not be saved.')
                ->danger()
                ->send();
        }
    }

    /**
     * Get the menu tree for a specific group and locale.
     *
     * @return list<array<string, mixed>>
     */
    public function getTreeForGroup(string $group, string $locale): array
    {
        if (! isset($this->menuTrees[$group][$locale])) {
            $this->loadMenuTrees($group);
        }

        return $this->menuTrees[$group][$locale] ?? [];
    }

    /**
     * Get available group keys.
     *
     * @return list<string>
     */
    public function getGroupKeys(): array
    {
        return MenuServiceInterface::GROUP_KEYS;
    }

    /**
     * @return array<int, string>
     */
    public function getPageTargetOptions(?string $locale = null): array
    {
        return $this->menuService->getPageTargetOptions($locale ?? (string) ($this->editForm['locale'] ?? 'ar'));
    }

    /**
     * @return array<int, string>
     */
    public function getParentOptionsForEdit(): array
    {
        $locale = (string) ($this->editForm['locale'] ?? 'ar');
        $group = (string) ($this->editForm['group_key'] ?? $this->activeGroup);
        $items = $this->getTreeForGroup($group, $locale);

        return $this->flattenParentOptions($items, $this->editingItemId);
    }

    /**
     * @return array<int, string>
     */
    public function getParentOptionsForLocale(string $locale): array
    {
        return $this->flattenParentOptions($this->getTreeForGroup($this->activeGroup, $locale), null);
    }

    /**
     * @return array<int, string>
     */
    public function getSharedPageTargetOptions(): array
    {
        $arabic = $this->menuService->getPageTargetOptions('ar');
        $english = $this->menuService->getPageTargetOptions('en');

        return array_intersect_key($arabic, $english);
    }

    // ──────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────

    private function createItem(array $data): void
    {
        Gate::authorize('manage-menu');

        try {
            foreach (['ar', 'en'] as $locale) {
                $labelKey = "label_{$locale}";
                $label = (string) ($data[$labelKey] ?? '');

                if ($label === '') {
                    continue;
                }

                $payload = new MenuItemDataDTO(
                    label: $label,
                    itemType: $this->activeGroup,
                    groupKey: $this->activeGroup,
                    locale: $locale,
                    targetType: (string) ($data['target_type'] ?? 'url'),
                    parentId: isset($data["parent_{$locale}"]) && $data["parent_{$locale}"] !== '' ? (int) $data["parent_{$locale}"] : null,
                    targetId: ($data['target_type'] ?? 'url') === 'page' && isset($data['target_id']) && $data['target_id'] !== '' ? (int) $data['target_id'] : null,
                    url: ($data['target_type'] ?? 'url') === 'url' ? (string) ($data['url'] ?? '') : null,
                    isEnabled: (bool) ($data['is_enabled'] ?? true),
                    openInNewTab: (bool) ($data['open_in_new_tab'] ?? false),
                );

                $this->menuService->createItem($payload);
            }

            Notification::make()
                ->title('Menu item created.')
                ->success()
                ->send();

            $this->loadMenuTrees();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to create menu item.')
                ->body('Please review the menu item and try again.')
                ->danger()
                ->send();
        }
    }

    private function loadMenuTrees(?string $group = null): void
    {
        $group ??= $this->activeGroup;

        if (! in_array($group, MenuServiceInterface::GROUP_KEYS, true)) {
            $group = 'header';
        }

        $dtoToArray = function (MenuItemDTO $item) use (&$dtoToArray): array {
            return [
                'id' => $item->id,
                'parentId' => $item->parentId,
                'label' => $item->label,
                'locale' => $item->locale,
                'groupKey' => $item->groupKey,
                'targetType' => $item->targetType,
                'targetId' => $item->targetId,
                'url' => $item->url,
                'resolvedUrl' => $item->resolvedUrl,
                'isEnabled' => $item->isEnabled,
                'openInNewTab' => $item->openInNewTab,
                'sortOrder' => $item->sortOrder,
                'depth' => $item->depth,
                'children' => array_map($dtoToArray, $item->children),
            ];
        };

        unset($this->menuTrees[$group], $this->menuTreeDTOs[$group]);

        foreach (['ar', 'en'] as $locale) {
            $items = $this->loadAdminTreeItems($group, $locale);
            $this->menuTreeDTOs[$group][$locale] = $items;
            $this->menuTrees[$group][$locale] = array_map($dtoToArray, $items);
        }
    }

    /**
     * Load ALL menu items for a group/locale (including disabled) for the admin UI.
     * Delegates to the service layer's admin tree method.
     *
     * @return list<MenuItemDTO>
     */
    private function loadAdminTreeItems(string $group, string $locale): array
    {
        return $this->menuService->getAdminTree($group, $locale);
    }

    private function findItemInTrees(int $itemId): ?MenuItemDTO
    {
        // $menuTreeDTOs is private and not persisted by Livewire between requests.
        // Re-load from DB if empty before searching.
        if ($this->menuTreeDTOs === []) {
            $this->loadMenuTrees($this->activeGroup);
        }

        foreach ($this->menuTreeDTOs as $groups) {
            foreach ($groups as $items) {
                $found = $this->findItemRecursive($items, $itemId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // Fallback: load directly from service in case the item is in a group/locale
        // not yet loaded (e.g. after a group switch without a full reload).
        return $this->menuService->findAdminItem($itemId);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function flattenParentOptions(array $items, ?int $excludedItemId, string $prefix = ''): array
    {
        $options = [];

        foreach ($items as $item) {
            $itemId = (int) $item['id'];

            if ($itemId === $excludedItemId || $this->containsItemId($item['children'] ?? [], $excludedItemId)) {
                continue;
            }

            if ((int) ($item['depth'] ?? 0) < self::MAX_DEPTH) {
                $options[$itemId] = $prefix.(string) $item['label'];
            }

            if (! empty($item['children'])) {
                $options += $this->flattenParentOptions($item['children'], $excludedItemId, $prefix.'— ');
            }
        }

        return $options;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function containsItemId(array $items, ?int $itemId): bool
    {
        if ($itemId === null) {
            return false;
        }

        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $itemId || $this->containsItemId($item['children'] ?? [], $itemId)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, array{id: int, children?: array<int, array{id: int}>}>  $orderedTree */
    private function assertSubmittedTreeMatchesActiveLocale(array $orderedTree, string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported menu locale.');
        }

        $allowedIds = $this->collectTreeIds($this->getTreeForGroup($this->activeGroup, $locale));
        $submittedIds = $this->collectSubmittedTreeIds($orderedTree);

        sort($allowedIds);
        sort($submittedIds);

        if ($submittedIds !== $allowedIds) {
            throw new \InvalidArgumentException('Menu order must include every item in the active locale tree.');
        }

        foreach ($submittedIds as $itemId) {
            if (! in_array($itemId, $allowedIds, true)) {
                throw new \InvalidArgumentException('Menu order contains an item outside the active locale tree.');
            }
        }
    }

    /** @param  list<array<string, mixed>>  $items */
    private function collectTreeIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $ids[] = (int) $item['id'];
            $ids = array_merge($ids, $this->collectTreeIds($item['children'] ?? []));
        }

        return $ids;
    }

    /** @param  array<int, array{id: int, children?: array<int, array{id: int}>}>  $orderedTree */
    private function collectSubmittedTreeIds(array $orderedTree): array
    {
        $ids = [];

        foreach ($orderedTree as $node) {
            $ids[] = (int) $node['id'];
            if (isset($node['children']) && is_array($node['children'])) {
                $ids = array_merge($ids, $this->collectSubmittedTreeIds($node['children']));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<MenuItemDTO>  $items
     */
    private function findItemRecursive(array $items, int $itemId): ?MenuItemDTO
    {
        foreach ($items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }

            $found = $this->findItemRecursive($item->children, $itemId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int, children?: array<int, array{id: int}>}>  $orderedTree
     * @return array<int, MenuTreeNodeDTO>
     */
    private function buildTreeNodesFromOrder(array $orderedTree, int $depth = 0): array
    {
        $nodes = [];

        foreach ($orderedTree as $sortOrder => $node) {
            $children = [];

            if ($depth > self::MAX_DEPTH) {
                throw new \InvalidArgumentException('Menu order exceeds the maximum depth.');
            }

            if (isset($node['children']) && is_array($node['children'])) {
                if ($depth >= self::MAX_DEPTH) {
                    throw new \InvalidArgumentException('Menu order exceeds the maximum depth.');
                }

                $children = $this->buildTreeNodesFromOrder($node['children'], $depth + 1);
            }

            $nodes[] = new MenuTreeNodeDTO(
                itemId: (int) $node['id'],
                sortOrder: $sortOrder,
                depth: $depth,
                children: $children,
            );
        }

        return $nodes;
    }

    /**
     * @return array<int, Component>
     */
    private function itemFormSchema(): array
    {
        return [
            TextInput::make('label_ar')
                ->label('Label (AR)')
                ->required()
                ->maxLength(255),
            TextInput::make('label_en')
                ->label('Label (EN)')
                ->required()
                ->maxLength(255),
            Select::make('target_type')
                ->label('Target Type')
                ->options([
                    'page' => 'Page',
                    'url' => 'Custom URL / External',
                ])
                ->default('url')
                ->required()
                ->reactive(),
            TextInput::make('url')
                ->label('URL')
                ->url()
                ->maxLength(2048)
                ->visible(fn (callable $get): bool => ($get('target_type') ?? 'url') !== 'page'),
            Select::make('target_id')
                ->label('Target Page')
                ->options(fn (): array => $this->getSharedPageTargetOptions())
                ->searchable()
                ->visible(fn (callable $get): bool => ($get('target_type') ?? 'url') === 'page')
                ->required(fn (callable $get): bool => ($get('target_type') ?? 'url') === 'page'),
            Select::make('parent_ar')
                ->label('Parent (AR)')
                ->options(fn (): array => $this->getParentOptionsForLocale('ar'))
                ->searchable()
                ->placeholder('No parent'),
            Select::make('parent_en')
                ->label('Parent (EN)')
                ->options(fn (): array => $this->getParentOptionsForLocale('en'))
                ->searchable()
                ->placeholder('No parent'),
            Toggle::make('is_enabled')
                ->label('Enabled')
                ->default(true),
            Toggle::make('open_in_new_tab')
                ->label('Open in New Tab')
                ->default(false),
        ];
    }
}
