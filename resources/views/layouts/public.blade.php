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
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="absolute inset-x-0 top-0 -z-10 h-[28rem] bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.22),_transparent_48%),radial-gradient(circle_at_20%_20%,_rgba(14,165,233,0.18),_transparent_30%)]"></div>

            @if ($isPreview ?? false)
                <div class="border-b border-amber-400/30 bg-amber-400/10">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 text-sm text-amber-100 sm:px-6 lg:px-8">
                        <p>
                            Preview mode
                            @isset($preview)
                                <span class="text-amber-200/80">{{ strtoupper($preview->targetType) }}</span>
                            @endisset
                        </p>
                        @isset($preview)
                            @if ($preview->expiresAt)
                                <p class="text-amber-200/80">Expires {{ $preview->expiresAt }}</p>
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

            <header class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-white/10 bg-slate-900/75 p-5 shadow-2xl shadow-sky-950/30 backdrop-blur">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-4">
                        <a href="/{{ $locale }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight text-white">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-400/15 text-sky-200">SPU</span>
                            <span>{{ $seo->title }}</span>
                        </a>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-300">
                            @foreach ($navigation->utility->items as $item)
                                @if ($item->resolvedUrl)
                                    <a href="{{ $item->resolvedUrl }}" @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif class="rounded-full border border-white/10 px-3 py-2 transition hover:border-sky-300/50 hover:text-white">
                                        {{ $item->label }}
                                    </a>
                                @endif
                            @endforeach

                            @foreach ($languageSwitch as $switchLink)
                                <a href="{{ $switchLink->url }}" class="rounded-full px-3 py-2 {{ $switchLink->isCurrent ? 'bg-white text-slate-950' : 'border border-white/10 hover:border-slate-300 hover:text-white' }}">
                                    {{ $switchLink->label }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <nav aria-label="Primary navigation" class="flex flex-wrap items-center gap-2 text-sm text-slate-200">
                            @foreach ($navigation->header->items as $item)
                                @if ($item->resolvedUrl)
                                    <a href="{{ $item->resolvedUrl }}" class="rounded-full px-3 py-2 transition {{ $item->isActive ? 'bg-sky-400/20 text-white' : 'hover:bg-white/5 hover:text-white' }}">
                                        {{ $item->label }}
                                    </a>
                                @endif
                            @endforeach
                        </nav>

                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            @if ($navigation->studentPortalUrl)
                                <a href="{{ $navigation->studentPortalUrl }}" target="_blank" rel="noreferrer" class="text-slate-300 transition hover:text-white">Student Portal</a>
                            @endif

                            @if ($navigation->staffAccessUrl)
                                <a href="{{ $navigation->staffAccessUrl }}" target="_blank" rel="noreferrer" class="text-slate-300 transition hover:text-white">Staff Access</a>
                            @endif

                            @if ($navigation->applyCta)
                                <a href="{{ $navigation->applyCta->url }}" @if ($navigation->applyCta->target) target="{{ $navigation->applyCta->target }}" @endif class="rounded-full bg-sky-400 px-4 py-2 font-semibold text-slate-950 transition hover:bg-sky-300">
                                    {{ $navigation->applyCta->label }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
                @yield('content')
            </main>

            <footer class="border-t border-white/10 bg-slate-950/90">
                <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[1.4fr,1fr,1fr] lg:px-8">
                    <div>
                        @if ($navigation->footerSettings->logoUrl)
                            <img src="{{ $navigation->footerSettings->logoUrl }}" alt="{{ $navigation->footerSettings->brandTitle ?? $seo->title }}" class="h-12 w-auto rounded-xl object-contain">
                        @endif
                        <h2 class="{{ $navigation->footerSettings->logoUrl ? 'mt-4 ' : '' }}text-lg font-semibold text-white">{{ $navigation->footerSettings->brandTitle ?? $seo->title }}</h2>
                        @if ($navigation->footerSettings->brandSummary)
                            <p class="mt-3 text-sm leading-7 text-slate-300">{{ $navigation->footerSettings->brandSummary }}</p>
                        @endif
                        @if ($navigation->footerSettings->address)
                            <p class="mt-3 text-sm text-slate-300">{{ $navigation->footerSettings->address }}</p>
                        @endif
                        <div class="mt-4 space-y-2 text-sm text-slate-300">
                            @if ($navigation->footerSettings->phone)
                                <p>{{ $navigation->footerSettings->phone }}</p>
                            @endif
                            @if ($navigation->footerSettings->email)
                                <p>{{ $navigation->footerSettings->email }}</p>
                            @endif
                            @if ($navigation->footerSettings->mapEmbedUrl)
                                <a href="{{ $navigation->footerSettings->mapEmbedUrl }}" target="_blank" rel="noreferrer" class="inline-flex transition hover:text-white">Campus Map</a>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Navigation</h3>
                        <div class="mt-4 flex flex-col gap-2 text-sm text-slate-300">
                            @foreach ($navigation->footer->items as $item)
                                @if ($item->resolvedUrl)
                                    <a href="{{ $item->resolvedUrl }}" class="transition hover:text-white">{{ $item->label }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Connect</h3>
                        <div class="mt-4 space-y-2 text-sm text-slate-300">
                            @foreach ($navigation->socialContact->socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" rel="noreferrer" class="block transition hover:text-white">{{ $link->platform }}</a>
                            @endforeach
                            @foreach ($navigation->socialContact->contactLinks as $link)
                                <p>{{ $link->label }}: {{ $link->value }}</p>
                            @endforeach
                            @foreach ($navigation->footerSettings->legalLinks as $link)
                                <a href="{{ $link->url }}" @if ($link->target) target="{{ $link->target }}" @endif class="block transition hover:text-white">{{ $link->label }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="border-t border-white/10 px-4 py-4 text-center text-sm text-slate-400 sm:px-6 lg:px-8">
                    {{ $navigation->footerSettings->copyrightText }}
                </div>
            </footer>
        </div>
    </body>
</html>
