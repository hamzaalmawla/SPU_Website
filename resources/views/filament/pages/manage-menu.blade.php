<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Group Tabs --}}
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
            @foreach ($this->getGroupKeys() as $group)
                <button
                    wire:click="switchGroup('{{ $group }}')"
                    @class([
                        'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                        'bg-primary-500 text-white' => $this->activeGroup === $group,
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800' => $this->activeGroup !== $group,
                    ])
                >
                    {{ ucfirst($group) }} Navigation
                </button>
            @endforeach
        </div>

        {{-- Locale Sub-tabs --}}
        @foreach (['ar' => 'العربية (AR)', 'en' => 'English (EN)'] as $locale => $localeLabel)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-base font-semibold mb-4 text-gray-900 dark:text-gray-100">
                    {{ $localeLabel }}
                </h3>

                @php
                    $items = $this->getTreeForGroup($this->activeGroup, $locale);
                @endphp

                @if (empty($items))
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        No menu items for this group and locale.
                    </p>
                @else
                    <div
                        class="space-y-1"
                        x-data="menuTree('{{ $this->activeGroup }}', '{{ $locale }}')"
                    >
                        @foreach ($items as $item)
                            @include('filament.pages.partials.menu-tree-item', [
                                'item' => $item,
                                'depth' => 0,
                                'locale' => $locale,
                            ])
                        @endforeach

                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950 transition"
                                x-on:click="saveOrder()"
                            >
                                <x-heroicon-s-arrows-up-down class="w-4 h-4" />
                                Save Order
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
        function menuTree(group, locale) {
            return {
                group: group,
                locale: locale,
                saveOrder() {
                    const tree = this.collectOrder(this.$el);
                    this.$wire.reorderItems(tree);
                },
                collectOrder(container) {
                    const items = [];
                    const children = container.querySelectorAll(':scope > [data-menu-item]');
                    children.forEach(el => {
                        const entry = { id: parseInt(el.dataset.menuItem) };
                        const childContainer = el.querySelector('[data-children]');
                        if (childContainer) {
                            entry.children = this.collectOrder(childContainer);
                        }
                        items.push(entry);
                    });
                    return items;
                }
            };
        }
    </script>
    @endpush
</x-filament-panels::page>
