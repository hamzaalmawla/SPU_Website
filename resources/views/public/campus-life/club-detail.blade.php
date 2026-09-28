@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $club = is_array($section['club'] ?? null) ? $section['club'] : [];
        $previewToken = ($isPreview ?? false) && isset($preview) ? $preview->token : null;
        $clubsUrl = $previewToken ? '/'.$locale.'/preview?token='.urlencode($previewToken) : '/'.$locale.'/campus-life/clubs-activities';
    @endphp

    <section class="relative overflow-hidden font-hacen">
        <img src="{{ $club['image'] ?? '/images/campus-clubs.webp' }}" alt="{{ $club['title'] ?? '' }}" class="h-[340px] w-full object-cover md:h-[440px]">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/90 via-spu-blue/55 to-transparent"></div>
        <div class="absolute inset-0">
            <div class="container flex h-full flex-col justify-end pb-12 text-white md:pb-16">
                <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold text-white/80" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="transition hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                    <a href="{{ $clubsUrl }}" class="transition hover:text-white">{{ $section['clubs']['title'] ?? ($locale === 'ar' ? 'الأندية الطلابية' : 'Student Clubs') }}</a>
                </nav>
                @if (! empty($club['tag']))<span class="mt-5 w-fit rounded-full bg-spu-red px-4 py-1.5 text-xs font-bold">{{ $club['tag'] }}</span>@endif
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight md:text-5xl">{{ $club['title'] ?? '' }}</h1>
                @if (! empty($club['summary']))<p class="mt-4 max-w-3xl text-base leading-8 text-white/85">{{ $club['summary'] }}</p>@endif
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-14 font-hacen md:py-20">
        <div class="container">
            <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                <article class="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm md:p-10">
                    <h2 class="text-2xl font-black text-spu-blue">{{ $locale === 'ar' ? 'عن النادي' : 'About the Club' }}</h2>
                    <div class="mt-5 whitespace-pre-line text-base leading-8 text-slate-700">{{ $club['body'] ?? $club['summary'] ?? '' }}</div>
                </article>
                <aside class="lg:sticky lg:top-28 lg:self-start">
                    <div class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                        @if (! empty($club['signupUrl']))
                            <a href="{{ $club['signupUrl'] }}" target="_blank" rel="noopener noreferrer external" class="flex w-full items-center justify-center rounded-lg bg-spu-red px-5 py-3 text-center text-sm font-bold text-white transition hover:bg-spu-blue">{{ $club['signupLabel'] ?? ($locale === 'ar' ? 'التسجيل في النادي' : 'Join This Club') }}</a>
                            <p class="mt-3 text-center text-xs leading-5 text-slate-500">{{ $locale === 'ar' ? 'يفتح نموذج التسجيل في نافذة جديدة.' : 'The signup form opens in a new window.' }}</p>
                        @endif
                        <a href="{{ $clubsUrl }}" class="mt-4 flex w-full items-center justify-center rounded-lg border border-slate-200 px-5 py-3 text-sm font-bold text-spu-blue transition hover:border-spu-blue">{{ $section['clubs']['backLabel'] ?? ($locale === 'ar' ? 'العودة إلى الأندية' : 'Back to Clubs') }}</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
