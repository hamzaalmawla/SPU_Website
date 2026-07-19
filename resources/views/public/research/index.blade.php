@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)

    <section class="bg-white font-hacen" dir="{{ $direction }}">
        <div class="relative mx-auto h-[85vh] overflow-hidden">
            <img src="{{ $data['hero']['backgroundImage'] ?? '/images/uni-main-place.JPG' }}" alt="SPU Campus" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-spu-blue/50"></div>
            <div class="container relative z-10 flex min-h-[520px] items-center justify-center pt-14 md:min-h-[540px]">
                <div class="relative mx-auto w-full max-w-[670px] px-8 py-11 text-center md:px-16 md:py-10">
                    <span class="pointer-events-none absolute left-0 top-0 h-full w-[4px] bg-white"></span>
                    <span class="pointer-events-none absolute right-0 top-0 h-full w-[4px] bg-white"></span>
                    <span class="pointer-events-none absolute right-0 top-0 h-[4px] w-[265px] max-w-[42%] bg-white"></span>
                    <span class="pointer-events-none absolute bottom-0 left-0 h-[4px] w-[265px] max-w-[42%] bg-white"></span>
                    <h1 class="text-[34px] font-bold leading-tight text-white md:text-[40px]">{{ $data['hero']['title'] ?? '' }}</h1>
                    <p class="mt-5 text-[13px] font-bold leading-none text-white">{{ $data['hero']['eyebrow'] ?? '' }}</p>
                    <p class="mx-auto mt-2 max-w-[590px] text-[14px] font-bold leading-[1.45] text-white">{{ $data['hero']['summary'] ?? '' }}</p>
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-9">
                        <a href="{{ $data['hero']['cta1Url'] ?? ('/'.$locale.'/research/publications') }}" class="inline-flex h-[32px] min-w-[145px] items-center justify-center rounded-[2px] bg-spu-blue px-5 text-[10px] font-bold text-white transition hover:bg-[#171d47]">{{ $data['hero']['cta1'] ?? '' }}</a>
                        <a href="{{ $data['hero']['cta2Url'] ?? ('/'.$locale.'/research/centers') }}" class="inline-flex h-[32px] min-w-[145px] items-center justify-center rounded-[2px] bg-spu-blue px-5 text-[10px] font-bold text-white transition hover:bg-[#171d47]">{{ $data['hero']['cta2'] ?? '' }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-spu-blue py-[27px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            <div class="mx-auto grid max-w-[1080px] grid-cols-2 gap-y-7 md:grid-cols-4">
                @foreach (($data['stats'] ?? []) as $stat)
                    <div class="relative flex min-h-[48px] flex-col items-center justify-center px-4 text-center {{ ! $loop->last ? 'md:border-e md:border-white/60' : '' }}">
                        <span class="text-[24px] font-bold leading-none tracking-tight text-white md:text-[25px]">{{ $stat['value'] ?? '' }}</span>
                        <span class="mt-3 text-[10px] font-medium uppercase tracking-[0.08em] text-white">{{ $stat['label'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @php($featured = $data['featuredPublication'] ?? [])
    <section id="publications" class="bg-white pb-[56px] pt-[65px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            <h2 class="mb-[38px] text-center text-[32px] font-bold uppercase leading-none tracking-[0.02em] text-spu-blue md:text-[37px]">{{ $featured['sectionTitle'] ?? '' }}</h2>
            <div class="mx-auto flex max-w-[1120px] flex-col overflow-hidden rounded-[5px] border border-[#d9dce7] bg-white shadow-[0_2px_4px_rgba(0,0,0,0.2)] lg:min-h-[400px] lg:flex-row">
                <div class="flex-1 px-7 py-9 md:px-[54px] md:py-[68px]">
                    <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $featured['eyebrow'] ?? '' }}</p>
                    <h3 class="mt-5 text-[20px] font-bold leading-snug text-spu-blue md:text-[22px]">{{ $featured['title'] ?? '' }}</h3>
                    <p class="mt-4 max-w-[625px] text-[14px] leading-[1.55] text-[#333742]">{{ $featured['summary'] ?? '' }}</p>
                    <div class="mt-9 flex flex-wrap items-center gap-8 border-s-2 border-[#c8ccd9] ps-8">
                        <div class="min-w-[105px]"><p class="text-[9px] font-medium uppercase tracking-[0.12em] text-[#6f7280]">{{ $featured['authorLabel'] ?? '' }}</p><p class="mt-1 text-[11px] font-bold text-spu-blue">{{ $featured['authorName'] ?? '' }}</p></div>
                        <div class="min-w-[120px]"><p class="text-[9px] font-medium uppercase tracking-[0.12em] text-[#6f7280]">{{ $featured['affiliationLabel'] ?? '' }}</p><p class="mt-1 text-[11px] font-bold text-spu-blue">{{ $featured['affiliation'] ?? '' }}</p></div>
                        <div class="min-w-[100px]"><p class="text-[9px] font-medium uppercase tracking-[0.12em] text-[#6f7280]">{{ $featured['publishedLabel'] ?? '' }}</p><p class="mt-1 text-[11px] font-bold text-spu-blue">{{ $featured['date'] ?? '' }}</p></div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-[52px]">
                        <a href="/{{ $locale }}/research/publications/{{ $featured['slug'] ?? '' }}" class="group inline-flex h-[37px] min-w-[150px] items-center justify-center gap-2 rounded-[6px] bg-spu-blue px-5 text-[11px] font-bold text-white transition hover:bg-[#171d47]">
                            <span>{{ $featured['viewCta'] ?? '' }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 group-hover:rtl:-translate-x-1" aria-hidden="true">
                        </a>
                        @if (! empty($featured['doi']))
                            <a href="https://doi.org/{{ $featured['doi'] }}" target="_blank" rel="noopener" class="inline-flex h-[37px] min-w-[142px] items-center justify-center rounded-[6px] border border-[#202759] px-5 text-[11px] font-bold text-spu-blue transition hover:bg-spu-blue/5">{{ $featured['downloadCta'] ?? '' }}</a>
                        @endif
                    </div>
                </div>
                <div class="relative min-h-[355px] w-full shrink-0 border-t-[5px] border-spu-red bg-[#7e86aa] lg:min-h-0 lg:w-[370px]">
                    <img src="{{ $featured['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $featured['title'] ?? '' }}" class="h-[305px] w-full object-cover">
                    <div class="absolute bottom-0 left-0 right-0 h-[76px] bg-[#858caf]"></div>
                    @if (! empty($featured['doi']))
                        <div class="absolute bottom-0 left-[86px] right-0 rounded-t-[3px] bg-white px-4 py-3 shadow-sm rtl:left-0 rtl:right-[86px]">
                            <p class="text-[8px] font-medium uppercase tracking-[0.14em] text-[#8a8a9a]">{{ $featured['doiLabel'] ?? '' }}</p>
                            <p class="mt-1 text-[11px] font-bold text-spu-blue">{{ $featured['doi'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section id="centers" class="bg-white pb-[106px] pt-[8px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            <h2 class="mb-[36px] text-center text-[31px] font-bold leading-tight text-spu-blue md:text-[38px]">{{ $data['gateway']['sectionTitle'] ?? '' }}</h2>
            <div class="mx-auto grid max-w-[1140px] grid-cols-1 gap-x-[38px] gap-y-[36px] md:grid-cols-2 lg:grid-cols-3">
                @foreach (($data['gateway']['cards'] ?? []) as $card)
                    <div class="group relative min-h-[205px] rounded-[6px] border border-[#d2d5df] bg-white px-[27px] pb-7 pt-10 shadow-[0_2px_4px_rgba(0,0,0,0.18)] transition hover:-translate-y-1 hover:shadow-[0_10px_24px_rgba(32,39,89,0.12)]">
                        <div class="absolute -top-[24px] left-[42px] flex h-[50px] w-[50px] items-center justify-center rounded-full border border-[#d2d5df] bg-white text-[22px] font-bold leading-none text-spu-blue shadow-[0_1px_1px_rgba(0,0,0,0.05)] transition group-hover:border-spu-blue group-hover:bg-spu-blue group-hover:text-white rtl:left-auto rtl:right-[42px]">{{ $card['number'] ?? '' }}</div>
                        <h3 class="text-[22px] font-bold leading-tight text-spu-blue">{{ $card['title'] ?? '' }}</h3>
                        <p class="mt-3 max-w-[280px] text-[15px] leading-[1.7] text-[#50525c]">{{ $card['summary'] ?? '' }}</p>
                        <a href="{{ $card['url'] ?? '#' }}" class="mt-4 inline-flex items-center gap-2 text-[15px] font-bold text-spu-red transition hover:gap-3"><span>{{ $card['cta'] ?? '' }}</span><span class="rtl:rotate-180">&rarr;</span></a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
