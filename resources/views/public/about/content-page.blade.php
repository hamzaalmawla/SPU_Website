@extends('layouts.public')

@section('content')
    <div class="bg-slate-50 font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        @if ($page->slug === 'history')
            <section class="bg-white py-24 font-hacen">
                <div class="container mx-auto">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $page->title }}</h2>
                    <div class="history-vision-grid">
                        <div class="history-vision-image reveal reveal-left">
                            <img src="{{ $page->heroImage }}" alt="{{ $page->title }}" class="h-full w-full object-cover">
                        </div>
                        <div class="reveal reveal-right">
                            <blockquote class="history-quote mb-12 text-2xl font-black leading-relaxed text-slate-950">{{ $page->summary }}</blockquote>
                            <div class="grid gap-6 text-base leading-relaxed text-slate-700">
                                @foreach ($page->sections as $section)
                                    @if (! empty($section['body']))
                                        <p>{{ $section['body'] }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-section py-24 font-hacen">
                <div class="container mx-auto px-6">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $locale === 'ar' ? 'محطات رئيسية' : 'Key Milestones' }}</h2>
                    <div class="history-timeline">
                        @foreach (collect($page->sections)->flatMap(fn ($section) => $section['items'] ?? []) as $point)
                            @php([$year, $text] = array_pad(explode(':', (string) $point, 2), 2, ''))
                            <article class="history-timeline-item reveal {{ $loop->odd ? 'reveal-left' : 'reveal-right' }}">
                                <span class="history-timeline-dot" aria-hidden="true"></span>
                                <div class="history-timeline-content">
                                    <p class="mb-3 text-4xl font-black leading-none text-spu-blue/35" translate="no">{{ trim($year) }}</p>
                                    <h3 class="text-xl font-black leading-tight text-spu-blue">{{ trim($text) }}</h3>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto">
                    <p class="mx-auto mb-12 max-w-3xl text-center text-slate-700">{{ $page->summary }}</p>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
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
                    <h2 class="reveal reveal-up mb-12 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $locale === 'ar' ? 'الأعمدة الاستراتيجية' : 'Strategic Pillars' }}</h2>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
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
