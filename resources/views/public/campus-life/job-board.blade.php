@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $hero = $section['hero'] ?? [];
        $labels = $section['labels'] ?? [];
        $jobs = array_values(array_filter($section['jobs'] ?? [], 'is_array'));
    @endphp

    <section class="relative overflow-hidden bg-white pt-28 font-hacen">
        <div class="container relative z-10 py-16 md:pb-20 md:pt-20">
            <div class="mx-auto max-w-[900px] text-center">
                <nav class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-slate-500" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <a href="/{{ $locale }}/campus-life" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'الحياة الجامعية' : 'Campus Life' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <a href="/{{ $locale }}/campus-life/career-development" class="transition hover:text-spu-blue">{{ __('public.career_development') }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <span>{{ $hero['title'] ?? '' }}</span>
                </nav>

                <h1 class="mt-5 text-4xl font-black leading-[1.1] tracking-tight text-spu-blue md:text-5xl lg:text-[58px]">
                    {{ $hero['title'] ?? '' }} <span class="relative inline-block text-spu-red">{{ $locale === 'ar' ? 'معنا' : 'With Us' }}<span class="absolute -bottom-2 left-0 h-1.5 w-full rounded-full bg-spu-red/20"></span></span>
                </h1>
                <p class="mx-auto mt-6 max-w-[640px] text-base leading-relaxed text-slate-600 md:text-lg">{{ $hero['summary'] ?? '' }}</p>
            </div>
        </div>
    </section>

    <section id="jobs-listing" class="bg-white py-16 font-hacen md:py-20">
        <div class="container">
            <p class="mx-auto max-w-[1100px] text-xs font-bold text-slate-500">
                {{ $labels['showing'] ?? '' }} {{ count($jobs) }} {{ $labels['positions'] ?? '' }}
            </p>

            <div class="mx-auto mt-6 grid max-w-[1280px] grid-cols-1 gap-7 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($jobs as $job)
                    <article class="group flex flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-[0_10px_24px_rgba(15,23,42,0.08)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_18px_34px_rgba(15,23,42,0.12)]">
                        <div class="relative h-16 overflow-hidden bg-spu-blue px-5 py-4">
                            <div class="absolute inset-0 bg-gradient-to-br from-white/8 to-transparent"></div>
                            <div class="relative flex h-full justify-between">
                                <span class="h-fit w-fit rounded-[3px] bg-white px-2 text-[10px] font-bold text-spu-blue shadow-sm">{{ $job['department'] ?? '' }}</span>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-white/90">{{ $job['type'] ?? '' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-5">
                            <h2 class="text-base font-bold leading-tight text-spu-blue">{{ $job['title'] ?? '' }}</h2>
                            <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-600">{{ $job['shortDescription'] ?? '' }}</p>

                            <div class="mt-auto flex flex-wrap gap-2 pt-5">
                                <a href="/{{ $locale }}/campus-life/career-development/jobs/{{ $job['slug'] ?? '' }}" class="inline-flex h-9 items-center justify-center rounded-md bg-spu-blue px-4 text-[11px] font-bold text-white transition hover:bg-spu-red">{{ $labels['learnMore'] ?? '' }}</a>
                                <a href="/{{ $locale }}/campus-life/career-development/jobs/apply?job={{ $job['slug'] ?? '' }}" class="inline-flex h-9 items-center justify-center rounded-md border border-spu-red px-4 text-[11px] font-bold text-spu-red transition hover:bg-spu-red hover:text-white">{{ $labels['apply'] ?? '' }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
