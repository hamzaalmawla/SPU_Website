@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)

    <section class="bg-white font-hacen" dir="{{ $direction }}">
        <div class="relative h-[370px] overflow-hidden">
            <img src="{{ $data['hero']['backgroundImage'] ?? '/images/uni-main-place.JPG' }}" alt="SPU Campus" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-spu-blue/50"></div>
            <div class="container relative z-10 flex h-full flex-col items-center justify-center text-center text-white">
                <nav class="flex flex-wrap items-center justify-center gap-2 text-[13px] font-bold" aria-label="Breadcrumb">
                    @foreach (($data['hero']['breadcrumbs'] ?? []) as $item)
                        <span class="inline-flex items-center gap-2"><a href="{{ $item['url'] ?? '#' }}" class="transition hover:text-white/75">{{ $item['label'] ?? '' }}</a>@if (! $loop->last)<span class="text-white/85">›</span>@endif</span>
                    @endforeach
                </nav>
                <h1 class="mt-6 text-[34px] font-bold leading-tight md:text-[42px]">{{ $data['hero']['title'] ?? '' }}</h1>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="#research-laboratories" class="inline-flex h-[39px] min-w-[150px] items-center justify-center rounded-[3px] bg-spu-red px-6 text-[11px] font-bold uppercase tracking-[0.05em] text-white transition hover:bg-[#5c1111]">{{ $data['hero']['primaryCta'] ?? '' }}</a>
                    <a href="{{ $data['hero']['secondaryCtaUrl'] ?? ('/'.$locale.'/research/office') }}" class="inline-flex h-[39px] min-w-[195px] items-center justify-center rounded-[5px] border border-white bg-white/10 px-6 text-[11px] font-bold text-white transition hover:bg-white/20">{{ $data['hero']['secondaryCta'] ?? '' }}</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white pb-[80px] pt-[60px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            <div class="research-centers-intro-grid mx-auto max-w-[1070px] gap-14">
                <div><div class="h-[2px] w-[185px] bg-spu-red"></div><h2 class="mt-8 max-w-[470px] text-[30px] font-bold leading-[1.35] text-spu-blue">{{ $data['intro']['title'] ?? '' }}</h2><p class="mt-7 whitespace-pre-line text-[17px] leading-[1.55] text-[#33405d]">{{ $data['intro']['summary'] ?? '' }}</p></div>
                <div class="hidden h-[184px] bg-[#b9bfcc] lg:block"></div>
                <div class="grid gap-10">@foreach (($data['intro']['highlights'] ?? []) as $item)<article class="grid grid-cols-[28px_1fr] gap-6"><img src="{{ $item['icon'] ?? '' }}" alt="" class="mt-1 h-[20px] w-[20px]" aria-hidden="true"><div><h3 class="text-[14px] font-bold text-spu-blue">{{ $item['title'] ?? '' }}</h3><p class="mt-2 text-[10px] leading-relaxed text-[#556070]">{{ $item['summary'] ?? '' }}</p></div></article>@endforeach</div>
            </div>
        </div>
        <div class="container mx-auto mt-[80px] px-6"><div class="mx-auto max-w-[1090px]"><h2 class="mb-[48px] text-[32px] font-bold text-spu-blue">{{ $locale === 'ar' ? 'مراكز البحث' : 'Research Centers' }}</h2><div class="grid grid-cols-1 gap-[38px] md:grid-cols-2 lg:grid-cols-3">
            @foreach (($data['items'] ?? []) as $center)
                <a href="/{{ $locale }}/research/centers/{{ $center['slug'] ?? '' }}" class="group block overflow-hidden rounded-[10px] border border-[#d5d9e2] bg-white shadow-[0_4px_10px_rgba(0,0,0,0.12)] transition-all hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(32,39,89,0.12)]"><div class="relative h-[180px] overflow-hidden"><img src="{{ $center['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $center['name'] ?? '' }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"><div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div><span class="absolute left-4 top-4 rounded-[4px] bg-white/90 px-3 py-1 text-[10px] font-bold text-spu-blue backdrop-blur-sm rtl:left-auto rtl:right-4">{{ $center['faculty'] ?? '' }}</span></div><div class="p-5"><h3 class="text-[18px] font-bold leading-tight text-spu-blue transition-colors group-hover:text-spu-red">{{ $center['name'] ?? '' }}</h3><p class="mt-3 text-[13px] leading-[1.7] text-[#33405d] line-clamp-3">{{ $center['mission'] ?? '' }}</p><div class="mt-4 grid grid-cols-2 gap-3">@foreach ([['labs','Labs','مختبر'],['researchers','Researchers','باحث'],['projects','Projects','مشروع'],['publications','Publications','منشور']] as [$key,$en,$ar])<div class="rounded-[6px] bg-spu-blue/[0.04] p-3 text-center"><span class="block text-[20px] font-bold text-spu-blue">{{ $center[$key] ?? 0 }}</span><span class="text-[10px] text-[#6f7280]">{{ $locale === 'ar' ? $ar : $en }}</span></div>@endforeach</div><div class="mt-4 flex items-center gap-2 text-[12px] text-[#6f7280]"><span>{{ $center['directorName'] ?? '' }}</span></div><div class="mt-5 flex items-center gap-3"><span class="inline-flex h-[32px] items-center justify-center rounded-[6px] bg-spu-blue px-4 text-[11px] font-bold text-white transition group-hover:bg-[#171d47]">{{ $locale === 'ar' ? 'عرض المركز' : 'View Center' }}</span><span class="text-spu-red transition-all group-hover:translate-x-1 rtl:group-hover:-translate-x-1">→</span></div></div></a>
            @endforeach
        </div></div></div>
        <div id="research-laboratories" class="mx-auto mt-[80px] max-w-[1090px] px-6"><h2 class="mb-[48px] text-[32px] font-bold leading-tight text-spu-blue">{{ $data['laboratories']['title'] ?? '' }}</h2><div class="grid grid-cols-1 gap-[38px] md:grid-cols-2 lg:grid-cols-3">@foreach (($data['laboratories']['items'] ?? []) as $lab)<article class="overflow-hidden rounded-[10px] border border-[#d4d8e2] bg-white shadow-[0_4px_10px_rgba(0,0,0,0.08)] transition-all hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(32,39,89,0.12)]"><div class="relative h-[170px] overflow-hidden"><img src="{{ $lab['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $lab['title'] ?? '' }}" class="h-full w-full object-cover"><span class="absolute left-4 top-4 rounded-[4px] bg-white/90 px-3 py-1.5 text-[10px] font-semibold text-spu-blue backdrop-blur-sm rtl:left-auto rtl:right-4">{{ $lab['faculty'] ?? '' }}</span></div><div class="p-5"><h3 class="text-[18px] font-bold leading-[1.35] text-spu-blue">{{ $lab['title'] ?? '' }}</h3><p class="mt-3 text-[13px] leading-[1.7] text-[#33405d]">{{ $lab['summary'] ?? '' }}</p><div class="mt-4 space-y-2 text-[13px] text-[#33405d]"><div>♙ {{ $lab['director'] ?? '' }}</div><div>▣ {{ $lab['projects'] ?? '' }}</div><div>▤ {{ $lab['publications'] ?? '' }}</div><div>✉ {{ $lab['contact'] ?? '' }}</div></div><a href="{{ $lab['ctaUrl'] ?? '#' }}" class="mt-5 inline-flex h-[36px] w-full items-center justify-center rounded-[8px] bg-spu-blue text-[11px] font-bold text-white transition hover:bg-[#171d47]">{{ $lab['cta'] ?? '' }}</a></div></article>@endforeach</div></div>
    </section>
@endsection
