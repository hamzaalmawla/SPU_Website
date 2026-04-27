@php
    /** @var string $group */
    /** @var string $locale */
    $items = $this->getTreeForGroup($group, $locale);
@endphp

<div class="space-y-1">
    @if (empty($items))
        <p class="text-sm text-gray-500 dark:text-gray-400 italic py-2">
            No menu items for this group and locale.
        </p>
    @else
        @foreach ($items as $item)
            @include('filament.pages.partials.menu-tree-item', [
                'item' => $item,
                'depth' => 0,
                'locale' => $locale,
            ])
        @endforeach
    @endif
</div>
