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
        {{-- Hero --}}
        <section class="vision-subpage-hero relative flex items-center justify-center overflow-hidden pt-28 font-hacen">
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

        {{-- Summary --}}
        @if ($page->summary !== '')
            <section class="bg-white py-16 font-hacen md:py-20">
                <div class="container mx-auto px-6">
                    <div class="mx-auto max-w-4xl text-center">
                        <p class="text-base font-bold leading-7 text-slate-600 md:text-lg md:leading-8">{{ $page->summary }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- Vision / Mission / Values Cards --}}
        @if ($page->cards !== [])
            <section class="bg-white pb-16 font-hacen md:pb-20">
                <div class="container mx-auto px-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        @foreach ($page->cards as $card)
                            <article class="reveal reveal-up rounded-xl border border-slate-100 bg-white p-8 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-md">
                                @if (! empty($card['icon']))
                                    <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center">
                                        <img src="{{ $card['icon'] }}" alt="" class="h-7 w-7" aria-hidden="true">
                                    </div>
                                @endif
                                <h3 class="text-lg font-bold text-spu-blue">{{ $card['title'] }}</h3>
                                <p class="mt-3 text-sm font-medium leading-6 text-slate-500">{{ $card['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Strategic Pillars --}}
        @if ($page->pillars !== [])
            <section class="bg-section py-16 font-hacen md:py-20">
                <div class="container mx-auto px-6">
                    <div class="mb-10 text-center md:mb-12">
                        <h2 class="reveal reveal-up text-2xl font-black text-spu-blue md:text-3xl">{{ $page->pillarsTitle }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
                        @foreach ($page->pillars as $pillar)
                            <article class="reveal reveal-up rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                                <h3 class="text-sm font-bold text-spu-blue">{{ $pillar['title'] }}</h3>
                                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">{{ $pillar['summary'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
