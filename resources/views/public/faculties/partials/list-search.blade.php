{{--
    Search and year filter for a faculty's projects or research page. Both narrow
    only this page's own list (this faculty's projects or publications), work
    together, and keep each other when one changes. Expects $studentListUrl,
    $filters, $filterOptions and $isAr from the subpage view, plus $placeholder,
    $searchLabel and $yearLabel.
--}}
@php
    $activeSearch = (string) ($filters['q'] ?? '');
    $activeYear = (string) ($filters['year'] ?? '');
    $yearOptions = is_array($filterOptions['years'] ?? null) ? $filterOptions['years'] : [];
    $searchId = 'faculty-list-search-'.$page->subpageSlug;
    $yearId = 'faculty-list-year-'.$page->subpageSlug;
@endphp

<form method="GET" action="{{ $studentListUrl }}" role="search" class="mb-8 flex flex-wrap items-center gap-4">
    <label for="{{ $searchId }}" class="sr-only">{{ $searchLabel }}</label>
    <input id="{{ $searchId }}" type="search" name="q" value="{{ $activeSearch }}" placeholder="{{ $placeholder }}" class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[280px]">
    @if ($yearOptions !== [])
        <label for="{{ $yearId }}" class="sr-only">{{ $yearLabel }}</label>
        <select id="{{ $yearId }}" name="year" data-autosubmit class="h-9 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[150px]">
            <option value="">{{ $isAr ? 'كل السنوات' : 'All years' }}</option>
            @foreach ($yearOptions as $year)
                <option value="{{ $year }}" @selected($activeYear === (string) $year)>{{ $year }}</option>
            @endforeach
        </select>
    @endif
    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-[6px] bg-spu-red px-4 text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-spu-blue">{{ $isAr ? 'بحث' : 'Search' }}</button>
    @if ($activeSearch !== '' || $activeYear !== '')
        <a href="{{ $studentListUrl }}" class="inline-flex h-9 items-center justify-center rounded-[6px] border border-slate-200 px-4 text-[11px] font-bold uppercase tracking-[0.08em] text-spu-blue transition-colors hover:border-spu-blue">{{ $isAr ? 'الكل' : 'All' }}</a>
    @endif
</form>
