@php
    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $activeFilters = is_array($data['activeFilters'] ?? null) ? $data['activeFilters'] : [];
    $currentPage = (int) ($pagination['current_page'] ?? 1);
    $totalPages = (int) ($pagination['total_pages'] ?? 1);
    $firstPage = max(1, $currentPage - 2);
    $lastPage = min($totalPages, $currentPage + 2);
    $cleanFilters = array_filter(
        $activeFilters,
        static fn (mixed $value, string|int $key): bool => $key !== 'page' && is_scalar($value) && (string) $value !== '',
        ARRAY_FILTER_USE_BOTH,
    );
    $baseQuery = is_array($baseQuery ?? null) ? $baseQuery : [];
    $pageUrl = static function (int $pageNumber) use ($basePath, $baseQuery, $cleanFilters): string {
        $query = [...$baseQuery, ...$cleanFilters, 'page' => $pageNumber];

        if ($pageNumber === 1) {
            unset($query['page']);
        }

        return $basePath.($query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    };
@endphp

@if ($totalPages > 1)
    <nav class="mt-12 flex flex-wrap items-center justify-center gap-2" aria-label="{{ $locale === 'ar' ? 'ترقيم صفحات النتائج' : 'Results pagination' }}">
        @if ($currentPage > 1)
            <a href="{{ $pageUrl($currentPage - 1) }}" rel="prev" aria-label="{{ $locale === 'ar' ? 'الصفحة السابقة' : 'Previous page' }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rotate-180 rtl:rotate-0">
            </a>
        @endif

        @for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++)
            <a href="{{ $pageUrl($pageNumber) }}" @if ($pageNumber === $currentPage) aria-current="page" @endif class="inline-flex h-10 min-w-10 items-center justify-center rounded-[6px] border px-3 text-xs font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $pageNumber === $currentPage ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue hover:border-spu-blue' }}">
                {{ $pageNumber }}
            </a>
        @endfor

        @if ($currentPage < $totalPages)
            <a href="{{ $pageUrl($currentPage + 1) }}" rel="next" aria-label="{{ $locale === 'ar' ? 'الصفحة التالية' : 'Next page' }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-spu-blue transition hover:border-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
                <img src="/images/icon-arrow-right-outline.svg" alt="" aria-hidden="true" class="h-3 w-3 rtl:rotate-180">
            </a>
        @endif
    </nav>
@endif
