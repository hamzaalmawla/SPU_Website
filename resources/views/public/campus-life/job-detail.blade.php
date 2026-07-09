@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $job = is_array($section['job'] ?? null) ? $section['job'] : [];
        $labels = $section['labels'] ?? [];
    @endphp

    <section class="relative overflow-hidden bg-white pt-28 font-hacen">
        <div class="container py-16 md:pb-20 md:pt-20">
            <div class="mx-auto max-w-[980px] text-center">
                <nav class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-slate-500" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <a href="/{{ $locale }}/campus-life/career-development/jobs" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'لوحة الوظائف' : 'Job Board' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <span>{{ $job['title'] ?? '' }}</span>
                </nav>
                <h1 class="mt-5 text-4xl font-black leading-tight text-spu-blue md:text-5xl">{{ $job['title'] ?? '' }}</h1>
                <p class="mx-auto mt-5 max-w-[720px] text-base leading-7 text-slate-600">{{ $job['shortDescription'] ?? '' }}</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3 text-xs font-bold text-slate-500">
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $job['department'] ?? '' }}</span>
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $job['location'] ?? '' }}</span>
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $job['type'] ?? '' }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-16 font-hacen md:py-20">
        <div class="container">
            <div class="mx-auto grid max-w-[1120px] gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    @foreach ([
                        'overview' => $labels['overview'] ?? '',
                        'responsibilities' => $labels['responsibilities'] ?? '',
                        'requirements' => $labels['requirements'] ?? '',
                        'benefits' => $labels['benefits'] ?? '',
                    ] as $key => $heading)
                        <article class="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm">
                            <h2 class="text-xl font-black text-spu-blue">{{ $heading }}</h2>
                            <div class="mt-4 space-y-3 text-sm font-bold leading-7 text-slate-700">
                                @foreach (array_values(array_filter($job[$key] ?? [], 'is_string')) as $item)
                                    <p>{{ $item }}</p>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="lg:sticky lg:top-28 lg:self-start">
                    <div class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                        <a href="/{{ $locale }}/campus-life/career-development/jobs/apply?job={{ $job['slug'] ?? '' }}" class="flex w-full items-center justify-center rounded-lg bg-spu-red px-5 py-3 text-sm font-bold text-white transition hover:bg-spu-blue">{{ $labels['apply'] ?? '' }}</a>
                        <a href="/{{ $locale }}/campus-life/career-development/jobs" class="mt-3 flex w-full items-center justify-center rounded-lg border border-slate-200 px-5 py-3 text-sm font-bold text-spu-blue transition hover:border-spu-blue">{{ $labels['back'] ?? '' }}</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
