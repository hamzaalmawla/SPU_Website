@extends('layouts.public')

@section('content')
    @php
        $content = $page->content;
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $offers = is_array($content['offers'] ?? null) ? $content['offers'] : [];
        $offerItems = array_values(array_filter(is_array($offers['items'] ?? null) ? $offers['items'] : [], 'is_array'));
        $isAr = $locale === 'ar';
    @endphp

    <section class="relative flex min-h-[420px] items-end overflow-hidden pt-28 font-hacen" dir="{{ $direction }}">
        <div class="absolute inset-0">
            <img src="{{ $page->heroImage }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/65 to-spu-blue/15"></div>
        </div>
        <div class="container relative z-10 pb-16 text-center text-white">
            <nav class="mb-3 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/72" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ __('centers.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span class="text-white" aria-current="page">{{ $hero['title'] ?? __('centers.centers') }}</span>
            </nav>
            <h1 class="text-[34px] font-bold leading-tight md:text-[48px]">{{ $hero['title'] ?? __('centers.centers') }}</h1>
            @if (! empty($hero['summary']))
                <x-public.rich-text class="mx-auto mt-4 max-w-[760px] text-sm font-semibold leading-7 text-white/85 md:text-base" :text="$hero['summary']" />
            @endif
        </div>
    </section>

    <section id="centers-list" class="bg-white py-16 font-hacen lg:py-24" dir="{{ $direction }}">
        <div class="container">
            <h2 class="mb-10 text-center text-[28px] font-bold text-spu-blue md:text-[36px]">{{ $content['centersTitle'] ?? __('centers.centers') }}</h2>

            @if ($page->cards !== [])
                <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($page->cards as $card)
                        <a href="{{ $card['url'] }}" class="group flex flex-col overflow-hidden rounded-xl bg-white text-start shadow-[0_15px_40px_rgba(0,0,0,0.06)] ring-1 ring-slate-100 transition hover:-translate-y-1 hover:shadow-[0_20px_44px_rgba(30,38,82,0.14)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                            <div class="aspect-[16/9] overflow-hidden bg-slate-100">
                                <img src="{{ $card['image'] }}" alt="" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy" aria-hidden="true">
                            </div>
                            <div class="flex flex-1 flex-col p-6">
                                <h3 class="card-title text-[1.2rem] font-bold leading-snug text-spu-blue" dir="auto">{{ $card['title'] }}</h3>
                                @if ($card['summary'] !== '')
                                    <p class="mt-3 text-[14px] leading-relaxed text-[#4b5563]">{{ $card['summary'] }}</p>
                                @endif
                                <span class="mt-auto inline-flex items-center gap-2 pt-6 text-[12px] font-bold uppercase tracking-[0.12em] text-spu-red">
                                    {{ __('centers.explore') }}
                                    <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 transition-transform group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" aria-hidden="true">
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-center font-bold text-slate-600">{{ __('centers.no_centers') }}</p>
            @endif
        </div>
    </section>

    @if ($offerItems !== [])
        <section id="centers-offer" class="bg-[#fcfdfd] py-20 font-hacen lg:py-28" dir="{{ $direction }}">
            <div class="container">
                <div class="mb-14 text-center">
                    <h2 class="mx-auto max-w-[700px] text-[1.8rem] font-bold leading-[1.2] tracking-tight text-[#1e2652] lg:text-[2.4rem]">{{ $offers['title'] ?? '' }}</h2>
                    @if (! empty($offers['summary']))
                        <x-public.rich-text class="mx-auto mt-4 max-w-[680px] text-[15px] leading-7 text-[#4b5563]" :text="$offers['summary']" />
                    @endif
                </div>
                <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach ($offerItems as $item)
                        <article class="flex flex-col items-start rounded-xl p-8 text-start lg:p-10 {{ $loop->first ? 'bg-[#1e2652] text-white shadow-[0_20px_40px_rgba(30,38,82,0.2)]' : 'bg-white text-[#1e2652] shadow-[0_15px_40px_rgba(0,0,0,0.06)]' }}">
                            <h3 class="card-title w-full text-[1.2rem] font-bold leading-snug lg:text-[1.3rem] {{ $loop->first ? 'text-white' : 'text-[#1e2652]' }}" dir="auto">{{ $item['title'] ?? '' }}</h3>
                            @if (! empty($item['summary']))
                                <p class="mt-4 text-[14px] leading-relaxed {{ $loop->first ? 'text-white/80' : 'text-[#4b5563]' }}">{{ $item['summary'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
