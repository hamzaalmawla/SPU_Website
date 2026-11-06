@extends('layouts.public')

@php
    $isArabic = $locale === 'ar';
    $manager = $directorate->manager;
    $hasContact = filled($directorate->email) || filled($directorate->location);
@endphp

@section('content')
    <div class="bg-white font-hacen text-spu-blue overflow-x-hidden">
        @include('public.about.partials.hero', ['title' => $directorate->title, 'summary' => $directorate->summary, 'image' => null])

        <section class="dir-detail-section bg-[#faf9fb] font-hacen overflow-x-hidden" aria-labelledby="directorate-overview-heading">
            <div class="container mx-auto max-w-6xl px-6">
                <div class="grid gap-10 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <div class="dir-overview-head reveal reveal-up">
                            <span class="dir-overview-icon" aria-hidden="true">
                                <img src="{{ $directorate->icon ?? '/images/icon-university-outline.svg' }}" alt="" class="h-7 w-7">
                            </span>
                            <div>
                                <p class="dir-eyebrow">{{ $isArabic ? 'مديرية مركزية' : 'Central Directorate' }}</p>
                                <h2 id="directorate-overview-heading" class="dir-section-title">{{ $isArabic ? 'نظرة عامة' : 'Overview' }}</h2>
                            </div>
                        </div>
                        <x-public.rich-text class="dir-overview-text reveal reveal-up reveal-delay-1" :text="$directorate->description" />

                        @if ($directorate->taskGroups === [] && $directorate->services !== [])
                            <h2 class="dir-section-title dir-section-gap reveal reveal-up">{{ $isArabic ? 'الخدمات الرئيسية' : 'Key Services' }}</h2>
                            <div class="dir-service-list">
                                @foreach ($directorate->services as $service)
                                    <article class="dir-service-item reveal reveal-up">
                                        <span class="dir-service-icon" aria-hidden="true"><img src="/images/icon-check-circle-outline.svg" alt="" class="h-4 w-4"></span>
                                        <h3 class="card-title dir-service-name" dir="auto">{{ $service }}</h3>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <aside class="lg:col-span-2 dir-aside" aria-label="{{ $manager ? ($isArabic ? 'مدير المديرية' : 'Directorate manager') : ($isArabic ? 'معلومات التواصل' : 'Contact information') }}">
                        @if ($manager)
                            <div class="dir-manager-card reveal reveal-right">
                                <p class="dir-manager-role">{{ $isArabic ? 'مدير المديرية' : 'Directorate Manager' }}</p>
                                <div class="dir-manager-head">
                                    @if ($manager->photo)
                                        <img src="{{ $manager->photo }}" alt="{{ $manager->name }}" class="dir-manager-photo" loading="lazy" decoding="async">
                                    @else
                                        <span class="dir-manager-photo dir-manager-initial" aria-hidden="true">
                                            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a7 7 0 0 1 14 0v1"></path></svg>
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <h2 class="dir-manager-name" dir="auto">{{ $manager->name }}</h2>
                                    </div>
                                </div>

                                @if ($manager->education !== [])
                                    <h3 class="dir-manager-heading">{{ $isArabic ? 'المؤهلات العلمية' : 'Education' }}</h3>
                                    <ul class="dir-manager-education">
                                        @foreach ($manager->education as $qualification)
                                            <li><span class="dir-manager-degree" dir="auto">{{ $qualification }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($manager->email || $manager->phone)
                                    <h3 class="dir-manager-heading">{{ $isArabic ? 'التواصل' : 'Contact' }}</h3>
                                    <ul class="dir-info-list">
                                        @if ($manager->email)
                                            <li>
                                                <span class="dir-info-label">{{ $isArabic ? 'البريد الإلكتروني' : 'Email' }}</span>
                                                <a href="mailto:{{ $manager->email }}" class="dir-info-value dir-info-link" dir="ltr">{{ $manager->email }}</a>
                                            </li>
                                        @endif
                                        @if ($manager->phone)
                                            <li>
                                                <span class="dir-info-label">{{ $isArabic ? 'الهاتف' : 'Phone' }}</span>
                                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', strtr($manager->phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) }}" class="dir-info-value dir-info-link" dir="ltr">{{ $manager->phone }}</a>
                                            </li>
                                        @endif
                                    </ul>
                                @endif
                            </div>
                        @endif

                        @if ($hasContact || ! $manager)
                            <div class="dir-info-card reveal reveal-right {{ $manager ? 'dir-info-card-compact' : '' }}">
                                @unless ($manager)
                                    <div class="dir-info-icon" aria-hidden="true">
                                        <img src="{{ $directorate->icon ?? '/images/icon-university-outline.svg' }}" alt="" class="h-6 w-6">
                                    </div>
                                @endunless
                                <h2 class="dir-info-title">{{ $isArabic ? 'معلومات التواصل' : 'Contact Information' }}</h2>
                                <ul class="dir-info-list">
                                    @if ($directorate->email)
                                        <li>
                                            <span class="dir-info-label">{{ $isArabic ? 'البريد الإلكتروني' : 'Email' }}</span>
                                            <a href="mailto:{{ $directorate->email }}" class="dir-info-value dir-info-link" dir="ltr">{{ $directorate->email }}</a>
                                        </li>
                                    @endif
                                    @if ($directorate->location)
                                        <li>
                                            <span class="dir-info-label">{{ $isArabic ? 'الموقع' : 'Location' }}</span>
                                            <span class="dir-info-value">{{ $directorate->location }}</span>
                                        </li>
                                    @endif
                                </ul>
                                @unless ($manager)
                                    <a href="/{{ $locale }}/about/directorates" class="dir-btn dir-btn-primary dir-btn-block">{{ $isArabic ? 'العودة إلى المديريات' : 'Back to Directorates' }}</a>
                                @endunless
                            </div>
                        @endif
                    </aside>
                </div>
            </div>
        </section>

        @foreach ($directorate->taskGroups as $groupIndex => $group)
            @php $dark = $groupIndex % 2 === 1; @endphp
            <section class="dir-tasks-section {{ $dark ? 'dir-tasks-dark' : '' }}" aria-labelledby="directorate-tasks-{{ $groupIndex }}">
                <div class="container mx-auto max-w-6xl px-6">
                    <div class="dir-tasks-head reveal reveal-up">
                        <p class="dir-eyebrow">{{ $isArabic ? 'مهام المديرية' : 'What we do' }}</p>
                        <h2 id="directorate-tasks-{{ $groupIndex }}" class="dir-section-title" dir="auto">{{ $group['title'] }}</h2>
                        @if ($group['summary'] !== '')
                            <p class="dir-tasks-summary" dir="auto">{{ $group['summary'] }}</p>
                        @endif
                    </div>
                    <ol class="dir-task-grid">
                        @foreach ($group['items'] as $task)
                            <li class="dir-task-card reveal reveal-up">
                                <span class="dir-task-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="card-title dir-task-title" dir="auto">{{ $task['title'] }}</h3>
                                @if ($task['body'] !== '')
                                    <p class="dir-task-body" dir="auto">{{ $task['body'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endforeach

        <section class="dir-closing-section" aria-label="{{ $isArabic ? 'روابط وتواصل' : 'Links and contact' }}">
            <div class="container mx-auto max-w-6xl px-6">
                @if (!empty($directorate->links))
                    <h2 class="dir-section-title reveal reveal-up">{{ $isArabic ? 'روابط هامة' : 'Important Links' }}</h2>
                    <div class="dir-link-grid">
                        @foreach ($directorate->links as $link)
                            <a href="{{ $link['url'] ?? '#' }}" class="dir-link-card reveal reveal-up">
                                <span class="dir-service-icon" aria-hidden="true">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </span>
                                <h3 class="card-title dir-service-name" dir="auto">{{ $link['title'] ?? '' }}</h3>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="dir-actions reveal reveal-up">
                    <a href="/{{ $locale }}/contact?topic=directorate#contact-form" class="dir-btn dir-btn-primary">{{ $isArabic ? 'تواصل معنا' : 'Contact Us' }}</a>
                    <a href="/{{ $locale }}/about/directorates" class="dir-btn dir-btn-outline">{{ $isArabic ? 'جميع المديريات' : 'All Directorates' }}</a>
                </div>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
