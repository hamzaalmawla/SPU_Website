@php
    /** @var array $item */
    /** @var int $depth */
    /** @var string $locale */
    $maxDepth = 2;
@endphp

<div
    data-menu-item="{{ $item['id'] }}"
    @class([
        'border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900 transition',
        'ml-6' => $depth > 0,
    ])
>
    <div class="flex items-center justify-between px-3 py-2 gap-3">
        {{-- Drag handle --}}
        <div class="flex items-center gap-2 flex-1 min-w-0">
            <span class="cursor-grab text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300" title="Drag to reorder">
                <x-heroicon-s-bars-2 class="w-4 h-4" />
            </span>

            {{-- Label --}}
            <span @class([
                'text-sm font-medium truncate',
                'text-gray-900 dark:text-gray-100' => $item['isEnabled'],
                'text-gray-400 dark:text-gray-500 line-through' => !$item['isEnabled'],
            ])>
                {{ $item['label'] }}
            </span>

            {{-- Target type badge --}}
            <span @class([
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => ($item['targetType'] ?? '') === 'page',
                'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => ($item['targetType'] ?? '') === 'url',
                'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' => ($item['targetType'] ?? '') === 'route',
            ])>
                {{ $item['targetType'] ?? '' }}
            </span>

            @if ($item['openInNewTab'] ?? false)
                <span class="text-gray-400 dark:text-gray-500" title="Opens in new tab">
                    <x-heroicon-s-arrow-top-right-on-square class="w-3.5 h-3.5" />
                </span>
            @endif

            @if ($item['url'] ?? null)
                <span class="text-xs text-gray-400 dark:text-gray-500 truncate max-w-[200px]" title="{{ $item['url'] }}">
                    {{ $item['url'] }}
                </span>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-1 shrink-0">
            <button
                type="button"
                wire:click="toggleItem({{ $item['id'] }}, {{ ($item['isEnabled'] ?? false) ? 'false' : 'true' }})"
                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                title="{{ ($item['isEnabled'] ?? false) ? 'Disable' : 'Enable' }}"
            >
                @if ($item['isEnabled'] ?? false)
                    <x-heroicon-s-eye class="w-4 h-4 text-green-500" />
                @else
                    <x-heroicon-s-eye-slash class="w-4 h-4 text-gray-400" />
                @endif
            </button>

            <button
                type="button"
                wire:click="editItem({{ $item['id'] }})"
                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                title="Edit"
            >
                <x-heroicon-s-pencil-square class="w-4 h-4 text-blue-500" />
            </button>

            <button
                type="button"
                wire:click="deleteItem({{ $item['id'] }})"
                wire:confirm="Are you sure you want to delete this menu item and all its children?"
                class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                title="Delete"
            >
                <x-heroicon-s-trash class="w-4 h-4 text-red-500" />
            </button>
        </div>
    </div>

    {{-- Children --}}
    @if (!empty($item['children']) && $depth < $maxDepth - 1)
        <div data-children class="pb-2 px-2 space-y-1">
            @foreach ($item['children'] as $child)
                @include('filament.pages.partials.menu-tree-item', [
                    'item' => $child,
                    'depth' => $depth + 1,
                    'locale' => $locale,
                ])
            @endforeach
        </div>
    @endif
</div>
