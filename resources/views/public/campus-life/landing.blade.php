@extends('layouts.public')

@section('content')
    @php($landing = $page->landing)

    <section class="page-hero relative min-h-[680px] overflow-hidden font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $landing['hero']['image'] ?? '/images/admissions-hero-campus.webp' }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-b from-spu-blue/70 via-spu-blue/50 to-spu-blue/80"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-spu-blue/30 to-transparent"></div>
        </div>

        <div class="container relative z-10 flex min-h-[680px] items-center justify-center text-center">
            <div class="mx-auto max-w-[860px]">
                <div class="mb-6 inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/8 px-5 py-2.5 backdrop-blur-md">
                    <span class="h-px w-8 bg-gradient-to-r from-transparent to-white/80"></span>
                    <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/85">{{ $locale === 'ar' ? 'الحياة الجامعية' : 'CAMPUS LIFE' }}</span>
                    <span class="h-px w-8 bg-gradient-to-l from-transparent to-white/80"></span>
                </div>

                <h1 class="text-5xl font-bold leading-[1.08] tracking-tight text-white md:text-6xl lg:text-[68px]">{{ $landing['hero']['title'] ?? '' }}</h1>
                <p class="mx-auto mt-6 max-w-[640px] text-lg font-bold leading-relaxed text-white/90">{{ $landing['hero']['summary'] ?? '' }}</p>

                <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    @foreach (($landing['hero']['quickLinks'] ?? []) as $link)
                        <a href="{{ $link['href'] ?? '#' }}" class="group inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-5 py-2.5 text-sm font-bold text-white backdrop-blur-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-white/30 hover:bg-white/15 hover:shadow-[0_8px_24px_rgba(0,0,0,0.15)]">
                            <span>{{ $link['label'] ?? '' }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-2.5 w-2.5 opacity-60 transition-transform duration-300 group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" aria-hidden="true">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-white to-transparent"></div>
    </section>

    <section class="relative z-10 -mt-20 font-hacen">
        <div class="container">
            <div class="mx-auto max-w-[960px] overflow-hidden rounded-2xl bg-spu-blue shadow-[0_24px_64px_rgba(32,39,89,0.25)]">
                <div class="relative p-10 text-center text-white md:p-12">
                    <div class="pointer-events-none absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 24px 24px;" aria-hidden="true"></div>
                    <h2 class="relative text-[28px] font-bold leading-tight tracking-tight md:text-[34px]">{{ $landing['intro']['title'] ?? '' }}</h2>
                    <p class="relative mx-auto mt-4 max-w-[680px] text-base leading-7 text-white/85">{{ $landing['intro']['summary'] ?? '' }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 font-hacen">
        <div class="container">
            <div class="cms-grid-stats gap-4 md:gap-6">
                @foreach (($landing['stats'] ?? []) as $stat)
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-[0_4px_20px_rgba(0,0,0,0.04)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(0,0,0,0.08)] md:p-8">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-spu-blue/5 text-spu-blue transition-colors duration-300 group-hover:bg-spu-blue group-hover:text-white">
                            <img src="{{ $stat['icon'] ?? '' }}" alt="" class="h-5 w-5 brightness-0 invert" aria-hidden="true">
                        </div>
                        <div class="flex items-baseline justify-center gap-0.5" dir="ltr">
                            <span class="text-4xl font-bold tracking-tight text-spu-blue md:text-5xl" translate="no">{{ $stat['value'] ?? '' }}</span>
                            <span class="text-2xl font-bold text-spu-red md:text-3xl" translate="no">{{ $stat['suffix'] ?? '' }}</span>
                        </div>
                        <p class="mt-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">{{ $stat['label'] ?? '' }}</p>
                        <div class="mx-auto mt-4 h-1 w-10 rounded-full bg-gradient-to-r from-spu-blue to-spu-red/40 transition-all duration-300 group-hover:w-16" aria-hidden="true"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="health" class="bg-section py-20 font-hacen lg:py-28">
        <span id="community-service" class="sr-only"></span>
        <div class="container">
            <div class="mx-auto max-w-[640px] text-center">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['features']['eyebrow'] ?? '' }}</p>
                <h2 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-spu-blue md:text-5xl">{{ $landing['features']['title'] ?? '' }}</h2>
                <p class="mt-5 text-base leading-relaxed text-slate-500">{{ $landing['features']['summary'] ?? '' }}</p>
            </div>
            <div class="cms-grid-cards mx-auto mt-16 max-w-[1200px] gap-6">
                @foreach (($landing['features']['items'] ?? []) as $feature)
                    <div class="group relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-8 shadow-[0_2px_12px_rgba(0,0,0,0.04)] transition-all duration-400 hover:-translate-y-2 hover:border-spu-blue/10 hover:shadow-[0_20px_50px_rgba(32,39,89,0.1)]">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-spu-blue via-spu-blue/60 to-spu-red opacity-0 transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div>
                        <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-spu-blue/8 bg-gradient-to-b from-spu-blue to-[#2a346d] shadow-[0_8px_20px_rgba(32,39,89,0.15)]"><img src="{{ $feature['icon'] ?? '' }}" alt="" class="h-8 w-8 brightness-0 invert" aria-hidden="true"></div>
                        <h3 class="text-xl font-bold leading-tight text-spu-blue">{{ $feature['title'] ?? '' }}</h3>
                        <p class="mt-3 text-[15px] leading-relaxed text-slate-500">{{ $feature['summary'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="services" x-data="campusLifeReveal()" x-init="init()" class="pb-8 pt-24 font-hacen overflow-x-hidden">
        <span id="activities" class="invisible absolute -mt-24"></span>
        <span id="career" class="invisible absolute -mt-24"></span>
        <div class="container mb-8">
            <div class="mx-auto max-w-[640px] text-center">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['servicesHeading']['eyebrow'] ?? '' }}</p>
                <h2 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-spu-blue md:text-5xl">{{ $landing['servicesHeading']['title'] ?? '' }}</h2>
            </div>
        </div>
        <div class="relative">
            <div class="absolute bottom-0 left-1/2 top-0 hidden w-px -translate-x-1/2 bg-gradient-to-b from-spu-blue/20 via-slate-200/60 to-transparent lg:block" aria-hidden="true"></div>
            @foreach (($landing['services'] ?? []) as $service)
                <div class="relative {{ ! $loop->odd ? 'bg-section' : '' }}">
                    <div class="absolute left-1/2 top-1/2 z-10 hidden h-[18px] w-[18px] -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white shadow-md {{ $loop->first ? 'bg-spu-blue' : 'bg-slate-300' }}" aria-hidden="true"></div>
                    <div class="container py-16 lg:py-24">
                        <div class="flex flex-col items-center gap-10 lg:gap-20 {{ ($service['imagePosition'] ?? '') === 'right' ? 'lg:flex-row' : 'lg:flex-row-reverse' }}">
                            <div class="min-w-0 flex-1" data-campus-reveal="{{ ($service['imagePosition'] ?? '') === 'right' ? 'left' : 'right' }}">
                                <div class="max-w-[530px] {{ ($service['imagePosition'] ?? '') === 'left' ? 'lg:ms-auto' : '' }}">
                                    <p class="text-7xl font-bold tracking-tight text-spu-blue/[0.06]">{{ $service['number'] ?? '' }}</p>
                                    <h3 class="-mt-2 text-[32px] font-bold leading-tight tracking-tight text-spu-blue">{{ $service['title'] ?? '' }}</h3>
                                    <p class="mt-4 max-w-[500px] text-lg leading-relaxed text-slate-500 {{ ($service['imagePosition'] ?? '') === 'left' ? 'lg:ms-auto' : '' }}">{{ $service['summary'] ?? '' }}</p>
                                    <a href="{{ $service['href'] ?? '#' }}" class="group mt-8 inline-flex items-center gap-2.5 rounded-full border border-spu-blue/10 bg-spu-blue/[0.03] px-6 py-3 text-base font-bold text-spu-red transition-all duration-300 hover:border-spu-red/20 hover:bg-spu-red/[0.04] hover:shadow-[0_4px_16px_rgba(111,22,22,0.08)]"><span>{{ $service['link'] ?? '' }}</span><img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" aria-hidden="true"></a>
                                </div>
                            </div>
                            <div class="w-full shrink-0 lg:w-[500px]" data-campus-reveal="{{ ($service['imagePosition'] ?? '') === 'right' ? 'right' : 'left' }}"><div class="relative overflow-hidden rounded-3xl shadow-[0_12px_40px_rgba(0,0,0,0.1)] transition-all duration-700 hover:shadow-[0_20px_60px_rgba(0,0,0,0.14)]"><img src="{{ $service['image'] ?? '' }}" alt="{{ $service['title'] ?? '' }}" class="h-[360px] w-full object-cover transition-transform duration-700 hover:scale-[1.03]"><div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-spu-blue/10 to-transparent"></div></div></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section x-data="campusLifeGallery()" class="bg-section py-20 font-hacen lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-[640px] text-center"><p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['gallery']['eyebrow'] ?? '' }}</p><h2 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-spu-blue md:text-5xl">{{ $landing['gallery']['title'] ?? '' }}</h2><p class="mt-5 text-base leading-relaxed text-slate-500">{{ $landing['gallery']['summary'] ?? '' }}</p></div>
            <div class="cms-grid-cards mx-auto mt-14 max-w-[1200px] gap-4">
                @foreach (($landing['gallery']['images'] ?? []) as $image)
                    <button type="button" data-src="{{ $image['src'] ?? '' }}" data-alt="{{ $image['alt'] ?? '' }}" x-on:click="open($event)" class="group relative cursor-pointer overflow-hidden rounded-2xl text-start shadow-[0_4px_16px_rgba(0,0,0,0.06)] transition-all duration-500 hover:-translate-y-1 hover:shadow-[0_16px_48px_rgba(0,0,0,0.12)] {{ $loop->first ? 'sm:col-span-2 sm:row-span-2' : '' }}">
                        <img src="{{ $image['src'] ?? '' }}" alt="{{ $image['alt'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105 {{ $loop->first ? 'min-h-[300px] sm:min-h-[480px]' : 'h-[240px]' }}">
                        <span class="absolute inset-0 flex items-end bg-gradient-to-t from-spu-blue/60 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"><span class="p-6"><span class="text-sm font-bold text-white">{{ $image['alt'] ?? '' }}</span></span></span>
                    </button>
                @endforeach
            </div>
        </div>
        <div x-show="isOpen()" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-on:click="close()" x-on:keydown.escape.window="close()" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm" style="display: none;" role="dialog" aria-modal="true">
            <button type="button" x-on:click="close()" class="absolute right-6 top-6 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Close"><img src="/images/icon-close-outline.svg" alt="" class="h-5 w-5" aria-hidden="true"></button>
            <img x-bind:src="activeSrc" x-bind:alt="activeAlt" class="max-h-[85vh] max-w-full rounded-2xl object-contain shadow-2xl" x-on:click.stop>
        </div>
    </section>

    <section class="py-20 font-hacen lg:py-28">
        <div class="container"><div class="mx-auto max-w-[640px] text-center"><p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['portalsHeading']['eyebrow'] ?? '' }}</p><h2 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-spu-blue md:text-5xl">{{ $landing['portalsHeading']['title'] ?? '' }}</h2>@if (! empty($landing['portalGuidance']))<p class="mt-5 text-base leading-7 text-slate-500">{{ $landing['portalGuidance'] }}</p>@endif</div><div class="cms-grid-cards mx-auto mt-14 max-w-[1000px] gap-6">
            @foreach (($landing['portals'] ?? []) as $portal)
                <a href="{{ $portal['url'] ?? '#' }}" class="group relative flex flex-col items-center overflow-hidden rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-[0_4px_20px_rgba(0,0,0,0.04)] transition-all duration-400 hover:-translate-y-2 hover:border-spu-blue/10 hover:shadow-[0_20px_50px_rgba(32,39,89,0.1)]"><div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-spu-blue to-spu-red opacity-0 transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div><div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-spu-blue/5 text-spu-blue transition-all duration-300 group-hover:bg-spu-blue group-hover:text-white group-hover:shadow-[0_8px_24px_rgba(32,39,89,0.2)]"><img src="{{ $portal['icon'] ?? '' }}" alt="" class="h-6 w-6 brightness-0 invert" aria-hidden="true"></div><h3 class="text-lg font-bold text-spu-blue">{{ $portal['title'] ?? '' }}</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $portal['summary'] ?? '' }}</p><div class="mt-5 flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-spu-blue transition-all duration-300 group-hover:bg-spu-red group-hover:text-white"><img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true"></div></a>
            @endforeach
        </div></div>
    </section>

    <section class="py-20 font-hacen lg:py-28"><div class="container"><div class="relative overflow-hidden rounded-3xl bg-spu-blue px-8 py-16 text-center text-white shadow-[0_24px_64px_rgba(32,39,89,0.3)] md:px-16 md:py-20"><div class="pointer-events-none absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 28px 28px;" aria-hidden="true"></div><div class="pointer-events-none absolute inset-0" style="background: radial-gradient(circle at 20% 50%, rgba(111,22,22,0.15) 0%, transparent 50%), radial-gradient(circle at 80% 50%, rgba(43,125,179,0.1) 0%, transparent 50%);" aria-hidden="true"></div><div class="relative z-10"><h2 class="mx-auto max-w-[600px] text-4xl font-bold leading-tight tracking-tight md:text-5xl">{{ $landing['cta']['title'] ?? '' }}</h2><p class="mx-auto mt-5 max-w-[520px] text-lg leading-relaxed text-white/80">{{ $landing['cta']['summary'] ?? '' }}</p><div class="mt-10 flex flex-wrap items-center justify-center gap-4"><a href="{{ $landing['cta']['primaryUrl'] ?? '#' }}" class="inline-flex items-center gap-2.5 rounded-full bg-spu-red px-8 py-4 text-sm font-bold uppercase tracking-[0.08em] text-white shadow-[0_12px_32px_rgba(111,22,22,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_rgba(111,22,22,0.35)]"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-3 w-3 brightness-0 invert" aria-hidden="true"><span>{{ $landing['cta']['primaryLabel'] ?? '' }}</span></a><a href="{{ $landing['cta']['secondaryUrl'] ?? '#' }}" class="inline-flex items-center gap-2.5 rounded-full border border-white/20 bg-white/8 px-8 py-4 text-sm font-bold uppercase tracking-[0.08em] text-white backdrop-blur-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-white/30 hover:bg-white/15"><img src="/images/icon-envelope-outline.svg" alt="" class="h-3 w-3 brightness-0 invert" aria-hidden="true"><span>{{ $landing['cta']['secondaryLabel'] ?? '' }}</span></a></div></div></div></div></section>
@endsection
