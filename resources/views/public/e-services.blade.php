@extends('layouts.public')

@section('content')
    <div class="relative h-72 w-full overflow-hidden md:h-[450px]" id="digital-services-hero">
        <img src="{{ $page->hero['imageHero'] }}" alt="Digital Services" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-[#1e2756]/50"></div>
        <div class="absolute inset-0 flex flex-col items-center justify-center px-4 text-center text-white">
            <div class="mb-4 rounded bg-[#1e2756]/80 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em]">
                {{ $page->hero['eyebrow'] }}
            </div>
            <h1 class="text-4xl font-bold leading-tight md:text-5xl lg:text-[44px]">
                {{ $page->hero['title'] }}
            </h1>
            <p class="mt-4 max-w-[800px] text-sm font-medium leading-relaxed text-white/90 md:text-base">
                {{ $page->hero['summary'] }}
            </p>
        </div>
    </div>

    <section class="relative w-full overflow-hidden bg-white pt-24 pb-32" id="portal-access">
        <span id="library" class="sr-only"></span>
        <div class="relative z-10 pb-20 text-center">
            <h2 class="text-4xl font-bold text-[#1e2756] md:text-[40px]">{{ $page->digitalServices['title'] }}</h2>
        </div>

        <div class="container relative">
            <div class="pointer-events-none absolute inset-0 z-0 hidden h-full w-full lg:block">
                <div class="absolute left-0 z-0 h-[581px] w-[441px] -top-12 overflow-hidden">
                    <div class="absolute inset-0 z-10"></div>
                    <img src="{{ $page->hero['imageLeft'] }}" alt="Campus Day" class="h-full w-full object-cover">
                </div>
                <div class="absolute right-0 z-0 h-[581px] w-[441px] -bottom-12 overflow-hidden">
                    <div class="absolute inset-0 z-10"></div>
                    <img src="{{ $page->hero['imageRight'] }}" alt="Campus Night" class="h-full w-full object-cover">
                </div>
            </div>

            <div class="relative z-10">
                <div class="cms-grid-wide gap-10 lg:gap-18 xl:gap-24">
                    @foreach ($page->digitalServices['services'] as $service)
                        <div class="relative flex min-h-[314px] flex-col rounded-xl border border-[#94A3B880] bg-white p-8 shadow-[0_4px_20px_rgb(0,0,0,0.03)] transition-all hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                            <div class="mb-4 flex items-start justify-between">
                                <h3 class="pr-4 text-[20px] font-bold text-[#1e2756] rtl:pr-0 rtl:pl-4">{{ $service['title'] }}</h3>
                                <div class="mt-1 text-[#1e2756]">
                                    <img src="{{ $service['icon'] }}" alt="" class="h-5 w-5 object-contain services-card-icon" aria-hidden="true">
                                </div>
                            </div>

                            <p class="mb-6 flex-grow text-[13px] leading-relaxed text-gray-500">{{ $service['summary'] }}</p>

                            <div class="mt-auto">
                                <a href="{{ $service['url'] }}" class="inline-flex w-max items-center justify-center rounded bg-[#1e2756] px-6 py-2.5 text-[11px] font-bold uppercase tracking-wider text-white transition-colors hover:bg-opacity-90">
                                    <span>{{ $service['button'] }}</span>
                                    @if (str_contains($service['button'], 'Launch') || str_contains($service['button'], 'تفعيل'))
                                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="ml-2 h-3.5 w-3.5 brightness-0 invert rtl:mr-2 rtl:ml-0 rtl:rotate-180" aria-hidden="true">
                                    @endif
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="appeals-forms" class="bg-white py-16 font-hacen">
        <div class="container">
            <div class="cms-grid-wide gap-6">
                @foreach ($page->supportCards as $card)
                    <article @if ($card['id'] === 'privacy') id="privacy" @endif class="rounded-[8px] border border-spu-blue/10 bg-white p-6 shadow-[0_12px_34px_rgba(32,39,89,0.08)]">
                        @if ($card['id'] === 'privacy')
                            <span id="cookies" class="sr-only"></span>
                        @endif
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-spu-blue/50">{{ $card['eyebrow'] }}</p>
                        <h2 class="mt-3 text-[24px] font-bold text-spu-blue">{{ $card['title'] }}</h2>
                        <p class="mt-3 text-sm leading-6 text-spu-blue/65">{{ $card['summary'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
