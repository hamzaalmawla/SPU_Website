<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\MenuServiceInterface;
use App\DTOs\MenuItemDataDTO;
use App\DTOs\MenuItemDTO;
use App\DTOs\MenuTreeNodeDTO;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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

    protected static ?string $navigationLabel = 'Menu Builder';

    protected static ?string $title = 'Manage Menus';

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

    private MenuServiceInterface $menuService;

    public function boot(MenuServiceInterface $menuService): void
    {
        $this->menuService = $menuService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-menu');
    }

    public function mount(): void
    {
        $this->loadMenuTrees();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('menu_groups')
                    ->tabs([
                        $this->buildGroupTab('header', 'Header Navigation'),
                        $this->buildGroupTab('footer', 'Footer Navigation'),
                        $this->buildGroupTab('utility', 'Utility Navigation'),
                    ])
                    ->persistTabInQueryString('group')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
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

        $this->dispatch('open-modal', id: 'edit-menu-item', data: [
            'itemId' => $item->id,
            'label_ar' => $item->locale === 'ar' ? $item->label : '',
            'label_en' => $item->locale === 'en' ? $item->label : '',
            'target_type' => $item->targetType,
            'url' => $item->url ?? '',
            'is_enabled' => $item->isEnabled,
            'open_in_new_tab' => $item->openInNewTab,
        ]);
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
                label: (string) ($formData['label_ar'] ?? $formData['label_en'] ?? $item->label),
                itemType: $item->itemType,
                groupKey: $item->groupKey,
                locale: $item->locale,
                targetType: (string) ($formData['target_type'] ?? $item->targetType),
                parentId: $item->parentId,
                targetId: ($formData['target_type'] ?? $item->targetType) === 'page'
                    ? ($formData['target_id'] ?? $item->targetId)
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
    public function reorderItems(array $orderedTree): void
    {
        Gate::authorize('manage-menu');

        try {
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
                    parentId: isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null,
                    targetId: ($data['target_type'] ?? 'url') === 'page' ? ($data['target_id'] ?? null) : null,
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

    private function loadMenuTrees(): void
    {
        $this->menuTrees = [];
        $this->menuTreeDTOs = [];

        $dtoToArray = function (MenuItemDTO $item) use (&$dtoToArray): array {
            return [
                'id' => $item->id,
                'label' => $item->label,
                'targetType' => $item->targetType,
                'url' => $item->url,
                'resolvedUrl' => $item->resolvedUrl,
                'isEnabled' => $item->isEnabled,
                'openInNewTab' => $item->openInNewTab,
                'sortOrder' => $item->sortOrder,
                'children' => array_map($dtoToArray, $item->children),
            ];
        };

        foreach (MenuServiceInterface::GROUP_KEYS as $group) {
            foreach (['ar', 'en'] as $locale) {
                $items = $this->loadAdminTreeItems($group, $locale);
                $this->menuTreeDTOs[$group][$locale] = $items;
                $this->menuTrees[$group][$locale] = array_map($dtoToArray, $items);
            }
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
            $this->loadMenuTrees();
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

            if (isset($node['children']) && is_array($node['children']) && $depth < self::MAX_DEPTH - 1) {
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

    private function buildGroupTab(string $groupKey, string $label): Tab
    {
        return Tab::make($groupKey)
            ->label($label)
            ->schema([
                Tabs::make("{$groupKey}_locales")
                    ->tabs([
                        Tab::make("{$groupKey}_ar")
                            ->label('العربية (AR)')
                            ->schema([
                                View::make('filament.pages.partials.menu-tree')
                                    ->viewData([
                                        'group' => $groupKey,
                                        'locale' => 'ar',
                                    ]),
                            ]),
                        Tab::make("{$groupKey}_en")
                            ->label('English (EN)')
                            ->schema([
                                View::make('filament.pages.partials.menu-tree')
                                    ->viewData([
                                        'group' => $groupKey,
                                        'locale' => 'en',
                                    ]),
                            ]),
                    ]),
            ]);
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
            Toggle::make('is_enabled')
                ->label('Enabled')
                ->default(true),
            Toggle::make('open_in_new_tab')
                ->label('Open in New Tab')
                ->default(false),
        ];
    }
}
