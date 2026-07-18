@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])
        <section class="core-domains-section">
            <div class="container">
                <div class="partner-goals-grid">
                @foreach ([
                        ['icon' => '+', 'title' => __('public.experience_exchange'), 'text' => __('public.experience_exchange_desc')],
                        ['icon' => '◇', 'title' => __('public.faculty_development'), 'text' => __('public.faculty_development_desc')],
                        ['icon' => '✓', 'title' => __('public.postgraduate_pathways'), 'text' => __('public.postgraduate_pathways_desc')],
                        ['icon' => '○', 'title' => __('public.community_alignment'), 'text' => __('public.community_alignment_desc')],
                    ] as $goal)
                        <article class="partner-goal-card reveal reveal-up">
                            <div class="partner-goal-icon" aria-hidden="true">{{ $goal['icon'] }}</div>
                            <div class="partner-goal-content">
                                <h3>{{ $goal['title'] }}</h3>
                                <p>{{ $goal['text'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-white pb-20 lg:pb-24">
            <div class="container">
                @php
                    $partnershipUrl = static function (?string $category = null, ?string $query = null, ?int $pageNumber = null) use ($locale): string {
                        $params = ['locale' => $locale];
                        if (is_string($category) && $category !== '') $params['category'] = $category;
                        if (is_string($query) && $query !== '') $params['q'] = $query;
                        if (is_int($pageNumber) && $pageNumber > 1) $params['page'] = $pageNumber;

                        return route('public.about.partnerships', $params);
                    };
                @endphp
                <div class="filter-bar">
                    <nav class="filter-buttons" aria-label="{{ $locale === 'ar' ? 'تصفية الشراكات حسب الفئة' : 'Filter partnerships by category' }}">
                        <a href="{{ $partnershipUrl(null, $directory->query) }}" class="filter-btn {{ $directory->activeCategory === '' ? 'active' : '' }}" @if ($directory->activeCategory === '') aria-current="page" @endif>{{ __('public.all') }}</a>
                        @foreach ($directory->categories as $category)
                            <a href="{{ $partnershipUrl($category['key'], $directory->query) }}" class="filter-btn {{ $directory->activeCategory === $category['key'] ? 'active' : '' }}" @if ($directory->activeCategory === $category['key']) aria-current="page" @endif>{{ $category['label'] }}</a>
                        @endforeach
                    </nav>
                    <form method="GET" action="{{ route('public.about.partnerships', ['locale' => $locale]) }}" class="search-input-wrapper" role="search">
                        @if ($directory->activeCategory !== '')
                            <input type="hidden" name="category" value="{{ $directory->activeCategory }}">
                        @endif
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <label for="partnership-search" class="sr-only">{{ $locale === 'ar' ? 'ابحث عن شريك' : 'Search partners' }}</label>
                        <input id="partnership-search" name="q" type="search" value="{{ $directory->query }}" class="search-input" placeholder="{{ $locale === 'ar' ? 'ابحث عن شريك' : 'Search partners' }}">
                        <button type="submit" class="search-submit">{{ $locale === 'ar' ? 'بحث' : 'Search' }}</button>
                    </form>
                </div>

                <p class="partner-results-status" role="status">
                    {{ $locale === 'ar' ? 'عدد النتائج: '.$directory->totalItems : $directory->totalItems.' partnership'.($directory->totalItems === 1 ? '' : 's').' found' }}
                </p>

                <div id="partnership-results" class="partner-grid">
                @forelse ($directory->items as $partnership)
                    <article class="partner-card reveal reveal-up">
                        <div class="partner-logo-box">@if ($partnership->logo)<img src="{{ $partnership->logo }}" alt="{{ $partnership->name }}">@endif</div>
                        <div class="partner-card-body">
                            <div class="partner-meta"><span class="partner-status {{ $partnership->statusKey }}">{{ $partnership->status }}</span><span class="partner-established">{{ $partnership->establishedLabel }}</span></div>
                            <h2 class="partner-title">{{ $partnership->name }}</h2>
                            <p class="partner-category">{{ $partnership->category }}</p>
                            <p class="partner-desc">{{ $partnership->description }}</p>
                            @if ($partnership->scope)<p class="partner-scope"><strong>{{ $locale === 'ar' ? 'النطاق:' : 'Scope:' }}</strong> {{ $partnership->scope }}</p>@endif
                            @if ($partnership->websiteUrl)<a href="{{ $partnership->websiteUrl }}" class="partner-link" target="_blank" rel="noopener noreferrer" aria-label="{{ ($locale === 'ar' ? 'عرض تفاصيل ' : 'View details for ').$partnership->name.($locale === 'ar' ? ' في نافذة جديدة' : ' in a new window') }}">{{ $locale === 'ar' ? 'عرض التفاصيل' : 'View Details' }} <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true"></a>@endif
                        </div>
                    </article>
                @empty
                    <div class="partner-empty-state" role="status">
                        <h2>{{ $locale === 'ar' ? 'لا توجد شراكات مطابقة' : 'No matching partnerships' }}</h2>
                        <p>{{ $locale === 'ar' ? 'جرّب تغيير الفئة أو عبارة البحث.' : 'Try another category or search term.' }}</p>
                        <a href="{{ $partnershipUrl() }}" class="propose-btn">{{ $locale === 'ar' ? 'إعادة ضبط البحث' : 'Reset search' }}</a>
                    </div>
                @endforelse
                </div>

                @if ($directory->totalPages > 1)
                    <nav class="staff-pagination" aria-label="{{ $locale === 'ar' ? 'صفحات الشراكات' : 'Partnership pages' }}">
                        @for ($pageNumber = 1; $pageNumber <= $directory->totalPages; $pageNumber++)
                            <a href="{{ $partnershipUrl($directory->activeCategory, $directory->query, $pageNumber) }}" class="pag-btn {{ $pageNumber === $directory->currentPage ? 'active' : '' }}" @if ($pageNumber === $directory->currentPage) aria-current="page" @endif aria-label="{{ ($locale === 'ar' ? 'الصفحة ' : 'Page ').$pageNumber }}">{{ $pageNumber }}</a>
                        @endfor
                    </nav>
                @endif

                <div class="propose-section reveal reveal-up">
                    <svg class="propose-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <h3 class="propose-title">{{ $locale === 'ar' ? 'هل ترغب بالشراكة مع الجامعة؟' : 'Want to partner with SPU?' }}</h3>
                    <p class="propose-text">{{ $locale === 'ar' ? 'تواصل معنا لبدء تعاون أكاديمي أو بحثي بأثر مشترك.' : 'Contact us to start an academic or research collaboration with shared impact.' }}</p>
                    <a href="/{{ $locale }}/contact?topic=partnership#contact-form" class="propose-btn">{{ $locale === 'ar' ? 'إرسال مقترح' : 'Submit Proposal' }}</a>
                </div>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
