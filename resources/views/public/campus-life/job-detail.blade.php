@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $job = is_array($section['job'] ?? null) ? $section['job'] : [];
        $labels = $section['labels'] ?? [];
        $relatedJobs = array_values(array_filter($section['relatedJobs'] ?? [], 'is_array'));
        $typeLabels = collect(array_values(array_filter($section['types'] ?? [], 'is_array')))->pluck('label', 'id');
        $canonicalUrl = url('/'.$locale.'/campus-life/career-development/jobs/'.($job['slug'] ?? ''));
        $previewToken = ($isPreview ?? false) && isset($preview) ? $preview->token : null;
        $boardUrl = $previewToken ? '/'.$locale.'/preview?token='.$previewToken : '/'.$locale.'/campus-life/career-development/jobs';
    @endphp

    <section class="relative overflow-hidden bg-white pt-28 font-hacen">
        <div class="container py-16 md:pb-20 md:pt-20">
            <div class="mx-auto max-w-[980px] text-center">
                <img src="{{ $job['image'] ?? '/images/career-development-hero.webp' }}" alt="" class="mx-auto mb-8 h-44 w-full max-w-[760px] rounded-2xl object-cover shadow-sm" aria-hidden="true">
                <nav class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-slate-500" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <a href="{{ $boardUrl }}" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'لوحة الوظائف' : 'Job Board' }}</a>
                    <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                    <span>{{ $job['title'] ?? '' }}</span>
                </nav>
                <h1 class="mt-5 text-4xl font-black leading-tight text-spu-blue md:text-5xl">{{ $job['title'] ?? '' }}</h1>
                <p class="mx-auto mt-5 max-w-[720px] text-base leading-7 text-slate-600">{{ $job['shortDescription'] ?? '' }}</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3 text-xs font-bold text-slate-500">
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $job['department'] ?? '' }}</span>
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $job['location'] ?? '' }}</span>
                    <span class="rounded-full bg-spu-blue/5 px-4 py-2">{{ $typeLabels->get($job['type'] ?? '', '') }}</span>
                    <span class="rounded-full bg-emerald-50 px-4 py-2 text-emerald-700">{{ $labels['openStatus'] ?? '' }}</span>
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
                        <dl class="mb-5 space-y-3 border-b border-slate-100 pb-5 text-xs">
                            <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">{{ $labels['postedOn'] ?? '' }}</dt><dd class="text-slate-800"><time datetime="{{ $job['postedDate'] ?? '' }}">{{ $job['postedDate'] ?? '' }}</time></dd></div>
                            <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">{{ $labels['closesOn'] ?? '' }}</dt><dd class="text-slate-800"><time datetime="{{ $job['closeDate'] ?? '' }}">{{ $job['closeDate'] ?? '' }}</time></dd></div>
                            <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">{{ $labels['status'] ?? '' }}</dt><dd class="font-bold text-emerald-700">{{ $labels['openStatus'] ?? '' }}</dd></div>
                        </dl>
                        @if (! ($isPreview ?? false) && ($job['applicationEligible'] ?? false))
                            <a href="/{{ $locale }}/campus-life/career-development/jobs/apply?job={{ urlencode((string) ($job['slug'] ?? '')) }}" class="flex w-full items-center justify-center rounded-lg bg-spu-red px-5 py-3 text-sm font-bold text-white transition hover:bg-spu-blue">{{ $labels['apply'] ?? '' }}</a>
                        @else
                            <span class="flex w-full cursor-not-allowed items-center justify-center rounded-lg bg-slate-200 px-5 py-3 text-sm font-bold text-slate-500">{{ $labels['applicationsClosed'] ?? '' }}</span>
                        @endif
                        <a href="{{ $boardUrl }}" class="mt-3 flex w-full items-center justify-center rounded-lg border border-slate-200 px-5 py-3 text-sm font-bold text-spu-blue transition hover:border-spu-blue">{{ $labels['back'] ?? '' }}</a>
                        <div class="mt-3 grid grid-cols-2 gap-2" x-data="pageShare" data-share-url="{{ $canonicalUrl }}" data-share-title="{{ $job['title'] ?? '' }}">
                            <button type="button" x-on:click="share" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">{{ $labels['share'] ?? '' }}</button>
                            <button type="button" x-on:click="copy" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">
                                <span x-show="!copied">{{ $labels['copyLink'] ?? '' }}</span>
                                <span x-show="copied" x-cloak>{{ $labels['copied'] ?? '' }}</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>

            @if ($relatedJobs !== [])
                <div class="mx-auto mt-14 max-w-[1120px]">
                    <h2 class="text-2xl font-black text-spu-blue">{{ $labels['related'] ?? '' }}</h2>
                    <div class="mt-5 grid gap-5 md:grid-cols-3">
                        @foreach ($relatedJobs as $related)
                            <a href="{{ $previewToken ? '/'.$locale.'/preview?token='.$previewToken.'&job='.urlencode((string) ($related['slug'] ?? '')) : '/'.$locale.'/campus-life/career-development/jobs/'.($related['slug'] ?? '') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-spu-blue">
                                <span class="text-xs font-bold text-spu-red">{{ $related['department'] ?? '' }}</span>
                                <h3 class="mt-2 font-black text-spu-blue">{{ $related['title'] ?? '' }}</h3>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
