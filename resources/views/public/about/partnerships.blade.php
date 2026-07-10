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
                <div class="filter-bar">
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active">{{ __('public.all') }}</button>
                        <button type="button" class="filter-btn">{{ __('public.academic') }}</button>
                        <button type="button" class="filter-btn">{{ __('public.research_filter') }}</button>
                        <button type="button" class="filter-btn">{{ __('public.clinical') }}</button>
                    </div>
                    <div class="search-input-wrapper">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="search" class="search-input" placeholder="{{ $locale === 'ar' ? 'ابحث عن شريك' : 'Search partners' }}" aria-label="{{ $locale === 'ar' ? 'ابحث عن شريك' : 'Search partners' }}">
                    </div>
                </div>

                <div class="partner-grid">
                @foreach ($partnerships as $partnership)
                    <article class="partner-card reveal reveal-up">
                        <div class="partner-logo-box">@if ($partnership->logo)<img src="{{ $partnership->logo }}" alt="{{ $partnership->name }}">@endif</div>
                        <div class="partner-card-body">
                            <div class="partner-meta"><span class="partner-status active">{{ $partnership->status }}</span><span class="partner-established">{{ $partnership->establishedLabel }}</span></div>
                            <h2 class="partner-title">{{ $partnership->name }}</h2>
                            <p class="partner-category">{{ $partnership->category }}</p>
                            <p class="partner-desc">{{ $partnership->description }}</p>
                            @if ($partnership->websiteUrl)<a href="{{ $partnership->websiteUrl }}" class="partner-link" target="_blank" rel="noreferrer">{{ $locale === 'ar' ? 'عرض التفاصيل' : 'View Details' }} <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true"></a>@endif
                        </div>
                    </article>
                @endforeach
                </div>

                @if ($partnerships->count() > 6)
                    <div class="text-center reveal reveal-up">
                        <button type="button" class="load-more-btn">
                            <span>{{ $locale === 'ar' ? 'تحميل المزيد' : 'Load More' }}</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                    </div>
                @endif

                <div class="propose-section reveal reveal-up">
                    <svg class="propose-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <h3 class="propose-title">{{ $locale === 'ar' ? 'هل ترغب بالشراكة مع الجامعة؟' : 'Want to partner with SPU?' }}</h3>
                    <p class="propose-text">{{ $locale === 'ar' ? 'تواصل معنا لبدء تعاون أكاديمي أو بحثي بأثر مشترك.' : 'Contact us to start an academic or research collaboration with shared impact.' }}</p>
                    <a href="/{{ $locale }}/about/directorates" class="propose-btn">{{ $locale === 'ar' ? 'إرسال مقترح' : 'Submit Proposal' }}</a>
                </div>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
