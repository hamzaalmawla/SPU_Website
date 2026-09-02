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

<header id="site-header" class="fixed inset-x-0 top-0 z-[200] w-full pt-3 font-hacen"
        x-data="mobileNav()"
        data-search-items="{{ json_encode($searchItems, JSON_THROW_ON_ERROR) }}"
        data-search-all-label="{{ __('public.search_all_results_for', ['query' => '__QUERY__']) }}"
        @keydown.escape.window="handleEscape()"
        @keydown.window.ctrl.k.prevent="openSearch()"
        @keydown.window.meta.k.prevent="openSearch()"
        @click.outside="closeForOutsideClick()">
    <div class="container site-nav-container">
        @include('public.layout.emergency-notice')

        <button type="button"
                x-show="mobileNav"
                x-cloak
                style="display: none;"
                class="site-nav-backdrop nav:hidden"
                @click="closeMobile()"
                aria-label="{{ $locale === 'ar' ? 'إغلاق القائمة' : 'Close navigation' }}"></button>

        <div class="site-nav-shell" :class="shellClass()">
            <div class="site-nav-shell__main">
                <a href="/{{ $locale }}" aria-label="{{ __('public.home') }}" class="site-nav-brand">
                    <img src="/images/logo-spu.png" alt="{{ __('public.spu_logo_alt') }}" width="562" height="118" class="h-auto w-[8rem] sm:w-[9rem] lg:w-[10rem] xl:w-[10.5rem]" decoding="async">
                </a>

                <nav class="hidden w-full justify-center nav:flex nav:flex-1 nav:basis-auto nav:w-auto nav:items-center" aria-label="{{ __('public.primary_navigation') }}">
                    <ul class="site-nav-list lg:w-full">
                        @foreach ($navigation->header->items as $item)
                            <li class="site-nav-item"
                                @if (!empty($item->children))
                                    @mouseenter="openDropdown('{{ $loop->index }}')"
                                    @mouseleave="closeDropdown($event)"
                                    @focusin="openDropdownForFocus('{{ $loop->index }}')"
                                    @focusout="closeDropdownForFocus($event)"
                                @endif>
                                @if (!empty($item->children) && $item->resolvedUrl)
                                <div class="site-nav-link-composite ">
                                    <a href="{{ $item->resolvedUrl }}"
                                       class="site-nav-link {{ $item->isActive ? 'site-nav-link--active' : '' }}"
                                       @if ($item->isActive) aria-current="page" @endif
                                       @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif>
                                        <span>{{ $item->label }}</span>
                                    </a>
                                    <button type="button"
                                       class="site-nav-dropdown-toggle"
                                       data-dropdown-trigger="{{ $loop->index }}"
                                       @click.stop="toggleDropdown('{{ $loop->index }}')"
                                       :aria-expanded="isDropdownOpen('{{ $loop->index }}').toString()"
                                       aria-controls="site-nav-dropdown-{{ $loop->index }}"
                                       aria-label="{{ __('public.toggle_submenu') }}">
                                        <img src="/images/icon-chevron-down-outline.svg" alt="" class="site-nav-link__chevron" aria-hidden="true" width="24" height="24" decoding="async">
                                    </button>
                                </div>
                                @elseif (!empty($item->children))
                                <button type="button"
                                   class="site-nav-link {{ $item->isActive ? 'site-nav-link--active' : '' }}"
                                   data-dropdown-trigger="{{ $loop->index }}"
                                   @click="toggleDropdown('{{ $loop->index }}')"
                                   :aria-expanded="isDropdownOpen('{{ $loop->index }}').toString()"
                                   aria-controls="site-nav-dropdown-{{ $loop->index }}">
                                    <span>{{ $item->label }}</span>
                                    <img src="/images/icon-chevron-down-outline.svg" alt="" class="site-nav-link__chevron" aria-hidden="true" width="24" height="24" decoding="async">
                                </button>
                                @elseif ($item->resolvedUrl)
                                <a href="{{ $item->resolvedUrl }}"
                                   class="site-nav-link {{ $item->isActive ? 'site-nav-link--active' : '' }}"
                                   @if ($item->isActive) aria-current="page" @endif
                                   @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif><span>{{ $item->label }}</span></a>
                                @else
                                <span class="site-nav-link" aria-disabled="true"><span>{{ $item->label }}</span></span>
                                @endif

                                @if (!empty($item->children))
                                     @php
                                         $isResearchMenu = $item->resolvedUrl !== null
                                             && preg_match('~/research/?$~', (string) parse_url($item->resolvedUrl, PHP_URL_PATH)) === 1;
                                     @endphp
                                     <div id="site-nav-dropdown-{{ $loop->index }}"
                                          x-show="isDropdownOpen('{{ $loop->index }}')"
                                         x-transition:enter="transition duration-200 ease-out"
                                         x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.97]"
                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave="transition duration-150 ease-in"
                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.97]"
                                         style="display: none;"
                                         class="site-nav-dropdown {{ $isResearchMenu ? 'site-nav-dropdown--research' : '' }}">
                                        @php
                                            $flatDividerRendered = false;
                                            $hasRenderedGroup = false;
                                        @endphp
                                        @foreach ($item->children as $child)
                                            @if (!empty($child->children) && ! $isResearchMenu)
                                                @php $hasRenderedGroup = true; @endphp
                                                <div class="site-nav-dropdown-group">
                                                     <a @if($child->resolvedUrl) href="{{ $child->resolvedUrl }}" @else aria-disabled="true" @endif
                                                       class="site-nav-dropdown-group-header"
                                                       @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                                        {{ $child->label }}
                                                    </a>
                                                    @foreach ($child->children as $featured)
                                                         <a @if($featured->resolvedUrl) href="{{ $featured->resolvedUrl }}" @else aria-disabled="true" @endif
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
                                                 <a @if($child->resolvedUrl) href="{{ $child->resolvedUrl }}" @else aria-disabled="true" @endif
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
                    <div class="site-search-wrap relative">
                         <button type="button"
                                x-ref="searchToggle"
                                @click="toggleSearch()"
                                class="site-nav-lang"
                                aria-expanded="false"
                                :aria-expanded="searchOpen.toString()"
                                aria-controls="site-search-panel">
                            <img src="/images/icon-search-outline.svg" alt="" class="h-[1rem] w-[1rem]" aria-hidden="true" width="24" height="24" decoding="async">
                            <span class="sr-only">{{ __('public.search') }}</span>
                        </button>

                        <div id="site-search-panel"
                             x-show="searchOpen"
                             x-transition:enter="transition duration-200 ease-out"
                             x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition duration-150 ease-in"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                             style="display: none;"
                             class="site-search-panel absolute right-0 top-[calc(100%+0.75rem)] z-50 w-[min(22rem,calc(100vw-2rem))] rounded-[14px] border border-spu-blue/10 bg-white p-3 shadow-[0_24px_52px_rgba(11,19,50,0.14)]">
                            {{-- Enter submits to the real results page. The nav-label
                                 suggestions below stay as a fast jump for people who
                                 already know which page they want. --}}
                            <form method="GET"
                                  action="/{{ $locale }}/search"
                                  role="search"
                                  aria-label="{{ __('public.search_landmark') }}">
                                <label class="sr-only" for="site-search-input">{{ __('public.search_field_label') }}</label>
                                <div class="flex items-center gap-2">
                                    <input id="site-search-input"
                                           x-ref="siteSearch"
                                           x-model="searchQuery"
                                           type="search"
                                           name="q"
                                           autocomplete="off"
                                           maxlength="100"
                                           class="w-full rounded-[10px] border border-spu-blue/10 px-3 py-2 text-sm font-semibold text-spu-blue outline-none transition focus:border-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-spu-blue"
                                           placeholder="{{ __('public.search_site_placeholder') }}">
                                    <button type="submit"
                                            class="shrink-0 rounded-[10px] bg-spu-red px-3 py-2 text-xs font-bold text-white transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                                        {{ __('public.search_submit') }}
                                    </button>
                                </div>

                                <button type="submit"
                                        class="mt-2 block w-full rounded-[8px] px-3 py-2 text-start text-xs font-bold text-spu-red transition hover:bg-spu-blue/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-spu-blue"
                                        x-show="searchQuery.trim().length >= 2"
                                        x-text="allSearchResultsLabel()"
                                        style="display: none;"></button>
                            </form>

                            <div class="mt-2 grid" x-show="searchResults.length" style="display: none;">
                                <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-[0.08em] text-spu-blue/45">{{ __('public.search_quick_links') }}</p>
                                <template x-for="item in searchResults" :key="searchResultKey(item)">
                                    <a :href="item.url"
                                        @click="closeSearchResult()"
                                       class="rounded-[8px] px-3 py-2 text-sm font-semibold text-spu-blue transition hover:bg-spu-blue/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-spu-blue"
                                       x-text="item.label"></a>
                                </template>
                            </div>
                             <p class="mt-2 px-1 text-xs font-semibold text-spu-blue/45" x-show="needsLongerSearchQuery()">
                                 {{ __('public.search_hint') }}
                            </p>
                        </div>
                    </div>

                    @foreach ($languageSwitch as $switchLink)
                        @if (!$switchLink->isCurrent)
                            <a href="{{ $switchLink->url }}" class="site-nav-lang" data-language-switch>
                                <img src="/images/ic_outline-language.svg" alt="{{ __('public.language') }}" class="h-[1rem] w-[1rem]" width="29" height="29" decoding="async">
                                <span>{{ $switchLink->label }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if ($navigation->applyCta)
                        <a href="{{ $navigation->applyCta->url }}"
                            class="site-nav-apply hidden items-center gap-2 rounded-[10px] bg-spu-red px-5 py-2.5 text-xs font-bold text-white shadow-[0_8px_24px_rgba(111,22,22,0.25)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(111,22,22,0.3)] nav:inline-flex"
                           @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" rel="noreferrer" @endif>
                            <span>{{ $navigation->applyCta->label }}</span>
                        </a>
                    @endif

                     <button type="button"
                            x-ref="mobileToggle"
                            @click="toggleMobile()"
                            aria-label="{{ __('public.toggle_navigation') }}"
                            aria-expanded="false"
                            :aria-expanded="mobileNav.toString()"
                            aria-controls="site-mobile-navigation"
                            class="site-nav-menu-btn nav:hidden">
                        <img src="/images/icon-bars-outline.svg" :src="mobileToggleIcon()" class="h-5 w-5" alt="" width="24" height="24" decoding="async">
                    </button>
                </div>
            </div>

            @include('public.layout.mobile-navigation')
        </div>
    </div>
</header>
