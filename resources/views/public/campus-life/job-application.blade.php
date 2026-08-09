@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $hero = $section['hero'] ?? [];
        $breadcrumbs = $hero['breadcrumbs'] ?? [];
        $selectedJob = is_array($section['selectedJob'] ?? null) ? $section['selectedJob'] : [];
    @endphp

    <section class="relative bg-white font-hacen">
        <div class="relative h-[320px] overflow-hidden md:h-[360px]">
            <img src="{{ $hero['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $hero['title'] ?? '' }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/80 via-spu-blue/50 to-spu-blue/0"></div>
            <div class="absolute inset-0">
                <div class="container flex h-full flex-col items-center justify-center pt-16 text-center text-white">
                    <nav class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/85">
                        <a href="/{{ $locale }}" class="transition hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                        <a href="/{{ $locale }}/campus-life" class="transition hover:text-white">{{ $locale === 'ar' ? 'الحياة الجامعية' : 'Campus Life' }}</a>
                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                        <a href="/{{ $locale }}/campus-life/career-development" class="transition hover:text-white">{{ $locale === 'ar' ? 'التطوير المهني' : 'Career Development' }}</a>
                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                        <a href="/{{ $locale }}/campus-life/career-development#job-board" class="transition hover:text-white">{{ $locale === 'ar' ? 'لوحة الوظائف' : 'Job Board' }}</a>
                        <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                        <span>{{ $hero['title'] ?? '' }}</span>
                    </nav>
                    <h1 class="mt-2 text-4xl font-bold leading-tight md:text-5xl">{{ $hero['title'] ?? '' }}</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-16" x-data="dynamicFormShell()" data-form-id="job-application" data-locale="{{ $locale }}" data-job-id="{{ $selectedJob['id'] ?? '' }}" data-job-slug="{{ $selectedJob['slug'] ?? '' }}" data-preview="{{ !empty($isPreview) ? '1' : '0' }}">
        <div class="container mx-auto">
            <div class="mx-auto">
                <div class="mb-8 rounded-2xl border border-spu-blue/10 bg-spu-blue/[0.03] p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-spu-blue/10">
                            <svg class="h-6 w-6 text-spu-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-spu-blue">{{ $section['info']['title'] ?? '' }}</h2>
                            <p class="mt-1 text-sm text-spu-blue/60">{{ $section['info']['summary'] ?? '' }}</p>
                            <p class="mt-3 text-sm font-bold text-spu-red">{{ $section['info']['selectedJob'] ?? '' }} {{ $selectedJob['title'] ?? '' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-gray-50 p-6 sm:p-8">
                    @include('public.forms.dynamic-form')
                </div>
            </div>
        </div>
    </section>
@endsection
