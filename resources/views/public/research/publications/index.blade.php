@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)
    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])

    <section class="bg-white pb-[80px] pt-[60px] font-hacen" dir="{{ $direction }}">
        <div class="container">
            @php($activeFilters = $data['activeFilters'] ?? ['q' => '', 'faculty' => '', 'type' => '', 'year' => ''])
            <form method="GET" action="/{{ $locale }}/research/publications" class="research-filter-grid-publications mx-auto max-w-[1210px]">
                @foreach ([['faculties', 'facultyLabel', 'faculty'], ['publicationTypes', 'typeLabel', 'type'], ['years', 'yearLabel', 'year']] as [$key, $labelKey, $inputName])
                    <label class="relative block">
                        <span class="sr-only">{{ $data['filters'][$labelKey] ?? '' }}</span>
                        <select name="{{ $inputName }}" onchange="this.form.submit()" class="h-[49px] w-full appearance-none rounded-[8px] border border-[#dde2ea] bg-white px-5 text-[15px] font-bold text-[#344054] shadow-[0_8px_18px_rgba(16,24,40,0.08)] outline-none transition focus:border-spu-blue">
                            @foreach (($data['filters'][$key] ?? []) as $option)
                                <option value="{{ $option['value'] ?? '' }}" @selected(($activeFilters[$inputName] ?? '') === (string) ($option['value'] ?? ''))>{{ $option['label'] ?? '' }}</option>
                            @endforeach
                        </select>
                        <img src="/images/icon-chevron-down-outline.svg" alt="" class="pointer-events-none absolute right-5 top-1/2 h-3 w-3 -translate-y-1/2 rtl:left-5 rtl:right-auto" aria-hidden="true">
                    </label>
                @endforeach
                <label class="relative block">
                    <span class="sr-only">Search</span>
                    <img src="/images/icon-search-outline.svg" alt="" class="pointer-events-none absolute left-5 top-1/2 h-4 w-4 -translate-y-1/2 opacity-70 rtl:left-auto rtl:right-5" aria-hidden="true">
                    <input name="q" value="{{ $activeFilters['q'] ?? '' }}" type="search" class="h-[49px] w-full rounded-[8px] border border-[#dde2ea] bg-white ps-12 pe-5 text-[13px] font-medium text-[#344054] shadow-[0_8px_18px_rgba(16,24,40,0.08)] outline-none transition placeholder:text-[#5b6473] focus:border-spu-blue" placeholder="{{ $data['filters']['searchPlaceholder'] ?? '' }}">
                </label>
            </form>
            <p class="mx-auto mt-4 max-w-[1210px] text-sm text-[#6f7280]">{{ $data['resultCount'] ?? count($data['items'] ?? []) }} {{ $locale === 'ar' ? 'نتيجة' : 'results' }}</p>
            <div class="mx-auto mt-[48px] grid max-w-[1168px] grid-cols-1 gap-x-[38px] gap-y-[38px] lg:grid-cols-2">
                @foreach (($data['items'] ?? []) as $publication)
                    @include('public.research.partials.publication-card', ['publication' => $publication, 'locale' => $locale])
                @endforeach
            </div>
        </div>
    </section>
@endsection
