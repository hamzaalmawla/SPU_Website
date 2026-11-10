@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section id="directorates-section" class="relative bg-white font-hacen">
            <div class="directorates-content-wrapper relative z-10">
                <div class="dirs-intro reveal reveal-up">
                    <p class="dir-eyebrow">{{ $locale === 'ar' ? 'المديريات المركزية' : 'Central Directorates' }}</p>
                    <p class="dirs-count">
                        {{ $locale === 'ar' ? 'عدد المديريات: ' : 'Directorates: ' }}<span>{{ $directorates->count() }}</span>
                    </p>
                </div>

                <ul class="dirs-grid" role="list">
                    @foreach ($directorates as $index => $directorate)
                        <li class="reveal reveal-up">
                            <a href="/{{ $locale }}/about/directorates/{{ $directorate->slug }}" class="dirs-card">
                                <span class="dirs-card-top">
                                    <span class="dirs-card-icon" aria-hidden="true">
                                        <img src="{{ $directorate->icon ?? '/images/icon-university-outline.svg' }}" alt="" class="h-7 w-7">
                                    </span>
                                    <span class="dirs-card-number" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                </span>
                                <h2 class="card-title dirs-card-title" dir="auto">{{ $directorate->title }}</h2>
                                @if (filled($directorate->summary))
                                    <p class="dirs-card-summary" dir="auto">{{ $directorate->summary }}</p>
                                @endif
                                <span class="dirs-card-footer">
                                    @if ($directorate->manager)
                                        <span class="dirs-card-manager">
                                            @if ($directorate->manager->photo)
                                                <img src="{{ $directorate->manager->photo }}" alt="" class="dirs-card-avatar" loading="lazy" decoding="async">
                                            @else
                                                <span class="dirs-card-avatar dirs-card-avatar-empty" aria-hidden="true">
                                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a7 7 0 0 1 14 0v1"></path></svg>
                                                </span>
                                            @endif
                                            <span class="min-w-0">
                                                <span class="dirs-card-manager-role">{{ $locale === 'ar' ? 'مدير المديرية' : 'Directorate Manager' }}</span>
                                                <span class="dirs-card-manager-name" dir="auto">{{ $directorate->manager->name }}</span>
                                            </span>
                                        </span>
                                    @else
                                        <span class="dirs-card-more">{{ $locale === 'ar' ? 'صفحة المديرية' : 'Directorate page' }}</span>
                                    @endif
                                    <span class="dirs-card-arrow" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

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
                        <x-public.rich-text class="correspondence-text" :text="$locale === 'ar' ? 'تُوجّه المراسلات المتعلقة بكل مديرية عبر بيانات التواصل المنشورة في صفحتها أو من خلال نموذج التواصل العام.' : 'Correspondence for each directorate can be sent through its published contact details or the university contact form.'" />
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
                        <span class="staff-dir-nav-label">{{ $locale === 'ar' ? 'دليل الهيئة التدريسية' : 'Teaching Staff Directory' }}</span>
                        <span class="staff-dir-nav-sub">{{ $locale === 'ar' ? 'استعرض جميع أعضاء الهيئة التدريسية في الجامعة السورية الخاصة' : 'Browse all SPU teaching staff members' }}</span>
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
