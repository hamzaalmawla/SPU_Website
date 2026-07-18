@extends('layouts.public')

@section('content')
    <div class="bg-slate-50 font-hacen text-spu-blue">
        @if ($page->slug === 'history')
            @php
                $history = $page->sections;
            @endphp

            <section class="history-subpage-hero relative flex items-center justify-center overflow-hidden pt-28 font-hacen">
                <img src="{{ $page->heroImage ?: '/images/about-hero-2.webp' }}" alt="{{ $page->title }}" class="absolute inset-0 h-full w-full object-cover">
                <div class="container relative z-10 mx-auto px-6 text-center text-white">
                    <nav class="mb-6 flex items-center justify-center gap-3 text-xs font-bold text-white/85" aria-label="Breadcrumb">
                        <a href="/{{ $locale }}" class="transition hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                        <span aria-hidden="true">›</span>
                        <a href="/{{ $locale }}/about" class="transition hover:text-white">{{ $locale === 'ar' ? 'عن الجامعة' : 'About' }}</a>
                        <span aria-hidden="true">›</span>
                        <span>{{ $page->title }}</span>
                    </nav>
                    <h1 class="text-4xl font-black leading-tight text-white md:text-5xl">{{ $page->title }}</h1>
                </div>
            </section>

            <section class="bg-white py-24 font-hacen">
                <div class="container mx-auto">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $history['foundingTitle'] }}</h2>
                    <div class="history-vision-grid">
                        <div class="history-vision-image reveal reveal-left">
                            <img src="{{ $page->contentImage ?: '/images/uni-main-place.JPG' }}" alt="{{ $history['foundingTitle'] }}" class="h-full w-full object-cover">
                        </div>
                        <div class="reveal reveal-right">
                            <blockquote class="history-quote mb-12 text-2xl font-black leading-relaxed text-slate-950">{{ $history['quote'] }}</blockquote>
                            <div class="grid gap-6 text-base leading-relaxed text-slate-700">
                                @foreach ($history['body'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-section py-24 font-hacen">
                <div class="container mx-auto px-6">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $history['timelineTitle'] }}</h2>
                    <div class="history-timeline">
                        @foreach ($history['timeline'] as $point)
                            <article class="history-timeline-item reveal {{ $loop->odd ? 'reveal-left' : 'reveal-right' }}">
                                <span class="history-timeline-dot" aria-hidden="true"></span>
                                <div class="history-timeline-content">
                                    <p class="mb-3 text-4xl font-black leading-none text-spu-blue/35" translate="no">{{ $point['year'] }}</p>
                                    <h3 class="text-xl font-black leading-tight text-spu-blue">{{ $point['title'] }}</h3>
                                    <p class="mt-5 text-sm leading-relaxed text-slate-700">{{ $point['body'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-white py-16 font-hacen">
                <div class="container mx-auto max-w-6xl px-6">
                    @foreach ($history['narratives'] as $item)
                        <article class="history-row reveal reveal-up">
                            <div>
                                <h2 class="text-xl font-black text-spu-blue">{{ $item['title'] }}</h2>
                                <p class="mt-2 text-xs font-black uppercase tracking-widest text-spu-red">{{ $item['eyebrow'] }}</p>
                            </div>
                            <p class="max-w-3xl text-lg leading-relaxed text-slate-700">{{ $item['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto px-6">
                    <div class="history-legacy reveal reveal-up mx-auto max-w-4xl">
                        <h2 class="text-3xl font-black text-spu-blue">{{ $history['legacyTitle'] }}</h2>
                        <p class="mt-5 text-base leading-relaxed text-slate-700">{{ $history['legacyBody'] }}</p>
                    </div>
                </div>
            </section>

            @include('public.about.partials.navigation-section', ['locale' => $locale])
        @elseif (in_array($page->slug, ['quality-policy', 'ethical-charter', 'organizational-structure', 'accreditation', 'why-spu'], true))
            @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto px-6">
                    @if ($page->badge !== '')
                        <p class="mb-4 text-center text-xs font-black uppercase tracking-[0.2em] text-spu-red">{{ $page->badge }}</p>
                    @endif
                    <div class="mx-auto mb-14 grid max-w-4xl gap-5 text-base font-bold leading-8 text-slate-700">
                        @forelse ($page->intro as $paragraph)<p>{{ $paragraph }}</p>@empty<p>{{ $page->summary }}</p>@endforelse
                    </div>
                    @if ($page->stats !== [])
                        <div class="cms-grid-stats mb-16 gap-4 rounded-2xl bg-spu-blue p-6 text-white">
                            @foreach ($page->stats as $stat)
                                <div class="text-center"><strong class="block text-3xl">{{ $stat['value'] ?? '' }}</strong><span class="text-xs font-bold uppercase tracking-wider text-white/70">{{ $stat['label'] ?? '' }}</span></div>
                            @endforeach
                        </div>
                    @endif
                    <div class="cms-grid-cards gap-6">
                        @foreach ($page->sections as $section)
                            <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                                <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-spu-blue/5">
                                    <img src="/images/icon-check-circle-outline.svg" alt="" class="h-7 w-7">
                                </div>
                                <h2 class="mb-3 text-xl font-black text-spu-blue">{{ $section['title'] ?? '' }}</h2>
                                @if (! empty($section['body']))
                                    <p class="text-sm font-bold leading-7 text-slate-700">{{ $section['body'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            @include('public.about.partials.navigation-section', ['locale' => $locale])
        @else
            @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto">
                    <p class="mx-auto mb-12 max-w-3xl text-center text-slate-700">{{ $page->summary }}</p>
                    <div class="cms-grid-cards gap-6">
                        @foreach ($page->sections as $section)
                            <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm">
                                <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-spu-blue/5">
                                    <img src="/images/icon-check-circle-outline.svg" alt="" class="h-7 w-7">
                                </div>
                                <h2 class="mb-3 text-xl font-black text-spu-blue">{{ $section['title'] ?? '' }}</h2>
                                @if (! empty($section['body']))
                                    <p class="text-slate-700">{{ $section['body'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-section py-24 font-hacen">
                <div class="container mx-auto">
                    <h2 class="reveal reveal-up mb-12 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ __('public.strategic_pillars') }}</h2>
                    <div class="cms-grid-compact gap-6">
                        @foreach ($page->sections as $section)
                            <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                                <h3 class="mb-3 text-lg font-black text-spu-blue">{{ $section['title'] ?? '' }}</h3>
                                @if (! empty($section['body']))
                                    <p class="text-slate-700">{{ $section['body'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection
