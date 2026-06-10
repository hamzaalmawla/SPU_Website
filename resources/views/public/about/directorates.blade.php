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
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <h3 class="correspondence-title">{{ $locale === 'ar' ? 'المراسلات الإدارية' : 'Administrative Correspondence' }}</h3>
                        <p class="correspondence-text">{{ $page->summary }}</p>
                    </div>
                </div>

                <a href="/{{ $locale }}/about/directorates/staff" class="staff-dir-nav-banner reveal reveal-up reveal-delay-3">
                    <div class="staff-dir-nav-text">
                        <span class="staff-dir-nav-label">{{ $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory' }}</span>
                        <span class="staff-dir-nav-sub">{{ $locale === 'ar' ? 'استعرض جميع أعضاء الهيئة الأكاديمية في الجامعة السورية الخاصة' : 'Browse all SPU academic staff members' }}</span>
                    </div>
                    <span aria-hidden="true">›</span>
                </a>
            </div>
        </section>
    </div>
@endsection
