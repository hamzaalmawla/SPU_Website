@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section id="directorates-section" class="relative bg-white font-hacen">
            <div class="directorates-content-wrapper relative z-10">
                <div class="directorates-list">
                @foreach ($directorates as $index => $directorate)
                    <a href="/{{ $locale }}/about/directorates/{{ $directorate->slug }}" class="directorate-item reveal reveal-up">
                        <div class="directorate-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="directorate-info">
                            <h2 class="directorate-title">{{ $directorate->title }}</h2>
                            @if ($directorate->location)<div class="directorate-category">{{ $directorate->location }}</div>@endif
                            <p class="directorate-desc">{{ $directorate->summary }}</p>
                        </div>
                    </a>
                @endforeach
                </div>

                <div class="correspondence-box reveal reveal-up reveal-delay-2">
                    <div class="text-spu-red">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="correspondence-title">{{ $locale === 'ar' ? 'المراسلات الإدارية' : 'Administrative Correspondence' }}</h3>
                        <p class="correspondence-text">{{ $page->summary }}</p>
                    </div>
                </div>

                <a href="/{{ $locale }}/about/directorates/staff" class="staff-dir-nav-banner reveal reveal-up reveal-delay-3">
                    <div class="staff-dir-nav-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="staff-dir-nav-text">
                        <span class="staff-dir-nav-label">{{ $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory' }}</span>
                        <span class="staff-dir-nav-sub">{{ $locale === 'ar' ? 'استعرض جميع أعضاء الهيئة الأكاديمية في الجامعة السورية الخاصة' : 'Browse all SPU academic staff members' }}</span>
                    </div>
                    <div class="staff-dir-nav-arrow" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </div>
                </a>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
