<div x-show="mobileNav"
     x-transition:enter="transition duration-250 ease-out"
     x-transition:enter-start="opacity-0 -translate-y-3"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition duration-180 ease-in"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     style="display: none;"
      class="site-nav-mobile-panel xl:hidden">
    <div class="site-nav-mobile-list">
        @foreach ($navigation->header->items as $item)
            <div class="site-nav-mobile-card">
                <div class="site-nav-mobile-row">
                    <a href="{{ $item->resolvedUrl ?? '#' }}"
                       @click="closeAll()"
                       class="site-nav-mobile-link {{ $item->isActive ? 'site-nav-mobile-link--active' : '' }}"
                       @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif>
                        {{ $item->label }}
                    </a>

                    @if (!empty($item->children))
                        <button type="button"
                                @click.prevent="toggleDropdown('{{ $loop->index }}')"
                                aria-label="{{ __('public.toggle_submenu') }}"
                                class="site-nav-mobile-toggle">
                            <img src="/images/icon-chevron-down-outline.svg" class="h-2.5 w-2.5 transition-transform duration-200" :class="mobileChevronClass('{{ $loop->index }}')" alt="">
                        </button>
                    @endif
                </div>

                @if (!empty($item->children))
                    <div x-show="isDropdownOpen('{{ $loop->index }}')"
                         x-transition:enter="transition duration-200 ease-out"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition duration-150 ease-in"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         style="display: none;"
                         class="site-nav-mobile-children">
                        @php
                            $flatDividerRendered = false;
                            $hasRenderedGroup = false;
                        @endphp
                        @foreach ($item->children as $child)
                            @if (!empty($child->children))
                                @php $hasRenderedGroup = true; @endphp
                                <div class="site-nav-mobile-group">
                                    <a href="{{ $child->resolvedUrl ?? '#' }}"
                                       @click="closeAll()"
                                       class="site-nav-mobile-group-header"
                                       @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                        {{ $child->label }}
                                    </a>
                                    @foreach ($child->children as $featured)
                                        <a href="{{ $featured->resolvedUrl ?? '#' }}"
                                           @click="closeAll()"
                                           class="site-nav-mobile-child site-nav-mobile-featured"
                                           @if ($featured->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                            {{ $featured->label }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                @if ($hasRenderedGroup && ! $flatDividerRendered)
                                    <div class="my-2 border-t border-spu-blue/10"></div>
                                    @php $flatDividerRendered = true; @endphp
                                @endif
                                <a href="{{ $child->resolvedUrl ?? '#' }}"
                                   @click="closeAll()"
                                   class="site-nav-mobile-child"
                                   @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                    {{ $child->label }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if ($navigation->applyCta)
            <a href="{{ $navigation->applyCta->url }}"
               @click="closeAll()"
               class="mt-2 flex items-center justify-center gap-2 rounded-xl bg-spu-red px-5 py-3.5 text-sm font-bold text-white shadow-[0_8px_24px_rgba(111,22,22,0.2)]"
               @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" rel="noreferrer" @endif>
                <img src="/images/icon-user-graduate-outline.svg" alt="" class="h-5 w-5">
                <span>{{ $navigation->applyCta->label }}</span>
            </a>
        @endif
    </div>
</div>
