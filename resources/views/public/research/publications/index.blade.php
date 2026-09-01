@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php
        $data = $page->data;
        $basePath = '/'.$locale.'/research/'.($page->type === 'repository' ? 'repository' : 'publications');
    @endphp
    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])

    <section class="bg-white pb-[80px] pt-[60px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            @php($activeFilters = $data['activeFilters'] ?? ['q' => '', 'faculty' => '', 'type' => '', 'year' => '', 'page' => 1])
            <form method="GET" action="{{ $basePath }}" class="research-filter-grid-publications mx-auto max-w-[1210px]">
                @foreach ([['faculties', 'facultyLabel', 'faculty', 'filter_faculty'], ['publicationTypes', 'typeLabel', 'type', 'filter_type'], ['years', 'yearLabel', 'year', 'filter_year']] as [$key, $labelKey, $inputName, $fallbackKey])
                    <label class="relative block">
                        {{-- The payload never supplied these, so every select shipped
                             with an empty sr-only span and no accessible name. --}}
                        <span class="sr-only">{{ $data['filters'][$labelKey] ?? __('public.'.$fallbackKey) }}</span>
                        <select name="{{ $inputName }}" onchange="this.form.submit()" class="h-[49px] w-full appearance-none rounded-[8px] border border-[#dde2ea] bg-white px-5 text-[15px] font-bold text-[#344054] shadow-[0_8px_18px_rgba(16,24,40,0.08)] outline-none transition focus:border-spu-blue">
                            @foreach (($data['filters'][$key] ?? []) as $option)
                                <option value="{{ $option['value'] ?? '' }}" @selected(($activeFilters[$inputName] ?? '') === (string) ($option['value'] ?? ''))>{{ $option['label'] ?? '' }}</option>
                            @endforeach
                        </select>
                        <img src="/images/icon-chevron-down-outline.svg" alt="" class="pointer-events-none absolute right-5 top-1/2 h-3 w-3 -translate-y-1/2 rtl:left-5 rtl:right-auto" aria-hidden="true">
                    </label>
                @endforeach
                <label class="relative block">
                    <span class="sr-only">{{ __('public.search_submit') }}</span>
                    <img src="/images/icon-search-outline.svg" alt="" class="pointer-events-none absolute left-5 top-1/2 h-4 w-4 -translate-y-1/2 opacity-70 rtl:left-auto rtl:right-5" aria-hidden="true">
                    <input name="q" value="{{ $activeFilters['q'] ?? '' }}" type="search" class="h-[49px] w-full rounded-[8px] border border-[#dde2ea] bg-white ps-12 pe-5 text-[13px] font-medium text-[#344054] shadow-[0_8px_18px_rgba(16,24,40,0.08)] outline-none transition placeholder:text-[#5b6473] focus:border-spu-blue" placeholder="{{ $data['filters']['searchPlaceholder'] ?? '' }}">
                </label>
            </form>
            <div class="mx-auto mt-4 flex max-w-[1210px] items-center justify-between gap-4 text-sm text-[#6f7280]">
                <p aria-live="polite">{{ $data['resultCount'] ?? count($data['items'] ?? []) }} {{ $locale === 'ar' ? 'نتيجة' : 'results' }}</p>
                @if (collect($activeFilters)->except('page')->filter(fn ($value) => (string) $value !== '')->isNotEmpty())
                    <a href="{{ $basePath }}" class="font-bold text-spu-red hover:text-spu-blue">{{ $locale === 'ar' ? 'مسح عوامل التصفية' : 'Clear filters' }}</a>
                @endif
            </div>
            <div class="mx-auto mt-[48px] grid max-w-[1168px] grid-cols-1 gap-x-[38px] gap-y-[38px] lg:grid-cols-2">
                @forelse (($data['items'] ?? []) as $publication)
                    @include('public.research.partials.publication-card', ['publication' => $publication, 'locale' => $locale])
                @empty
                    <div class="rounded-[10px] border border-slate-200 bg-section p-8 text-center lg:col-span-2">
                        <h2 class="text-xl font-bold text-spu-blue">{{ $locale === 'ar' ? 'لا توجد نتائج' : 'No results found' }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $locale === 'ar' ? 'جرّب تغيير كلمات البحث أو عوامل التصفية.' : 'Try changing your search terms or filters.' }}</p>
                        <a href="{{ $basePath }}" class="mt-5 inline-flex rounded-[6px] bg-spu-red px-5 py-2.5 text-xs font-bold text-white">{{ $locale === 'ar' ? 'مسح عوامل التصفية' : 'Clear filters' }}</a>
                    </div>
                @endforelse
            </div>
            @include('public.research.partials.listing-pagination', ['data' => $data, 'basePath' => $basePath, 'locale' => $locale])
        </div>
    </section>
@endsection
