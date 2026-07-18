@php
    $searchItems = [];
    $appendSearchItems = function (array $items) use (&$appendSearchItems, &$searchItems): void {
        foreach ($items as $item) {
            if ($item->resolvedUrl) {
                $searchItems[] = ['label' => $item->label, 'url' => $item->resolvedUrl];
            }

            if (!empty($item->children)) {
                $appendSearchItems($item->children);
            }
        }
    };

    $appendSearchItems($navigation->header->items);
@endphp

<header id="site-header" class="absolute top-0 z-[200] w-full pt-3 font-hacen"
        x-data="mobileNav()"
        data-search-items="{{ json_encode($searchItems, JSON_THROW_ON_ERROR) }}"
        @keydown.escape.window="closeAll()"
        @keydown.window.ctrl.k.prevent="openSearch()"
        @keydown.window.meta.k.prevent="openSearch()"
        @click.outside="closeForOutsideClick()"
        :class="headerClass()">
    <div class="container">
        @include('public.layout.emergency-notice')

        <div class="site-nav-shell" :class="shellClass()">
            <div class="site-nav-shell__main">
                <a href="/{{ $locale }}" aria-label="{{ __('public.home') }}" class="site-nav-brand">
                    <img src="/images/logo-spu.png" alt="{{ __('public.spu_logo_alt') }}" class="h-auto w-[9.25rem] sm:w-[11rem] xl:w-[13.5rem]">
                </a>

                <nav class="hidden flex-1 justify-center 2xl:flex" aria-label="{{ __('public.primary_navigation') }}">
                    <ul class="site-nav-list">
                        @foreach ($navigation->header->items as $item)
                            <li class="site-nav-item"
                                @if (!empty($item->children))
                                    @mouseenter="openDropdown('{{ $loop->index }}')"
                                    @mouseleave="closeDropdown()"
                                @endif>
                                <a href="{{ $item->resolvedUrl ?? '#' }}"
                                   class="site-nav-link {{ $item->isActive ? 'site-nav-link--active' : '' }}"
                                   @if ($item->isActive) aria-current="page" @endif
                                   @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif>
                                    <span>{{ $item->label }}</span>
                                    @if (!empty($item->children))
                                        <img src="/images/icon-chevron-down-outline.svg" alt="" class="site-nav-link__chevron" aria-hidden="true">
                                    @endif
                                </a>

                                @if (!empty($item->children))
                                     <div x-show="isDropdownOpen('{{ $loop->index }}')"
                                         x-transition:enter="transition duration-200 ease-out"
                                         x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.97]"
                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave="transition duration-150 ease-in"
                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.97]"
                                         style="display: none;"
                                         class="site-nav-dropdown">
                                        @php
                                            $flatDividerRendered = false;
                                            $hasRenderedGroup = false;
                                        @endphp
                                        @foreach ($item->children as $child)
                                            @if (!empty($child->children))
                                                @php $hasRenderedGroup = true; @endphp
                                                <div class="site-nav-dropdown-group">
                                                    <a href="{{ $child->resolvedUrl ?? '#' }}"
                                                       class="site-nav-dropdown-group-header"
                                                       @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                                        {{ $child->label }}
                                                    </a>
                                                    @foreach ($child->children as $featured)
                                                        <a href="{{ $featured->resolvedUrl ?? '#' }}"
                                                           class="site-nav-dropdown-featured"
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
                                                   class="site-nav-dropdown-link"
                                                   @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                                    {{ $child->label }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="site-nav-actions">
                    <button type="button"
                            @click="toggleSearch()"
                            class="site-nav-lang"
                            :aria-expanded="searchOpen.toString()"
                            aria-controls="site-search-panel">
                        <img src="/images/icon-search-outline.svg" alt="" class="h-[1rem] w-[1rem]" aria-hidden="true">
                        <span class="sr-only">{{ __('public.search') }}</span>
                    </button>

                    @foreach ($languageSwitch as $switchLink)
                        @if (!$switchLink->isCurrent)
                            <a href="{{ $switchLink->url }}" class="site-nav-lang" data-language-switch>
                                <img src="/images/ic_outline-language.svg" alt="{{ __('public.language') }}" class="h-[1rem] w-[1rem]">
                                <span>{{ $switchLink->label }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if ($navigation->applyCta)
                        <a href="{{ $navigation->applyCta->url }}"
                           class="hidden items-center gap-2 rounded-full bg-spu-red px-5 py-2.5 text-xs font-bold uppercase tracking-[0.08em] text-white shadow-[0_8px_24px_rgba(111,22,22,0.25)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(111,22,22,0.3)] 2xl:inline-flex"
                           @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" rel="noreferrer" @endif>
                            <span>{{ $navigation->applyCta->label }}</span>
                        </a>
                    @endif

                    <div id="site-search-panel"
                         x-show="searchOpen"
                         x-transition:enter="transition duration-200 ease-out"
                         x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition duration-150 ease-in"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                         style="display: none;"
                         class="absolute right-0 top-[calc(100%+0.75rem)] z-50 w-[min(22rem,calc(100vw-2rem))] rounded-[14px] border border-spu-blue/10 bg-white p-3 shadow-[0_24px_52px_rgba(11,19,50,0.14)] rtl:left-0 rtl:right-auto">
                        <label class="sr-only" for="site-search-input">{{ __('public.search') }}</label>
                        <input id="site-search-input"
                               x-ref="siteSearch"
                               x-model="searchQuery"
                               type="search"
                               class="w-full rounded-[10px] border border-spu-blue/10 px-3 py-2 text-sm font-semibold text-spu-blue outline-none transition focus:border-spu-red"
                               placeholder="{{ __('public.search_placeholder') }}">
                        <div class="mt-2 grid gap-1" x-show="searchResults.length">
                            <template x-for="item in searchResults" :key="searchResultKey(item)">
                                <a :href="item.url"
                                    @click="closeSearchResult()"
                                   class="rounded-[8px] px-3 py-2 text-sm font-semibold text-spu-blue transition hover:bg-spu-blue/5"
                                   x-text="item.label"></a>
                            </template>
                        </div>
                         <p class="mt-2 px-1 text-xs font-semibold text-spu-blue/45" x-show="needsLongerSearchQuery()">
                             {{ __('public.search_hint') }}
                        </p>
                    </div>

                    <button type="button"
                            @click="toggleMobile()"
                            aria-label="{{ __('public.toggle_navigation') }}"
                            class="site-nav-menu-btn 2xl:hidden">
                        <img :src="mobileToggleIcon()" class="h-5 w-5" alt="">
                    </button>
                </div>
            </div>

            @include('public.layout.mobile-navigation')
        </div>
    </div>
</header>
