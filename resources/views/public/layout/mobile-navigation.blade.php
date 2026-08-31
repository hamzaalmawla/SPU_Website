<div id="site-mobile-navigation"
     x-show="mobileNav"
     x-transition:enter="transition duration-250 ease-out"
     x-transition:enter-start="opacity-0 -translate-y-3"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition duration-180 ease-in"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     style="display: none;"
       class="site-nav-mobile-panel nav:hidden">
    <div class="site-nav-mobile-list">
        {{-- The header's search panel is desktop-only, so the mobile menu carries
             its own real search form rather than leaving phone visitors without
             any way to search the site. --}}
        <form method="GET"
              action="/{{ $locale }}/search"
              role="search"
              aria-label="{{ __('public.search_landmark') }}"
              class="mb-3 flex items-center gap-2">
            <label class="sr-only" for="site-mobile-search-input">{{ __('public.search_field_label') }}</label>
            <input id="site-mobile-search-input"
                   type="search"
                   name="q"
                   autocomplete="off"
                   maxlength="100"
                   placeholder="{{ __('public.search_site_placeholder') }}"
                   class="w-full rounded-[10px] border border-spu-blue/10 px-3 py-2.5 text-sm font-semibold text-spu-blue outline-none transition focus:border-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-spu-blue">
            <button type="submit"
                    class="shrink-0 rounded-[10px] bg-spu-red px-4 py-2.5 text-xs font-bold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                {{ __('public.search_submit') }}
            </button>
        </form>

        @foreach ($navigation->header->items as $item)
            <div class="site-nav-mobile-card">
                <div class="site-nav-mobile-row">
                     <a @if($item->resolvedUrl) href="{{ $item->resolvedUrl }}" @else aria-disabled="true" @endif
                       @click="closeAll()"
                       class="site-nav-mobile-link {{ $item->isActive ? 'site-nav-mobile-link--active' : '' }}"
                       @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif>
                        {{ $item->label }}
                    </a>

                    @if (!empty($item->children))
                         <button type="button"
                                data-dropdown-trigger="{{ $loop->index }}"
                                @click.prevent="toggleDropdown('{{ $loop->index }}')"
                                aria-label="{{ __('public.toggle_submenu') }}"
                                :aria-expanded="isDropdownOpen('{{ $loop->index }}').toString()"
                                aria-controls="site-mobile-submenu-{{ $loop->index }}"
                                class="site-nav-mobile-toggle">
                            <img src="/images/icon-chevron-down-outline.svg" class="h-2.5 w-2.5 transition-transform duration-200" :class="mobileChevronClass('{{ $loop->index }}')" alt="" width="24" height="24" loading="lazy" decoding="async">
                        </button>
                    @endif
                </div>

                @if (!empty($item->children))
                     <div id="site-mobile-submenu-{{ $loop->index }}"
                          x-show="isDropdownOpen('{{ $loop->index }}')"
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
                            $isResearchMenu = $item->resolvedUrl !== null
                                && preg_match('~/research/?$~', (string) parse_url($item->resolvedUrl, PHP_URL_PATH)) === 1;
                        @endphp
                        @foreach ($item->children as $child)
                            @if (!empty($child->children) && ! $isResearchMenu)
                                @php $hasRenderedGroup = true; @endphp
                                <div class="site-nav-mobile-group">
                                     <a @if($child->resolvedUrl) href="{{ $child->resolvedUrl }}" @else aria-disabled="true" @endif
                                       @click="closeAll()"
                                       class="site-nav-mobile-group-header"
                                       @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                        {{ $child->label }}
                                    </a>
                                    @foreach ($child->children as $featured)
                                         <a @if($featured->resolvedUrl) href="{{ $featured->resolvedUrl }}" @else aria-disabled="true" @endif
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
                                 <a @if($child->resolvedUrl) href="{{ $child->resolvedUrl }}" @else aria-disabled="true" @endif
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
                <img src="/images/icon-user-graduate-outline.svg" alt="" class="h-5 w-5" width="24" height="24" loading="lazy" decoding="async">
                <span>{{ $navigation->applyCta->label }}</span>
            </a>
        @endif
    </div>
</div>
