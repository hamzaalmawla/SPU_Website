<x-filament-panels::page>
    <x-admin.cms-shell area="menu">
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
            @foreach ($this->getGroupKeys() as $group)
                <button
                    wire:click="switchGroup('{{ $group }}')"
                    wire:loading.attr="disabled"
                    @class([
                        'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                        'bg-primary-500 text-white' => $this->activeGroup === $group,
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800' => $this->activeGroup !== $group,
                    ])
                >
                    {{ __('admin.menu.navigation', ['group' => ucfirst($group)]) }}
                </button>
            @endforeach
        </div>

        @foreach (['ar' => 'العربية (AR)', 'en' => 'English (EN)'] as $locale => $localeLabel)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                <h3 class="text-base font-semibold mb-4 text-gray-900 dark:text-gray-100">
                    {{ $localeLabel }}
                </h3>

                @php
                    $items = $this->getTreeForGroup($this->activeGroup, $locale);
                @endphp

                @if (empty($items))
                    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                        {{ __('admin.menu.empty') }}
                    </p>
                @else
                    <div
                        class="space-y-1"
                        x-data="menuTree('{{ $this->activeGroup }}', '{{ $locale }}')"
                        data-sortable
                        x-sortable
                        data-sortable-animation-duration="150"
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
                                wire:loading.attr="disabled"
                            >
                                <x-heroicon-s-arrows-up-down class="w-4 h-4" />
                                <span wire:loading.remove>{{ __('admin.menu.save_order') }}</span>
                                <span wire:loading>{{ __('admin.menu.saving_order') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </x-admin.cms-shell>

    @if ($isEditing)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 px-4 py-6" role="dialog" aria-modal="true">
            <form wire:submit="updateEditingItem" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700" dir="{{ ($editForm['locale'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr' }}">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.menu.edit_item') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ strtoupper((string) ($editForm['locale'] ?? 'ar')) }} · {{ ucfirst((string) ($editForm['group_key'] ?? $activeGroup)) }}</p>
                    </div>
                    <button type="button" wire:click="cancelEdit" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                        <x-heroicon-s-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.menu.label') }}</span>
                        <input type="text" wire:model="editForm.label" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.menu.target_type') }}</span>
                        <select wire:model.live="editForm.target_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="url">{{ __('admin.menu.custom_url') }}</option>
                            <option value="page">{{ __('admin.menu.page') }}</option>
                            <option value="route">Named Route</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.menu.parent') }}</span>
                        <select wire:model="editForm.parent_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">{{ __('admin.menu.no_parent') }}</option>
                            @foreach ($this->getParentOptionsForEdit() as $parentId => $parentLabel)
                                <option value="{{ $parentId }}">{{ $parentLabel }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if (($editForm['target_type'] ?? 'url') === 'page')
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.menu.target_page') }}</span>
                            <select wire:model="editForm.target_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="">{{ __('admin.menu.target_page') }}</option>
                                @foreach ($this->getPageTargetOptions((string) ($editForm['locale'] ?? 'ar')) as $pageId => $pageLabel)
                                    <option value="{{ $pageId }}">{{ $pageLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @elseif (($editForm['target_type'] ?? 'url') === 'route')
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Route Name</span>
                            <input type="text" wire:model="editForm.route_name" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </label>
                    @else
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.menu.url') }}</span>
                            <input type="text" wire:model="editForm.url" required maxlength="2048" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </label>
                    @endif

                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="editForm.is_enabled" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        {{ __('admin.menu.enabled') }}
                    </label>

                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="editForm.open_in_new_tab" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        {{ __('admin.menu.new_tab') }}
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelEdit" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-800">{{ __('admin.menu.cancel') }}</button>
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500" wire:loading.attr="disabled">{{ __('admin.menu.save_changes') }}</button>
                </div>
            </form>
        </div>
    @endif

    @push('scripts')
    <script>
        function menuTree(group, locale) {
            return {
                group: group,
                locale: locale,
                saveOrder() {
                    const tree = this.collectOrder(this.$el);
                    this.$wire.reorderItems(tree, this.locale);
                },
                collectOrder(container) {
                    const items = [];
                    const directItems = container.querySelectorAll(':scope > [data-menu-item], :scope > .space-y-1 > [data-menu-item]');
                    directItems.forEach(el => {
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
