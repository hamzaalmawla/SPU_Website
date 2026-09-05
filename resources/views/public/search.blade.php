@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $query = $results->query;
        $activeType = $results->type;

        // Every link out of this page carries the query and the active filter,
        // so paging or switching language never drops the visitor's search.
        $searchQuery = array_filter(
            ['q' => $query, 'type' => $activeType === 'all' ? '' : $activeType],
            fn (string $value): bool => $value !== '',
        );
        $searchPageUrl = function (int $pageNumber) use ($locale, $searchQuery): string {
            $parameters = [...$searchQuery, ...($pageNumber > 1 ? ['page' => $pageNumber] : [])];

            return '/'.$locale.'/search'.($parameters !== [] ? '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : '');
        };
    @endphp

    <section class="relative overflow-hidden bg-spu-blue pt-28 pb-12 font-hacen">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue via-spu-blue to-spu-blue/85" aria-hidden="true"></div>

        <div class="container relative z-10 max-w-[1180px] text-white">
            <nav class="mb-3 flex items-center gap-2 text-[11px] font-semibold text-white/74" aria-label="{{ $isAr ? 'مسار التنقل' : 'Breadcrumb' }}">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ __('public.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ __('public.search_page_title') }}</span>
            </nav>

            <h1 class="text-[28px] font-bold leading-tight md:text-[36px]">
                @if ($results->hasQuery)
                    {{ __('public.search_results_for', ['query' => $query]) }}
                @else
                    {{ __('public.search_page_title') }}
                @endif
            </h1>

            <form method="GET"
                  action="/{{ $locale }}/search"
                  role="search"
                  aria-label="{{ __('public.search_landmark') }}"
                  class="mt-6 flex flex-wrap items-center gap-2">
                <label for="site-search-query" class="sr-only">{{ __('public.search_field_label') }}</label>
                <input id="site-search-query"
                       type="search"
                       name="q"
                       value="{{ $query }}"
                       autocomplete="off"
                       maxlength="100"
                       placeholder="{{ __('public.search_site_placeholder') }}"
                       class="h-12 min-w-[240px] flex-1 rounded-[6px] border border-white/20 bg-white px-4 text-sm font-semibold text-spu-blue outline-none transition placeholder:font-medium placeholder:text-slate-400 focus:border-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">

                @if ($activeType !== 'all')
                    <input type="hidden" name="type" value="{{ $activeType }}">
                @endif

                <button type="submit"
                        class="h-12 rounded-[6px] bg-spu-red px-7 text-xs font-bold uppercase tracking-[0.08em] text-white transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    {{ __('public.search_submit') }}
                </button>
            </form>
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-14">
        <div class="container max-w-[1180px]">
            @if ($results->hasQuery && ! $results->queryTooShort)
                <form method="GET" action="/{{ $locale }}/search" class="flex flex-wrap items-center gap-2" aria-label="{{ __('public.search_filter_label') }}">
                    <input type="hidden" name="q" value="{{ $query }}">
                    <span class="sr-only" id="search-filter-label">{{ __('public.search_filter_label') }}</span>

                    @foreach ($types as $type)
                        <button type="submit"
                                name="type"
                                value="{{ $type }}"
                                aria-describedby="search-filter-label"
                                @if ($activeType === $type) aria-current="true" @endif
                                class="rounded-[5px] border px-4 py-2 text-[11px] font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $activeType === $type ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 bg-white text-spu-blue hover:border-spu-blue' }}">
                            {{ __('public.search_types.'.$type) }}
                            <span class="opacity-70" translate="no">({{ $results->typeCounts[$type] ?? 0 }})</span>
                        </button>
                    @endforeach
                </form>
            @endif

            {{-- The count is announced to screen readers when results change. --}}
            <p role="status" aria-live="polite" class="mt-6 text-[13px] font-bold text-slate-600">
                @if (! $results->hasQuery)
                    <span class="sr-only">{{ __('public.search_empty_title') }}</span>
                @elseif ($results->queryTooShort)
                    {{ __('public.search_min_length') }}
                @else
                    {{ __('public.search_results_count', ['count' => $results->total]) }}
                @endif
            </p>

            @if ($results->resultsCapped)
                <p class="mt-2 text-[12px] font-semibold text-slate-500">
                    {{-- Two numbers, because they stopped being the same one. The
                         facet counts now cover every matching row, so on a capped
                         response the matched total is larger than the list below
                         it, and naming only one made the notice claim the whole
                         corpus was on screen. Counts against the active tab, not
                         always 'all'. --}}
                    {{ __('public.search_capped_notice', [
                        'shown' => $results->total,
                        'total' => $results->typeCounts[$results->type] ?? $results->typeCounts['all'] ?? 0,
                    ]) }}
                </p>
            @endif

            @if (! $results->hasQuery)
                <div class="mt-8 rounded-[8px] border border-slate-200 bg-slate-50 p-8">
                    <h2 class="text-[18px] font-bold text-spu-blue">{{ __('public.search_empty_title') }}</h2>
                    <p class="mt-2 max-w-[52ch] text-[13px] font-medium leading-[24px] text-slate-600">{{ __('public.search_empty_text') }}</p>
                </div>
            @elseif ($results->queryTooShort)
                <div class="mt-8 rounded-[8px] border border-slate-200 bg-slate-50 p-8">
                    <h2 class="text-[18px] font-bold text-spu-blue">{{ __('public.search_min_length') }}</h2>
                </div>
            @elseif ($results->items->isEmpty())
                <div class="mt-8 rounded-[8px] border border-slate-200 bg-slate-50 p-8">
                    <h2 class="text-[18px] font-bold text-spu-blue">{{ __('public.search_no_results_title') }}</h2>
                    <p class="mt-2 text-[13px] font-medium text-slate-600">{{ __('public.search_no_results_text', ['query' => $query]) }}</p>

                    <p class="mt-5 text-[13px] font-bold text-spu-blue">{{ __('public.search_suggestions_title') }}</p>
                    <ul class="mt-2 list-disc space-y-1.5 ps-5 text-[13px] font-medium leading-[22px] text-slate-600">
                        <li>{{ __('public.search_suggestion_spelling') }}</li>
                        <li>{{ __('public.search_suggestion_broaden') }}</li>
                        @if ($activeType !== 'all')
                            <li>
                                <a href="{{ '/'.$locale.'/search?'.http_build_query(['q' => $query], '', '&', PHP_QUERY_RFC3986) }}"
                                   class="font-bold text-spu-red underline transition hover:text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                                    {{ __('public.search_suggestion_filter') }}
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                <ul class="mt-8 space-y-4">
                    @foreach ($results->items as $result)
                        <li class="rounded-[6px] border border-slate-200 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md">
                            <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[0.06em]">
                                <span class="rounded-[3px] bg-spu-blue/8 px-2.5 py-1 text-spu-blue">{{ __('public.search_types.'.$result->type) }}</span>
                                @if ($result->publishedAt)
                                    <span class="text-spu-red" translate="no">{{ $result->publishedAt }}</span>
                                @endif
                            </div>

                            <h2 class="mt-3 text-[17px] font-bold leading-[24px] text-spu-blue">
                                <a href="{{ $result->url }}"
                                   class="transition hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                                    {{ $result->title }}
                                </a>
                            </h2>

                            @if ($result->meta)
                                <p class="mt-1.5 text-[12px] font-semibold text-slate-500">{{ $result->meta }}</p>
                            @endif

                            @if ($result->snippet !== [])
                                {{-- Segments arrive pre-split and are escaped one by one, so
                                     indexed content can never inject markup here. --}}
                                <p class="mt-3 text-[13px] font-medium leading-[23px] text-slate-600">
                                    @foreach ($result->snippet as $segment)
                                        @if ($segment['highlighted'])<mark class="rounded-[2px] bg-spu-red/12 px-0.5 font-bold text-spu-blue">{{ $segment['text'] }}</mark>@else{{ $segment['text'] }}@endif
                                    @endforeach
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <x-public.pagination :current-page="$results->currentPage"
                                     :total-pages="$results->lastPage"
                                     :page-url="$searchPageUrl"
                                     :locale="$locale"
                                     class="mt-10" />
            @endif
        </div>
    </section>
@endsection
