@extends('layouts.public')

@section('content')
    @php($content = $page->page)

    <section class="relative min-h-[360px] overflow-hidden font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $content['hero']['image'] ?? '/images/slider-4.webp' }}" alt="{{ $content['hero']['imageAlt'] ?? '' }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/90 via-spu-blue/60 to-spu-blue/0"></div>
            <div class="absolute inset-0 bg-spu-blue/25"></div>
        </div>
        <div class="container relative z-10 flex min-h-[360px] items-center justify-center pb-12 pt-32 text-center">
            <div class="w-full">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/80">{{ $content['hero']['eyebrow'] ?? '' }}</p>
                <h1 class="mt-3 text-[32px] font-bold leading-tight text-white md:text-[42px]">{{ $content['hero']['title'] ?? '' }}</h1>
                <p class="mx-auto mt-4 w-full text-sm font-semibold leading-6 text-white/85 md:w-[58%]">{{ $content['hero']['summary'] ?? '' }}</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ $content['hero']['primaryUrl'] ?? '#tour' }}" class="group inline-flex items-center justify-center gap-2 rounded-[4px] bg-spu-red px-6 py-3 text-xs font-bold text-white shadow-[0_12px_30px_rgba(111,22,22,0.28)] transition hover:-translate-y-0.5 hover:bg-white hover:text-spu-blue"><img src="{{ $content['hero']['primaryIcon'] ?? '' }}" alt="" class="h-4 w-4 brightness-0 invert transition group-hover:brightness-100 group-hover:invert-0" aria-hidden="true"><span>{{ $content['hero']['primaryLabel'] ?? '' }}</span></a>
                    <a href="{{ $content['hero']['secondaryUrl'] ?? '#facilities' }}" class="inline-flex items-center justify-center gap-2 rounded-[4px] border border-white/20 bg-white/10 px-6 py-3 text-xs font-bold text-white backdrop-blur-sm transition hover:-translate-y-0.5 hover:bg-white/18"><img src="{{ $content['hero']['secondaryIcon'] ?? '' }}" alt="" class="h-4 w-4 brightness-0 invert" aria-hidden="true"><span>{{ $content['hero']['secondaryLabel'] ?? '' }}</span></a>
                </div>
            </div>
        </div>
    </section>

    <section id="tour" x-data="virtualTour" data-autoplay-interval="{{ (int) ($content['tour']['autoplayInterval'] ?? 6000) }}" data-play-label="{{ $content['tour']['playLabel'] ?? '' }}" data-pause-label="{{ $content['tour']['pauseLabel'] ?? '' }}" data-fullscreen-label="{{ $content['tour']['fullscreenLabel'] ?? '' }}" data-exit-fullscreen-label="{{ $content['tour']['exitFullscreenLabel'] ?? '' }}" class="bg-white py-14 font-hacen">
        <div class="container">
            <div class="text-center"><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-spu-blue/55">{{ $content['tour']['eyebrow'] ?? '' }}</p><h2 class="mt-2 text-[24px] font-bold leading-tight text-spu-blue md:text-[30px]">{{ $content['tour']['title'] ?? '' }}</h2><p class="mx-auto mt-3 w-full text-sm leading-6 text-spu-blue/60 md:w-[48%]">{{ $content['tour']['summary'] ?? '' }}</p></div>
            <script type="application/json" x-ref="sceneData">{!! json_encode($content['tour']['scenes'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
            <div x-ref="viewer" class="relative mt-8 overflow-hidden rounded-[8px] border border-spu-blue/10 bg-spu-blue shadow-[0_18px_60px_rgba(32,39,89,0.16)]" x-bind:class="fullscreenClass">
                <div tabindex="0" role="application" aria-roledescription="{{ $content['tour']['experienceLabel'] ?? '' }}" x-bind:aria-label="activeTitle" class="relative h-[360px] touch-none cursor-grab overflow-hidden outline-none focus-visible:ring-4 focus-visible:ring-spu-red md:h-[560px]" x-on:pointerdown="startPan" x-on:pointermove="movePan" x-on:pointerup="endPan" x-on:pointercancel="endPan" x-on:keydown="handleKey">
                    <img x-bind:src="activeImage" x-bind:alt="activeImageAlt" x-bind:style="imageTransform" draggable="false" class="h-full w-full select-none object-cover will-change-transform">
                    <template x-for="hotspot in activeHotspots" x-bind:key="hotspot.id">
                        <button type="button" x-bind:data-target-scene="hotspot.targetSceneId" x-on:click="activateHotspot" x-bind:style="hotspot.style" x-bind:aria-label="hotspot.label" x-bind:title="hotspot.description" class="group absolute -translate-x-1/2 -translate-y-1/2"><span class="grid h-8 w-8 place-items-center rounded-full bg-white shadow-lg ring-4 ring-white/30"><span class="h-2.5 w-2.5 rounded-full bg-spu-red"></span></span><span class="pointer-events-none absolute left-1/2 top-10 hidden max-w-56 -translate-x-1/2 rounded bg-spu-blue px-3 py-2 text-xs font-bold text-white group-hover:block group-focus:block" x-text="hotspot.label"></span></button>
                    </template>
                    <div class="absolute bottom-4 left-4 max-w-[70%] rounded bg-spu-blue/90 px-4 py-3 text-white rtl:left-auto rtl:right-4"><p class="text-sm font-bold" x-text="activeTitle"></p><p class="mt-1 text-xs text-white/75" x-text="activeSummary"></p></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 bg-spu-blue p-3 text-white" aria-label="{{ $content['tour']['controlLabel'] ?? '' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" x-on:click="previous" class="rounded border border-white/25 px-3 py-2 text-xs font-bold" aria-label="{{ $content['tour']['previousLabel'] ?? '' }}">&larr;</button>
                        <button type="button" x-on:click="next" class="rounded border border-white/25 px-3 py-2 text-xs font-bold" aria-label="{{ $content['tour']['nextLabel'] ?? '' }}">&rarr;</button>
                        <button type="button" x-on:click="toggleAutoplay" class="rounded border border-white/25 px-3 py-2 text-xs font-bold" x-bind:aria-label="autoplayLabel" x-text="autoplayLabel"></button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" x-on:click="zoomOut" class="grid h-9 w-9 place-items-center rounded border border-white/25 font-bold" aria-label="{{ $content['tour']['zoomOutLabel'] ?? '' }}">-</button>
                        <output class="min-w-12 text-center text-xs font-bold" x-text="zoomLabel"></output>
                        <button type="button" x-on:click="zoomIn" class="grid h-9 w-9 place-items-center rounded border border-white/25 font-bold" aria-label="{{ $content['tour']['zoomInLabel'] ?? '' }}">+</button>
                        <button type="button" x-on:click="resetView" class="rounded border border-white/25 px-3 py-2 text-xs font-bold">{{ $content['tour']['resetLabel'] ?? '' }}</button>
                        <button type="button" x-on:click="toggleFullscreen" class="rounded border border-white/25 px-3 py-2 text-xs font-bold" x-bind:aria-label="fullscreenLabel" x-text="fullscreenLabel"></button>
                    </div>
                </div>
            </div>
            <p class="sr-only" aria-live="polite" x-text="announcement"></p>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2" role="group" aria-label="{{ $content['tour']['title'] ?? '' }}">
                @foreach (($content['tour']['scenes'] ?? []) as $index => $scene)
                    <button type="button" data-scene-index="{{ $index }}" x-on:click="selectScene" class="w-36 shrink-0 rounded border border-slate-200 bg-white p-2 text-start focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-red"><img src="{{ $scene['image'] ?? '' }}" alt="" class="h-20 w-full rounded object-cover" aria-hidden="true"><span class="mt-2 block text-xs font-bold text-spu-blue">{{ $scene['title'] ?? '' }}</span></button>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-12 font-hacen"><div class="container"><div class="text-center"><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-spu-blue/55">{{ $content['highlights']['eyebrow'] ?? '' }}</p><h2 class="mt-2 text-[24px] font-bold leading-tight text-spu-blue md:text-[30px]">{{ $content['highlights']['title'] ?? '' }}</h2><p class="mx-auto mt-3 w-full text-sm leading-6 text-spu-blue/60 md:w-[56%]">{{ $content['highlights']['summary'] ?? '' }}</p></div><div class="mt-8 grid gap-4 lg:grid-cols-2">@foreach (($content['highlights']['items'] ?? []) as $item)<a href="{{ $item['href'] ?? '#' }}" class="group relative min-h-[210px] overflow-hidden rounded-[8px] bg-spu-blue text-white shadow-[0_16px_42px_rgba(32,39,89,0.14)] {{ ($item['featured'] ?? false) ? 'lg:row-span-2 lg:min-h-[440px]' : '' }}"><img src="{{ $item['image'] ?? '' }}" alt="{{ $item['imageAlt'] ?? '' }}" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"><div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/45 to-transparent"></div><div class="absolute inset-x-0 bottom-0 p-5 md:p-7"><h3 class="text-[22px] font-bold leading-tight {{ ($item['featured'] ?? false) ? 'md:text-[28px]' : 'md:text-[24px]' }}">{{ $item['title'] ?? '' }}</h3><p class="mt-2 text-sm leading-6 text-white/82">{{ $item['summary'] ?? '' }}</p><span class="mt-4 inline-flex items-center gap-2 rounded-[4px] bg-spu-red px-4 py-2 text-[11px] font-bold transition group-hover:bg-white group-hover:text-spu-blue"><span>{{ $item['label'] ?? '' }}</span><img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 brightness-0 invert rtl:rotate-180" aria-hidden="true"></span></div></a>@endforeach</div></div></section>

    <section id="facilities" class="bg-[#f4f8fd] py-14 font-hacen"><div class="container"><div class="text-center"><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-spu-blue/55">{{ $content['facilities']['eyebrow'] ?? '' }}</p><h2 class="mt-2 text-[24px] font-bold leading-tight text-spu-blue md:text-[30px]">{{ $content['facilities']['title'] ?? '' }}</h2><p class="mx-auto mt-3 w-full text-sm leading-6 text-spu-blue/60 md:w-[56%]">{{ $content['facilities']['summary'] ?? '' }}</p></div><div class="mt-8 grid gap-5 md:grid-cols-3">@foreach (($content['facilities']['items'] ?? []) as $item)<a href="{{ $item['href'] ?? '#' }}" class="group overflow-hidden rounded-[8px] border border-spu-blue/10 bg-white shadow-[0_12px_30px_rgba(32,39,89,0.08)] transition hover:-translate-y-1 hover:shadow-[0_18px_42px_rgba(32,39,89,0.14)]"><div class="relative h-[150px] overflow-hidden"><img src="{{ $item['image'] ?? '' }}" alt="{{ $item['title'] ?? '' }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105"><div class="absolute inset-0 bg-spu-blue/10"></div></div><div class="p-5 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-[8px] bg-spu-blue/5"><img src="{{ $item['icon'] ?? '' }}" alt="" class="h-6 w-6" aria-hidden="true"></div><h3 class="mt-4 text-[18px] font-bold text-spu-blue">{{ $item['title'] ?? '' }}</h3><p class="mt-2 text-sm leading-6 text-spu-blue/58">{{ $item['summary'] ?? '' }}</p><span class="mt-4 inline-flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-[0.08em] text-spu-red"><span>{{ $content['facilities']['detailsLabel'] ?? '' }}</span><img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true"></span></div></a>@endforeach</div></div></section>
@endsection
