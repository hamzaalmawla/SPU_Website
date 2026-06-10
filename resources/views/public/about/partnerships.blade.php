@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])
        <section class="core-domains-section">
            <div class="container">
                <h2 class="core-domains-title">{{ $page->headline }}</h2>
                <div class="partner-goals-grid">
                    @foreach ([$page->summary, $locale === 'ar' ? 'تبادل الخبرات وتطوير البرامج الأكاديمية.' : 'Exchange expertise and develop academic programs.', $locale === 'ar' ? 'دعم فرص البحث والدراسات العليا.' : 'Support research and postgraduate opportunities.', $locale === 'ar' ? 'تعزيز الحضور العلمي للجامعة.' : 'Strengthen the university academic presence.'] as $goal)
                        <article class="partner-goal-card reveal reveal-up">
                            <div class="partner-goal-icon"><i class="fa-solid fa-handshake"></i></div>
                            <div class="partner-goal-content">
                                <h3>{{ $locale === 'ar' ? 'مجال تعاون' : 'Collaboration Domain' }}</h3>
                                <p>{{ $goal }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-section py-20 lg:py-24">
            <div class="container">
                <div class="filter-bar">
                    <h2 class="text-3xl font-black text-spu-blue">{{ $locale === 'ar' ? 'الشركاء' : 'Partners' }}</h2>
                    <div class="filter-buttons"><button type="button" class="filter-btn active">{{ $locale === 'ar' ? 'الكل' : 'All' }}</button></div>
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
                            @if ($partnership->websiteUrl)<a href="{{ $partnership->websiteUrl }}" class="partner-link" target="_blank" rel="noreferrer">{{ $locale === 'ar' ? 'عرض التفاصيل' : 'View Details' }}</a>@endif
                        </div>
                    </article>
                @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
