@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($item = $page->item)
    @php($data = $page->data)

    <section class="bg-white pb-10 pt-28 font-hacen md:pb-14 md:pt-32" dir="{{ $direction }}">
        <div class="container mx-auto">
            <nav class="mb-6 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-slate-500" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'البحث' : 'Research' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research/publications" class="transition hover:text-spu-blue">{{ $locale === 'ar' ? 'المنشورات' : 'Publications' }}</a>
            </nav>
            <figure class="mx-auto container overflow-hidden rounded-[8px]">
                <img src="{{ $item['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $item['title'] ?? '' }}" class="mx-auto h-[220px] w-[80%] object-cover object-top md:h-[400px]">
            </figure>
            <h1 class="mx-auto mt-8 max-w-[820px] text-center text-2xl font-bold leading-snug text-spu-blue md:text-[32px] md:leading-tight">{{ $item['title'] ?? '' }}</h1>
        </div>
    </section>

    <section class="bg-slate-50 py-10 font-hacen md:py-14" dir="{{ $direction }}">
        <div class="container mx-auto max-w-[1120px] px-4 sm:px-6">
            <div class="research-detail-grid">
                <section class="space-y-6">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] md:p-8">
                        <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'الملخص' : 'Summary' }}</h2>
                        <p class="mt-4 text-[15px] leading-8 text-slate-700">{{ $item['lead'] ?? $item['summary'] ?? '' }}</p>
                    </div>
                    @if (! empty($item['paragraphs']))
                        <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] md:p-8">
                            @foreach ($item['paragraphs'] as $paragraph)
                                <p class="mt-4 first:mt-0 text-[15px] leading-8 text-slate-700">{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @endif
                    @if (! empty($item['keyStatement']))
                        <blockquote class="rounded-[8px] border-s-4 border-spu-red bg-white p-6 text-[17px] font-bold leading-8 text-spu-blue shadow-[0_8px_24px_rgba(15,23,42,0.04)]">{{ $item['keyStatement'] }}</blockquote>
                    @endif
                    <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                        <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'الكلمات المفتاحية' : 'Keywords' }}</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (($item['keywords'] ?? []) as $keyword)
                                <span class="inline-flex rounded-full border border-spu-blue/15 bg-spu-blue/5 px-3.5 py-1.5 text-xs font-semibold text-spu-blue">{{ $keyword }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $item['scholarUrl'] ?? '#' }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2.5 rounded-[6px] bg-spu-red px-6 text-xs font-bold uppercase tracking-[0.08em] text-white transition hover:bg-spu-red/90 hover:shadow-lg">{{ $locale === 'ar' ? 'عرض على Google Scholar' : 'View on Google Scholar' }}</a>
                        <a href="{{ $item['scopusUrl'] ?? '#' }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2.5 rounded-[6px] border border-spu-blue/20 bg-white px-6 text-xs font-bold uppercase tracking-[0.08em] text-spu-blue transition hover:border-spu-blue hover:bg-spu-blue/5">{{ $locale === 'ar' ? 'بحث في Scopus' : 'Search on Scopus' }}</a>
                    </div>
                    @if (! empty($data['related']))
                        <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                            <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'منشورات ذات صلة' : 'Related Publications' }}</h2>
                            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                                @foreach ($data['related'] as $related)
                                    <a href="/{{ $locale }}/research/publications/{{ $related['slug'] ?? '' }}" class="group rounded-[8px] border border-slate-100 p-4 transition hover:border-spu-blue/20 hover:shadow-[0_4px_12px_rgba(32,39,89,0.06)]">
                                        <h3 class="text-[13px] font-bold leading-snug text-spu-blue group-hover:text-spu-red">{{ $related['title'] ?? '' }}</h3>
                                        <p class="mt-2 text-[11px] text-slate-500">{{ $related['year'] ?? '' }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <nav class="flex flex-wrap justify-between gap-4" aria-label="{{ $locale === 'ar' ? 'منشورات أخرى' : 'Other publications' }}">
                        @if (! empty($data['previous']))
                            <a href="/{{ $locale }}/research/publications/{{ $data['previous']['slug'] }}" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ $locale === 'ar' ? 'السابق' : 'Previous' }}: {{ $data['previous']['title'] }}</a>
                        @endif
                        @if (! empty($data['next']))
                            <a href="/{{ $locale }}/research/publications/{{ $data['next']['slug'] }}" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ $locale === 'ar' ? 'التالي' : 'Next' }}: {{ $data['next']['title'] }}</a>
                        @endif
                    </nav>
                </section>
                <aside class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] lg:sticky lg:top-28">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'معلومات المنشور' : 'Publication Info' }}</h2>
                    <dl class="mt-5 divide-y divide-slate-100">
                        @foreach ([($locale === 'ar' ? 'الفئة' : 'Category') => $item['category'] ?? $item['type'] ?? '', ($locale === 'ar' ? 'المؤلف' : 'Author') => $item['author'] ?? '', ($locale === 'ar' ? 'السنة' : 'Year') => $item['year'] ?? '', ($locale === 'ar' ? 'الكلية' : 'Faculty') => $item['faculty'] ?? '', ($locale === 'ar' ? 'الربع' : 'Quartile') => $item['rate'] ?? 'To be verified', ($locale === 'ar' ? 'النوع' : 'Type') => $item['type'] ?? ''] as $label => $value)
                            <div class="py-3 first:pt-0"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-bold text-spu-blue">{{ $value }}</dd></div>
                        @endforeach
                        <div class="py-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">DOI</dt><dd class="mt-1"><a href="https://doi.org/{{ $item['doi'] ?? '' }}" target="_blank" rel="noopener" class="break-all text-sm font-bold text-spu-blue transition hover:text-spu-red hover:underline">{{ $item['doi'] ?? '' }}</a></dd></div>
                        <div class="py-3 last:pb-0"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $locale === 'ar' ? 'مجالات البحث' : 'Research Themes' }}</dt><dd class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (($item['resolvedThemes'] ?? []) as $theme)
                                <a href="/{{ $locale }}/research/themes/{{ $theme['slug'] ?? '' }}" class="inline-flex rounded-full border border-spu-blue/15 bg-spu-blue/5 px-2.5 py-1 text-[10px] font-semibold text-spu-blue transition hover:border-spu-blue/40 hover:bg-spu-blue/10">{{ $theme['label'] ?? $theme['slug'] ?? '' }}</a>
                            @endforeach
                        </dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </section>
@endsection
