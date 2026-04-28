<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo->title }}</title>
        <meta name="robots" content="{{ $seo->robots ?? 'index,follow' }}">
        @if ($seo->metaDescription)
            <meta name="description" content="{{ $seo->metaDescription }}">
        @endif
        <meta property="og:locale" content="{{ $locale }}">
        <meta property="og:title" content="{{ $seo->ogTitle ?? $seo->title }}">
        @if ($seo->ogDescription)
            <meta property="og:description" content="{{ $seo->ogDescription }}">
        @endif
        @if ($seo->ogImage)
            <meta property="og:image" content="{{ $seo->ogImage }}">
        @endif
        @if ($seo->canonicalUrl)
            <link rel="canonical" href="{{ $seo->canonicalUrl }}">
        @endif
        @foreach ($seo->hreflang as $hreflang)
            <link rel="alternate" hreflang="{{ $hreflang['locale'] }}" href="{{ $hreflang['url'] }}">
        @endforeach
        <link rel="preconnect" href="https://fonts.bunny.net">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen antialiased font-hacen">

        @if ($isPreview ?? false)
            <div class="border-b border-amber-400/30 bg-amber-400/10">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 text-sm text-amber-100 sm:px-6 lg:px-8">
                    <p>
                        {{ __('public.preview_mode') }}
                        @isset($preview)
                            <span class="text-amber-200/80">{{ strtoupper($preview->targetType) }}</span>
                        @endisset
                    </p>
                    @isset($preview)
                        @if ($preview->expiresAt)
                            <p class="text-amber-200/80">{{ __('public.expires', ['time' => $preview->expiresAt]) }}</p>
                        @endif
                    @endisset
                </div>
            </div>
        @endif

        @if ($navigation->emergencyNotice->isEnabled)
            <div class="border-b border-red-400/30 bg-red-500/10">
                <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                    <p class="text-sm font-medium text-red-100">{{ $navigation->emergencyNotice->title }}</p>
                    @if ($navigation->emergencyNotice->message)
                        <p class="mt-1 text-sm text-red-100/80">{{ $navigation->emergencyNotice->message }}</p>
                    @endif
                </div>
            </div>
        @endif

        <header id="site-header" class="absolute top-0 z-50 w-full top-3" x-data="mobileNav()"
            @keydown.escape.window="closeAll()"
            @click.outside="openMenu = null; if (window.innerWidth < 1536) { mobileNav = false; }"
            :class="stickyNav ? 'fixed inset-x-0 top-0 z-50 w-full font-hacen' : ''">

            <div class="container">
                <div class="site-nav-shell" :class="stickyNav ? 'site-nav-shell--sticky' : ''">

                    {{-- Main bar: logo + desktop nav + actions --}}
                    <div class="site-nav-shell__main">
                        <a href="/{{ $locale }}" aria-label="{{ __('public.home') }}" class="site-nav-brand">
                            <img src="/images/logo-spu.png" alt="{{ __('public.spu_logo_alt') }}" class="h-auto w-[9.25rem] sm:w-[11rem] xl:w-[13.5rem]">
                        </a>

                        <nav class="hidden flex-1 justify-center 2xl:flex" aria-label="{{ __('public.primary_navigation') }}">
                            <ul class="site-nav-list">
                                @foreach ($navigation->header->items as $item)
                                    <li class="site-nav-item"
                                        @if (!empty($item->children))
                                            @mouseenter="openMenu = '{{ $loop->index }}'"
                                            @mouseleave="openMenu = null"
                                        @endif>
                                        <a href="{{ $item->resolvedUrl ?? '#' }}"
                                           class="site-nav-link font-hacen {{ $item->isActive ? 'site-nav-link--active' : '' }}"
                                           @if ($item->isActive) aria-current="page" @endif
                                           @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif>
                                            <span>{{ $item->label }}</span>
                                            @if (!empty($item->children))
                                                <svg class="site-nav-link__chevron"
                                                     :class="openMenu === '{{ $loop->index }}' ? 'rotate-180' : ''"
                                                     fill="none" stroke="currentColor" viewBox="0 0 20 20">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m5 7.5 5 5 5-5"></path>
                                                </svg>
                                            @endif
                                        </a>

                                        @if (!empty($item->children))
                                            <div x-show="openMenu === '{{ $loop->index }}'"
                                                 x-transition:enter="transition duration-200 ease-out"
                                                 x-transition:enter-start="opacity-0 -translate-y-3 scale-95"
                                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave="transition duration-150 ease-in"
                                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                 x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                                                 style="display: none;"
                                                 class="site-nav-dropdown">
                                                @foreach ($item->children as $child)
                                                    <a href="{{ $child->resolvedUrl ?? '#' }}"
                                                       class="site-nav-dropdown-link"
                                                       @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                                        {{ $child->label }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </nav>

                        <div class="site-nav-actions">
                            @foreach ($languageSwitch as $switchLink)
                                @if (!$switchLink->isCurrent)
                                    <button type="button" onclick="window.location='{{ $switchLink->url }}'" class="site-nav-lang">
                                        <img src="/images/ic_outline-language.svg" alt="{{ __('public.language') }}" class="h-[1.05rem] w-[1.05rem]">
                                        <span>{{ $switchLink->label }}</span>
                                    </button>
                                @endif
                            @endforeach

                            @if ($navigation->applyCta)
                                <a href="{{ $navigation->applyCta->url }}"
                                   @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" @endif
                                   @if ($navigation->applyCta->target === '_blank') rel="noreferrer" @endif
                                   class="site-nav-cta">
                                    <span class="site-nav-cta__dot" aria-hidden="true"></span>
                                    {{ $navigation->applyCta->label }}
                                </a>
                            @endif

                            <button type="button"
                                    @click="toggleMobile()"
                                    aria-label="{{ __('public.toggle_navigation') }}"
                                    class="site-nav-menu-btn 2xl:hidden">
                                <img :src="mobileNav ? '/images/icon-close-outline.svg' : '/images/icon-bars-outline.svg'"
                                     class="h-5 w-5" alt="">
                            </button>
                        </div>
                    </div>

                    {{-- Mobile navigation panel --}}
                    <div x-show="mobileNav"
                         x-transition:enter="transition duration-250 ease-out"
                         x-transition:enter-start="opacity-0 -translate-y-3"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition duration-180 ease-in"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         style="display: none;"
                         class="site-nav-mobile-panel 2xl:hidden">

                        {{-- Mobile actions: utility links, language, portal links --}}
                        <div class="site-nav-mobile-actions">
                            <div class="site-nav-mobile-utility-row">
                                @foreach ($navigation->utility->items as $item)
                                    @if ($item->resolvedUrl)
                                        <a href="{{ $item->resolvedUrl }}"
                                           @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif
                                           class="site-nav-utility"
                                           @click="closeAll()">
                                            {{ $item->label }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>

                            <div class="site-nav-mobile-utility-row">
                                @if ($navigation->studentPortalUrl)
                                    <a href="{{ $navigation->studentPortalUrl }}" target="_blank" rel="noreferrer" class="site-nav-utility" @click="closeAll()">
                                        {{ __('public.student_portal') }}
                                    </a>
                                @endif

                                @if ($navigation->staffAccessUrl)
                                    <a href="{{ $navigation->staffAccessUrl }}" target="_blank" rel="noreferrer" class="site-nav-utility" @click="closeAll()">
                                        {{ __('public.staff_access') }}
                                    </a>
                                @endif

                                @foreach ($languageSwitch as $switchLink)
                                    @if (!$switchLink->isCurrent)
                                        <a href="{{ $switchLink->url }}" class="site-nav-lang" @click="closeAll()">
                                            <img src="/images/ic_outline-language.svg" alt="{{ __('public.language') }}" class="h-[1.05rem] w-[1.05rem]">
                                            <span>{{ $switchLink->label }}</span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>

                            @if ($navigation->applyCta)
                                <a href="{{ $navigation->applyCta->url }}"
                                   @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" @endif
                                   @if ($navigation->applyCta->target === '_blank') rel="noreferrer" @endif
                                   class="site-nav-cta"
                                   @click="closeAll()">
                                    <span class="site-nav-cta__dot" aria-hidden="true"></span>
                                    {{ $navigation->applyCta->label }}
                                </a>
                            @endif
                        </div>

                        {{-- Mobile nav items --}}
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
                                                <img src="/images/icon-chevron-down-outline.svg"
                                                     class="h-2.5 w-2.5 transition-transform duration-200"
                                                     :class="openMenu === '{{ $loop->index }}' ? 'rotate-180' : ''"
                                                     alt="">
                                            </button>
                                        @endif
                                    </div>

                                    @if (!empty($item->children))
                                        <div x-show="openMenu === '{{ $loop->index }}'"
                                             x-transition:enter="transition duration-200 ease-out"
                                             x-transition:enter-start="opacity-0 -translate-y-2"
                                             x-transition:enter-end="opacity-100 translate-y-0"
                                             x-transition:leave="transition duration-150 ease-in"
                                             x-transition:leave-start="opacity-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 -translate-y-1"
                                             style="display: none;"
                                             class="site-nav-mobile-children">
                                            @foreach ($item->children as $child)
                                                <a href="{{ $child->resolvedUrl ?? '#' }}"
                                                   @click="closeAll()"
                                                   class="site-nav-mobile-child"
                                                   @if ($child->openInNewTab ?? false) target="_blank" rel="noreferrer" @endif>
                                                    {{ $child->label }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <main>@yield('content')</main>

        @php($homepageFooterPayload = ($homepageFooterSection ?? null)?->payload)
        <footer id="site-footer" class="overflow-hidden bg-spu-blue pt-16 pb-8 font-hacen text-white">
            @if ($homepageFooterPayload)
                <div class="container">
                    <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
                        {{-- Brand block --}}
                        <div class="flex flex-col items-start lg:col-span-4">
                            @if ($homepageFooterPayload->content['brandBlock']['title'] ?? null)
                                <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">
                                    {{ $homepageFooterPayload->content['brandBlock']['title'] }}
                                </h2>
                            @endif
                            @if ($homepageFooterPayload->content['brandBlock']['body'] ?? null)
                                <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                                    {{ $homepageFooterPayload->content['brandBlock']['body'] }}
                                </p>
                            @endif

                            @if ($homepageFooterPayload->socialLinks !== [])
                                <div class="flex items-center gap-6 text-[22px]">
                                    @foreach ($homepageFooterPayload->socialLinks as $link)
                                        <a href="{{ $link->url }}" target="_blank" rel="noreferrer"
                                           class="text-white/80 transition-all hover:scale-110 hover:text-spu-red">
                                            <i class="{{ $link->icon ?? '' }}"></i>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Footer columns --}}
                        @foreach ($homepageFooterPayload->footerColumns as $column)
                            <div class="lg:col-span-2">
                                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                                    {{ $column->title }}
                                </h3>
                                <ul class="flex flex-col gap-4">
                                    @foreach ($column->links as $link)
                                        <li>
                                            <a href="{{ $link->url }}"
                                               @if ($link->target) target="{{ $link->target }}" @endif
                                               @if ($link->target === '_blank') rel="noreferrer" @endif
                                               class="text-[16px] text-white/80 transition-colors hover:text-white">
                                                {{ $link->label }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach

                        {{-- Contact block --}}
                        <div class="lg:col-span-3">
                            @if ($homepageFooterPayload->content['contactBlock']['title'] ?? null)
                                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                                    {{ $homepageFooterPayload->content['contactBlock']['title'] }}
                                </h3>
                            @else
                                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                                    {{ __('public.contact_heading') }}
                                </h3>
                            @endif
                            <div class="flex flex-col gap-6">
                                @foreach ($homepageFooterPayload->contactLinks as $link)
                                    <div class="flex items-start gap-4">
                                        @if ($link->icon ?? null)
                                            <i class="{{ $link->icon }} mt-1.5 text-spu-red"></i>
                                        @endif
                                        <span class="text-[15px] leading-relaxed text-white/80 {{ ($link->ltr ?? false) ? 'ltr' : '' }}">
                                            {{ $link->label }}: {{ $link->value }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Map embed --}}
                        @if ($homepageFooterPayload->content['mapEmbed']['url'] ?? null)
                            <div class="flex flex-col items-start lg:col-span-3 lg:items-end">
                                @if ($homepageFooterPayload->content['mapEmbed']['label'] ?? null)
                                    <h3 class="mb-8 w-full text-[18px] font-bold uppercase tracking-widest text-white/50">
                                        {{ $homepageFooterPayload->content['mapEmbed']['label'] }}
                                    </h3>
                                @endif
                                <div class="group h-[180px] w-full overflow-hidden rounded-[12px] border border-white/10 shadow-2xl">
                                    <iframe src="{{ $homepageFooterPayload->content['mapEmbed']['url'] }}"
                                            class="h-full w-full grayscale-[0.3] opacity-80 transition-all duration-700 group-hover:grayscale-0 group-hover:opacity-100"
                                            style="border:0;" allowfullscreen="" loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            </div>
                        @endif
                    </div>

                    <hr class="mb-8 border-white/10">

                    <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                        <p class="text-[14px] text-white/50" translate="no">
                            {{ $homepageFooterPayload->content['copyrightText'] ?? $seo->title }}
                        </p>

                        @if (!empty($homepageFooterPayload->content['legalLinks'] ?? []))
                            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                                @foreach ($homepageFooterPayload->content['legalLinks'] as $link)
                                    @if (!empty($link['label']) && !empty($link['url']))
                                        <a href="{{ $link['url'] }}"
                                           class="text-white/50 transition-colors hover:text-white"
                                           @if (str_starts_with($link['url'] ?? '', 'http')) target="_blank" rel="noreferrer" @endif>
                                            {{ $link['label'] }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="container">
                    <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
                        {{-- Brand block (fallback) --}}
                        <div class="flex flex-col items-start lg:col-span-4">
                            @if ($navigation->footerSettings->logoUrl)
                                <img src="{{ $navigation->footerSettings->logoUrl }}"
                                     alt="{{ $navigation->footerSettings->brandTitle ?? $seo->title }}"
                                     class="mb-6 h-12 w-auto rounded-xl object-contain">
                            @endif
                            <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">
                                {{ $navigation->footerSettings->brandTitle ?? $seo->title }}
                            </h2>
                            @if ($navigation->footerSettings->brandSummary)
                                <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                                    {{ $navigation->footerSettings->brandSummary }}
                                </p>
                            @endif

                            @if ($navigation->socialContact->socialLinks !== [])
                                <div class="flex items-center gap-6 text-[22px]">
                                    @foreach ($navigation->socialContact->socialLinks as $link)
                                        <a href="{{ $link->url }}" target="_blank" rel="noreferrer"
                                           class="text-white/80 transition-all hover:scale-110 hover:text-spu-red">
                                            <i class="{{ $link->icon ?? '' }}"></i>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Navigation links (fallback) --}}
                        <div class="lg:col-span-2">
                            <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                                {{ __('public.navigation_heading') }}
                            </h3>
                            <ul class="flex flex-col gap-4">
                                @foreach ($navigation->footer->items as $item)
                                    @if ($item->resolvedUrl)
                                        <li>
                                            <a href="{{ $item->resolvedUrl }}"
                                               @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif
                                               class="text-[16px] text-white/80 transition-colors hover:text-white">
                                                {{ $item->label }}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>

                        {{-- Contact (fallback) --}}
                        <div class="lg:col-span-3">
                            <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                                {{ __('public.connect_heading') }}
                            </h3>
                            <div class="flex flex-col gap-6">
                                @if ($navigation->footerSettings->address)
                                    <div class="flex items-start gap-4">
                                        <span class="text-[15px] leading-relaxed text-white/80">{{ $navigation->footerSettings->address }}</span>
                                    </div>
                                @endif
                                @foreach ($navigation->socialContact->contactLinks as $link)
                                    <div class="flex items-start gap-4">
                                        @if ($link->icon ?? null)
                                            <i class="{{ $link->icon }} mt-1.5 text-spu-red"></i>
                                        @endif
                                        <span class="text-[15px] leading-relaxed text-white/80 {{ ($link->ltr ?? false) ? 'ltr' : '' }}">
                                            {{ $link->label }}: {{ $link->value }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Map embed (fallback) --}}
                        @if ($navigation->footerSettings->mapEmbedUrl)
                            <div class="flex flex-col items-start lg:col-span-3 lg:items-end">
                                <h3 class="mb-8 w-full text-[18px] font-bold uppercase tracking-widest text-white/50">
                                    {{ __('public.campus_map') }}
                                </h3>
                                <div class="group h-[180px] w-full overflow-hidden rounded-[12px] border border-white/10 shadow-2xl">
                                    <iframe src="{{ $navigation->footerSettings->mapEmbedUrl }}"
                                            class="h-full w-full grayscale-[0.3] opacity-80 transition-all duration-700 group-hover:grayscale-0 group-hover:opacity-100"
                                            style="border:0;" allowfullscreen="" loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            </div>
                        @endif
                    </div>

                    <hr class="mb-8 border-white/10">

                    <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                        <p class="text-[14px] text-white/50" translate="no">
                            {{ $navigation->footerSettings->copyrightText }}
                        </p>

                        @if ($navigation->footerSettings->legalLinks !== [])
                            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                                @foreach ($navigation->footerSettings->legalLinks as $link)
                                    <a href="{{ $link->url }}"
                                       @if ($link->target) target="{{ $link->target }}" @endif
                                       @if ($link->target === '_blank') rel="noreferrer" @endif
                                       class="text-white/50 transition-colors hover:text-white">
                                        {{ $link->label }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </footer>

    </body>
</html>
