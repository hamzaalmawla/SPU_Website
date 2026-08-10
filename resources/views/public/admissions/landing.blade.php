@extends('layouts.public')

@section('content')
    @php
        $landing = $page->landing;
    @endphp

    <section class="relative overflow-hidden bg-section font-hacen">
        <div class="container relative z-10 grid items-start gap-10 pb-32 pt-44 lg:grid-cols-[1fr_1.2fr] lg:pb-40 lg:pt-48">
            <div class="flex flex-col gap-10 pt-4">
                <h1 class="text-[50px] font-bold leading-[1.06] tracking-[-0.96px] text-spu-blue">{{ $landing['hero']['title'] ?? '' }}</h1>
                <p class="max-w-[580px] text-[30px] font-bold leading-[37px] text-[#46464f]">{{ $landing['hero']['summary'] ?? '' }}</p>

                @if (! empty($landing['hero']['primaryUrl']) || ! empty($landing['hero']['secondaryUrl']))<div class="flex flex-wrap items-center gap-[23px]">
                    @if (! empty($landing['hero']['primaryUrl']))<a href="{{ $landing['hero']['primaryUrl'] }}" class="inline-flex h-[54px] items-center justify-center rounded-[6px] bg-spu-red px-8 text-xs font-bold uppercase tracking-[1.2px] text-white transition hover:opacity-90">{{ $landing['hero']['ctaPrimary'] ?? '' }}</a>@endif
                    @if (! empty($landing['hero']['secondaryUrl']))<a href="{{ $landing['hero']['secondaryUrl'] }}" class="inline-flex h-[54px] items-center justify-center rounded-[6px] border border-[#c7c5d0] bg-white px-8 text-xs font-bold uppercase tracking-[1.2px] text-spu-blue transition hover:bg-slate-50">{{ $landing['hero']['ctaSecondary'] ?? '' }}</a>@endif
                </div>@endif

                <div class="flex flex-col gap-5 pt-2">
                    @foreach (($landing['hero']['checklistItems'] ?? []) as $item)
                        <div class="flex items-start gap-4">
                            <span class="mt-0.5 shrink-0 text-spu-blue"><img src="/images/icon-check-circle-outline.svg" alt="" class="h-6 w-6" aria-hidden="true"></span>
                            <div class="flex flex-col gap-1">
                                <h2 class="text-lg font-bold leading-7 text-[#1b1b1f]">{{ $item['title'] ?? '' }}</h2>
                                <p class="text-sm leading-5 text-[#46464f]">{{ $item['desc'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative hidden h-[649px] lg:block">
                <div class="absolute right-0 top-0 h-[480px] w-[75%] overflow-hidden rounded shadow-sm rtl:left-0 rtl:right-auto">
                    <img src="{{ $landing['hero']['images']['campus'] ?? '/images/admissions-hero-campus.webp' }}" alt="{{ $landing['hero']['images']['campusAlt'] ?? ($locale === 'ar' ? 'حرم الجامعة السورية الخاصة' : 'Syrian Private University campus') }}" class="h-full w-full object-cover">
                    <div class="absolute inset-0 bg-spu-blue/40"></div>
                </div>
                <div class="absolute bottom-0 left-0 h-[358px] w-[56%] overflow-hidden rtl:left-auto rtl:right-0">
                    <img src="{{ $landing['hero']['images']['students'] ?? '/images/admission/front-img.jpg' }}" alt="{{ $landing['hero']['images']['studentsAlt'] ?? ($locale === 'ar' ? 'طلاب الجامعة السورية الخاصة' : 'Syrian Private University students') }}" class="h-full w-full object-cover">
                    <div class="absolute inset-0 bg-spu-blue/40"></div>
                </div>
                <div class="absolute bottom-[10%] right-[6%] w-[250px] rounded bg-spu-blue px-6 pb-6 pt-[77px] text-white shadow-sm rtl:left-[6%] rtl:right-auto">
                    <p class="text-xs font-bold uppercase tracking-[0.96px] opacity-80">{{ $landing['hero']['badgeLabel'] ?? '' }}</p>
                    <p class="mt-3 text-2xl font-bold leading-[31.2px]">{{ $landing['hero']['badgeValue'] ?? '' }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="relative z-10 -mt-14 font-hacen">
        <div class="container">
            <div class="relative overflow-hidden rounded-lg bg-spu-blue shadow-[0_20px_25px_-5px_rgba(0,0,0,0.1),0_8px_10px_-6px_rgba(0,0,0,0.1)]">
                <div class="cms-grid-stats cms-grid-stats-cols-4">
                    @foreach (($landing['trustBar'] ?? []) as $item)
                        <div class="relative flex flex-col items-center gap-3 px-6 py-10 text-center">
                            @if (! $loop->first)
                                <div class="absolute left-0 top-1/2 hidden h-full w-px -translate-y-1/2 bg-white/10 md:block rtl:left-auto rtl:right-0" aria-hidden="true"></div>
                            @endif
                            <span class="text-2xl text-white"><img src="{{ $item['icon'] ?? '' }}" alt="" class="h-7 w-7 brightness-0 invert" aria-hidden="true"></span>
                            <h2 class="text-lg font-semibold leading-[31.2px] text-white md:text-xl">{{ $item['title'] ?? '' }}</h2>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-20 font-hacen lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-[600px] text-center">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['journey']['eyebrow'] ?? '' }}</p>
                <h2 class="mt-3 text-4xl font-bold leading-tight text-spu-blue md:text-5xl">{{ $landing['journey']['title'] ?? '' }}</h2>
            </div>
            <div class="relative mx-auto mt-16 max-w-[1200px]">
                <div class="absolute left-[10%] right-[10%] top-[40px] hidden h-[2px] bg-gradient-to-r from-spu-blue via-slate-200 to-slate-200 lg:block" aria-hidden="true"></div>
                <div class="cms-grid-compact gap-6 lg:gap-5">
                    @foreach (($landing['journey']['steps'] ?? []) as $step)
                        <div class="group relative flex flex-col items-center text-center">
                            <div class="relative z-10 flex h-[56px] w-[56px] items-center justify-center rounded-full text-xl font-bold shadow-md {{ ($step['active'] ?? false) ? 'bg-spu-blue text-white shadow-[0_8px_24px_rgba(32,39,89,0.3)]' : 'border-2 border-slate-200 bg-white text-spu-blue' }}">{{ $step['number'] ?? '' }}</div>
                            <div class="mt-5 w-full rounded-2xl bg-white p-6 shadow-[0_4px_20px_rgba(0,0,0,0.04)] {{ ($step['active'] ?? false) ? 'ring-2 ring-spu-blue/15' : '' }}">
                                <h3 class="text-lg font-bold text-spu-blue">{{ $step['title'] ?? '' }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $step['summary'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="timeline" class="bg-section py-20 font-hacen lg:py-28">
        <div class="container">
            <div class="grid grid-cols-1 items-stretch gap-10 lg:grid-cols-12 lg:gap-8">
                <div class="relative overflow-hidden rounded-3xl lg:col-span-5">
                    <img src="{{ $landing['timeline']['image'] ?? '/images/admissions-hero-campus.webp' }}" alt="{{ $landing['timeline']['imageAlt'] ?? ($locale === 'ar' ? 'حرم الجامعة السورية الخاصة' : 'Syrian Private University campus') }}" class="h-full min-h-[500px] w-full object-cover lg:min-h-0">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-spu-blue/90 via-spu-blue/30 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 right-0 p-8">
                        <p class="text-[11px] font-bold uppercase tracking-[1.5px] text-white/60">{{ $landing['timeline']['primaryDeadlineLabel'] ?? '' }}</p>
                        @if (! empty($landing['timeline']['primaryDeadline']))<p class="mt-2 text-[42px] font-bold leading-[1.1] tracking-tight text-white">{{ $landing['timeline']['primaryDeadline'] }}</p>@endif
                        <div class="mt-4 border-s-[3px] border-spu-red py-1 ps-5"><p class="max-w-[360px] text-[15px] leading-relaxed text-white/80">{{ $landing['timeline']['primaryDeadlineDesc'] ?? '' }}</p></div>
                    </div>
                </div>
                <div class="flex flex-col lg:col-span-7">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['timeline']['eyebrow'] ?? '' }}</p>
                    <h2 class="mt-3 text-4xl font-bold leading-tight text-spu-blue">{{ $landing['timeline']['title'] ?? '' }}</h2>
                    <p class="mt-4 max-w-[560px] text-base leading-relaxed text-slate-500">{{ $landing['timeline']['summary'] ?? '' }}</p>
                    <div class="mt-10 flex flex-1 flex-col gap-4">
                        @foreach (($landing['timeline']['phases'] ?? []) as $phase)
                            <div class="group flex items-center gap-5 rounded-2xl bg-white p-5 shadow-[0_2px_12px_rgba(0,0,0,0.04)] {{ ($phase['active'] ?? false) ? 'ring-2 ring-spu-blue/10' : '' }}">
                                <div class="h-4 w-4 shrink-0 rounded-full {{ ($phase['active'] ?? false) ? 'bg-spu-blue shadow-[0_0_0_4px_rgba(32,39,89,0.12)]' : 'border-2 border-slate-300 bg-white' }}"></div>
                                <div class="flex flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <div><p class="text-[11px] font-bold uppercase tracking-[1.2px] text-slate-400">{{ $phase['label'] ?? '' }}</p><h3 class="mt-1 text-lg font-bold text-spu-blue">{{ $phase['title'] ?? '' }}</h3></div>
                                    <div class="shrink-0 rounded-full px-4 py-1.5 text-sm font-bold {{ ($phase['active'] ?? false) ? 'bg-spu-red/8 text-spu-red' : 'bg-slate-50 text-slate-500' }}">{{ $phase['date'] ?? '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="requirements" class="bg-section py-20 font-hacen lg:py-28">
        <div class="container">
            <div class="mx-auto max-w-[700px] text-center">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-spu-red/60">{{ $landing['resources']['eyebrow'] ?? '' }}</p>
                <h2 class="mt-3 text-4xl font-bold leading-tight text-spu-blue md:text-5xl">{{ $landing['resources']['title'] ?? '' }}</h2>
                <p class="mt-4 text-lg leading-relaxed text-slate-500">{{ $landing['resources']['subtitle'] ?? '' }}</p>
            </div>
            <div class="cms-grid-cards mx-auto mt-14 max-w-[1100px] gap-5">
                @foreach (($landing['resources']['cards'] ?? []) as $card)
                    <a href="/{{ $locale }}/admissions/{{ $card['slug'] ?? '' }}" class="group relative flex flex-col overflow-hidden rounded-2xl p-7 transition-all duration-300 hover:-translate-y-1 {{ ($card['active'] ?? false) ? 'bg-spu-blue text-white shadow-[0_20px_50px_rgba(32,39,89,0.25)]' : 'bg-white text-spu-blue shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_16px_48px_rgba(0,0,0,0.1)]' }}">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ ($card['active'] ?? false) ? 'bg-white/15' : 'bg-spu-blue/5' }}"><img src="{{ $card['icon'] ?? '' }}" alt="" class="h-5 w-5 {{ ($card['active'] ?? false) ? 'brightness-0 invert' : '' }}" aria-hidden="true"></div>
                        <h3 class="mt-5 text-xl font-bold leading-snug">{{ $card['title'] ?? '' }}</h3>
                        <p class="mt-2 text-sm leading-relaxed {{ ($card['active'] ?? false) ? 'text-white/70' : 'text-slate-400' }}">{{ $card['desc'] ?? '' }}</p>
                        <div class="mt-auto pt-5"><span class="text-xs font-bold uppercase tracking-[1px] {{ ($card['active'] ?? false) ? 'text-white/80' : 'text-spu-red' }}">{{ $card['link'] ?? ($locale === 'ar' ? 'اكتشف' : 'Explore') }}</span></div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
