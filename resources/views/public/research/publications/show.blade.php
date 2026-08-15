@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($item = $page->item)
    @php($data = $page->data)

    <section class="bg-white pb-10 pt-28 font-hacen md:pb-14 md:pt-32" dir="{{ $direction }}">
        <div class="container mx-auto">
            <nav class="mb-6 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-slate-500" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition hover:text-spu-blue">{{ __('public.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research" class="transition hover:text-spu-blue">{{ __('public.research') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2.5 w-2.5 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research/publications" class="transition hover:text-spu-blue">{{ __('public.publications') }}</a>
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
                    @if (! empty($item['lead']) || ! empty($item['summary']))
                        <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] md:p-8">
                            <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ __('public.summary') }}</h2>
                            <p class="mt-4 text-[15px] leading-8 text-slate-700">{{ $item['lead'] ?? $item['summary'] ?? '' }}</p>
                        </div>
                    @endif
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
                    @if (! empty($item['keywords']))
                        <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                            <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ __('public.keywords') }}</h2>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach (($item['keywords'] ?? []) as $keyword)
                                    <span class="inline-flex rounded-full border border-spu-blue/15 bg-spu-blue/5 px-3.5 py-1.5 text-xs font-semibold text-spu-blue">{{ $keyword }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (! empty($item['scholarUrl']) || ! empty($item['scopusUrl']))
                        <div class="flex flex-wrap gap-3">
                            @if (! empty($item['scholarUrl']))
                                <a href="{{ $item['scholarUrl'] }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2.5 rounded-[6px] bg-spu-red px-6 text-xs font-bold uppercase tracking-[0.08em] text-white transition hover:bg-spu-red/90 hover:shadow-lg">{{ __('public.view_on_google_scholar') }}</a>
                            @endif
                            @if (! empty($item['scopusUrl']))
                                <a href="{{ $item['scopusUrl'] }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2.5 rounded-[6px] border border-spu-blue/20 bg-white px-6 text-xs font-bold uppercase tracking-[0.08em] text-spu-blue transition hover:border-spu-blue hover:bg-spu-blue/5">{{ __('public.search_on_scopus') }}</a>
                            @endif
                        </div>
                    @endif
                    @if (! empty($item['downloads']))
                        <section class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]" aria-labelledby="publication-files-heading">
                            <h2 id="publication-files-heading" class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ __('public.publication_files') }}</h2>
                            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($item['downloads'] as $download)
                                    <li>
                                        <a href="{{ $download['url'] }}" class="flex min-h-12 items-center justify-between gap-4 rounded-[6px] border border-spu-blue/15 px-4 py-3 text-sm font-bold text-spu-blue transition hover:border-spu-blue hover:bg-spu-blue/5" download>
                                            <span>{{ $download['label'] ?? __('public.download_file', ['type' => $download['type'] ?? '']) }}</span>
                                            @if (! empty($download['type']))
                                                <span class="shrink-0 rounded bg-slate-100 px-2 py-1 text-[10px] uppercase tracking-wider text-slate-500">{{ $download['type'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if (! empty($data['related']))
                        <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)]">
                            <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ __('public.related_publications') }}</h2>
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
                    <nav class="flex flex-wrap justify-between gap-4" aria-label="{{ __('public.publications') }}">
                        @if (! empty($data['previous']))
                            <a href="/{{ $locale }}/research/publications/{{ $data['previous']['slug'] }}" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ __('public.previous') }}: {{ $data['previous']['title'] }}</a>
                        @endif
                        @if (! empty($data['next']))
                            <a href="/{{ $locale }}/research/publications/{{ $data['next']['slug'] }}" class="text-sm font-bold text-spu-blue hover:text-spu-red">{{ __('public.next') }}: {{ $data['next']['title'] }}</a>
                        @endif
                    </nav>
                </section>
                <aside class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_8px_24px_rgba(15,23,42,0.04)] lg:sticky lg:top-28">
                    <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ __('public.publication_info') }}</h2>
                    <dl class="mt-5 divide-y divide-slate-100">
                        @foreach ([__('public.category') => $item['category'] ?? $item['type'] ?? '', __('public.author') => $item['author'] ?? '', __('public.publication_date') => $item['publicationDate'] ?? $item['year'] ?? '', __('public.journal') => $item['journalTitle'] ?? $item['publisher'] ?? '', __('public.volume') => $item['volume'] ?? '', __('public.issue') => $item['issue'] ?? '', __('public.pages') => $item['pages'] ?? '', __('public.issn') => $item['issn'] ?? '', __('public.license') => $item['license'] ?? '', __('public.faculty') => $item['faculty'] ?? '', __('public.quartile') => $item['rate'] ?? '', __('public.type') => $item['type'] ?? ''] as $label => $value)
                            @if ($value !== '')
                                <div class="py-3 first:pt-0"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-bold text-spu-blue">{{ $value }}</dd></div>
                            @endif
                        @endforeach
                        @if (! empty($item['doi']))
                            <div class="py-3"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">DOI</dt><dd class="mt-1"><a href="https://doi.org/{{ $item['doi'] }}" target="_blank" rel="noopener" class="break-all text-sm font-bold text-spu-blue transition hover:text-spu-red hover:underline">{{ $item['doi'] }}</a></dd></div>
                        @endif
                        @if (! empty($item['resolvedThemes']))
                            <div class="py-3 last:pb-0"><dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('public.research_themes') }}</dt><dd class="mt-2 flex flex-wrap gap-1.5">
                                @foreach (($item['resolvedThemes'] ?? []) as $theme)
                                    <a href="/{{ $locale }}/research/themes/{{ $theme['slug'] ?? '' }}" class="inline-flex rounded-full border border-spu-blue/15 bg-spu-blue/5 px-2.5 py-1 text-[10px] font-semibold text-spu-blue transition hover:border-spu-blue/40 hover:bg-spu-blue/10">{{ $theme['label'] ?? $theme['slug'] ?? '' }}</a>
                                @endforeach
                            </dd></div>
                        @endif
                    </dl>
                    <div class="mt-6 border-t border-slate-100 pt-6" x-data="pageShare" data-share-url="{{ url('/'.$locale.'/research/publications/'.($item['slug'] ?? '')) }}" data-share-title="{{ $item['title'] ?? '' }}">
                        <h3 class="text-[13px] font-bold uppercase tracking-[0.12em] text-spu-red">{{ $locale === 'ar' ? 'مشاركة المنشور' : 'Share Publication' }}</h3>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="share" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">{{ $locale === 'ar' ? 'مشاركة' : 'Share' }}</button>
                            <button type="button" x-on:click="copy" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-spu-blue transition hover:border-spu-blue">
                                <span x-show="!copied">{{ $locale === 'ar' ? 'نسخ الرابط' : 'Copy link' }}</span>
                                <span x-show="copied" x-cloak>{{ $locale === 'ar' ? 'تم النسخ' : 'Copied' }}</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
