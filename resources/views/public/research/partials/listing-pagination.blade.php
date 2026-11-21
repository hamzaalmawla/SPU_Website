@php
    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $activeFilters = is_array($data['activeFilters'] ?? null) ? $data['activeFilters'] : [];
    $currentPage = (int) ($pagination['current_page'] ?? 1);
    $totalPages = (int) ($pagination['total_pages'] ?? 1);
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

<x-public.pagination :current-page="$currentPage" :total-pages="$totalPages" :page-url="$pageUrl" :locale="$locale" />
