@extends('layouts.public')

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'ar' ? 'الرئيسية' : 'Home', 'item' => url('/'.$locale)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $locale === 'ar' ? 'عن الجامعة' : 'About', 'item' => url('/'.$locale.'/about')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $page->title, 'item' => url('/'.$locale.'/about/vision-mission')],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        <section class="vision-subpage-hero relative flex items-center justify-center overflow-hidden pt-28">
            <img src="{{ $page->heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="container relative z-10 mx-auto px-6 text-center text-white">
                <nav class="mb-6 text-xs font-bold text-white/85" aria-label="{{ $locale === 'ar' ? 'مسار التنقل' : 'Breadcrumb' }}">
                    <ol class="flex flex-wrap items-center justify-center gap-3">
                        <li><a href="/{{ $locale }}" class="transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a></li>
                        <li aria-hidden="true">›</li>
                        <li><a href="/{{ $locale }}/about" class="transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">{{ $locale === 'ar' ? 'عن الجامعة' : 'About' }}</a></li>
                        <li aria-hidden="true">›</li>
                        <li aria-current="page">{{ $page->title }}</li>
                    </ol>
                </nav>
                <h1 class="text-4xl font-black leading-tight text-white md:text-5xl">{{ $page->title }}</h1>
            </div>
        </section>

        <section class="bg-white py-20">
            <div class="container mx-auto px-6">
                <p class="mx-auto max-w-4xl text-center text-base font-bold leading-8 text-slate-700">{{ $page->summary }}</p>
                <h2 class="sr-only">{{ $page->cardsTitle }}</h2>

                <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach ($page->cards as $card)
                        <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg focus-within:-translate-y-1 focus-within:shadow-lg">
                            <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-spu-blue/5">
                                <img src="{{ $card['icon'] }}" alt="" class="h-7 w-7" aria-hidden="true">
                            </div>
                            <h3 class="text-xl font-black text-spu-blue">{{ $card['title'] }}</h3>
                            <p class="mt-4 text-sm font-bold leading-7 text-slate-700">{{ $card['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-section py-24">
            <div class="container mx-auto px-6">
                <h2 class="reveal reveal-up text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $page->pillarsTitle }}</h2>
                <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($page->pillars as $pillar)
                        <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-black text-spu-blue">{{ $pillar['title'] }}</h3>
                            <p class="mt-3 text-sm font-bold leading-7 text-slate-700">{{ $pillar['summary'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
