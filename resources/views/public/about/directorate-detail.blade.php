@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $directorate->title, 'summary' => $directorate->summary, 'image' => null])

        <section class="dir-detail-section bg-[#faf9fb] font-hacen" aria-labelledby="directorate-overview-heading">
            <div class="container mx-auto max-w-6xl px-6">
                <div class="grid gap-10 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <p class="dir-eyebrow reveal reveal-up">{{ $locale === 'ar' ? 'مديرية مركزية' : 'Central Directorate' }}</p>
                        <h2 id="directorate-overview-heading" class="dir-section-title reveal reveal-up">{{ $locale === 'ar' ? 'نظرة عامة' : 'Overview' }}</h2>
                        <p class="dir-overview-text reveal reveal-up reveal-delay-1">{{ $directorate->description }}</p>

                        <h2 class="dir-section-title dir-section-gap reveal reveal-up">{{ $locale === 'ar' ? 'الخدمات الرئيسية' : 'Key Services' }}</h2>
                        <div class="dir-service-list">
                            @foreach ($directorate->services as $service)
                                <article class="dir-service-item reveal reveal-up">
                                    <span class="dir-service-icon" aria-hidden="true"><img src="/images/icon-check-circle-outline.svg" alt="" class="h-4 w-4"></span>
                                    <h3 class="dir-service-name">{{ $service }}</h3>
                                </article>
                            @endforeach
                        </div>

                        @if (!empty($directorate->links))
                            <h2 class="dir-section-title dir-section-gap reveal reveal-up">{{ $locale === 'ar' ? 'روابط هامة' : 'Important Links' }}</h2>
                            <div class="dir-link-grid">
                                @foreach ($directorate->links as $link)
                                    <a href="{{ $link['url'] ?? '#' }}" class="dir-link-card reveal reveal-up">
                                        <span class="dir-service-icon" aria-hidden="true">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </span>
                                        <h3 class="dir-service-name">{{ $link['title'] ?? '' }}</h3>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="dir-actions reveal reveal-up">
                            <a href="/{{ $locale }}/contact?topic=directorate#contact-form" class="dir-btn dir-btn-primary">{{ $locale === 'ar' ? 'تواصل معنا' : 'Contact Us' }}</a>
                            <a href="/{{ $locale }}/about/directorates" class="dir-btn dir-btn-outline">{{ $locale === 'ar' ? 'جميع المديريات' : 'All Directorates' }}</a>
                        </div>
                    </div>

                    <aside class="lg:col-span-2">
                        <div class="dir-info-card reveal reveal-right">
                            <div class="dir-info-icon" aria-hidden="true">
                                <img src="{{ $directorate->icon ?? '/images/icon-university-outline.svg' }}" alt="" class="h-6 w-6">
                            </div>
                            <h2 class="dir-info-title">{{ $locale === 'ar' ? 'معلومات التواصل' : 'Contact Information' }}</h2>
                            <ul class="dir-info-list">
                                @if ($directorate->email)
                                    <li>
                                        <span class="dir-info-label">{{ $locale === 'ar' ? 'البريد الإلكتروني' : 'Email' }}</span>
                                        <a href="mailto:{{ $directorate->email }}" class="dir-info-value dir-info-link">{{ $directorate->email }}</a>
                                    </li>
                                @endif
                                @if ($directorate->location)
                                    <li>
                                        <span class="dir-info-label">{{ $locale === 'ar' ? 'الموقع' : 'Location' }}</span>
                                        <span class="dir-info-value">{{ $directorate->location }}</span>
                                    </li>
                                @endif
                            </ul>
                            <a href="/{{ $locale }}/about/directorates" class="dir-btn dir-btn-primary dir-btn-block">{{ $locale === 'ar' ? 'العودة إلى المديريات' : 'Back to Directorates' }}</a>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
