@props([
    'currentPage',
    'totalPages',
    'pageUrl',
    'locale' => 'ar',
    'label' => null,
    'class' => 'mt-12',
])

@php
    $currentPage = max(1, (int) $currentPage);
    $totalPages = max(1, (int) $totalPages);
    $isAr = $locale === 'ar';
    $pages = array_values(array_unique(array_filter([
        1,
        $currentPage - 1,
        $currentPage,
        $currentPage + 1,
        $totalPages,
    ], fn (int $page): bool => $page >= 1 && $page <= $totalPages)));
    sort($pages);
@endphp

@if ($totalPages > 1)
    <nav {{ $attributes->class([$class, 'flex items-center justify-center gap-2']) }} aria-label="{{ $label ?: ($isAr ? 'ترقيم صفحات النتائج' : 'Results pagination') }}">
        @if ($currentPage > 1)
            <a href="{{ $pageUrl($currentPage - 1) }}" rel="prev" aria-label="{{ $isAr ? 'الصفحة السابقة' : 'Previous page' }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rotate-180 rtl:rotate-0">
            </a>
        @endif

        <span class="inline-flex h-11 items-center rounded-[6px] border border-spu-red bg-spu-red px-4 text-xs font-bold text-white sm:hidden">
            {{ $isAr ? 'صفحة' : 'Page' }} {{ $currentPage }} {{ $isAr ? 'من' : 'of' }} {{ $totalPages }}
        </span>
        <span class="sr-only">{{ $currentPage }} / {{ $totalPages }}</span>

        <div class="hidden items-center gap-2 sm:flex">
            @php($previousPage = null)
            @foreach ($pages as $pageNumber)
                @if ($previousPage !== null && $pageNumber > $previousPage + 1)
                    <span class="inline-flex h-11 min-w-7 items-center justify-center text-sm font-bold text-slate-400" aria-hidden="true">...</span>
                @endif

                @if ($pageNumber === $currentPage)
                    <span aria-current="page" aria-label="{{ ($isAr ? 'الصفحة الحالية ' : 'Current page ').$pageNumber }}" class="inline-flex h-11 min-w-11 items-center justify-center rounded-[6px] border border-spu-red bg-spu-red px-3 text-xs font-bold text-white">{{ $pageNumber }}</span>
                @else
                    <a href="{{ $pageUrl($pageNumber) }}" aria-label="{{ ($isAr ? 'الصفحة ' : 'Page ').$pageNumber }}" class="inline-flex h-11 min-w-11 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-bold text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">{{ $pageNumber }}</a>
                @endif
                @php($previousPage = $pageNumber)
            @endforeach
        </div>

        @if ($currentPage < $totalPages)
            <a href="{{ $pageUrl($currentPage + 1) }}" rel="next" aria-label="{{ $isAr ? 'الصفحة التالية' : 'Next page' }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rtl:rotate-180">
            </a>
        @endif
    </nav>
@endif
