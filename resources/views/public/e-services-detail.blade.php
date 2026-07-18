@extends('layouts.public')

@section('content')
    <article class="bg-[#f7f8fb] text-spu-blue">
        <header class="relative min-h-[32rem] overflow-hidden pt-32 sm:min-h-[36rem]">
            <img src="{{ $page->heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-b from-[#121938]/80 via-[#202759]/75 to-[#202759]/95"></div>
            <div class="container relative z-10 flex min-h-[25rem] flex-col justify-end pb-14 pt-20 text-white sm:min-h-[29rem] sm:pb-20">
                <nav aria-label="{{ $locale === 'ar' ? 'مسار التنقل' : 'Breadcrumb' }}" class="mb-8 flex flex-wrap items-center gap-2 text-sm font-semibold text-white/75">
                    <a href="/{{ $locale }}" class="rounded-sm transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <span aria-hidden="true">/</span>
                    <a href="/{{ $locale }}/e-services" class="rounded-sm transition hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">{{ $locale === 'ar' ? 'الخدمات الإلكترونية' : 'E-Services' }}</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" class="text-white">{{ $page->heroTitle }}</span>
                </nav>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-white/70">{{ $page->heroEyebrow }}</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">{{ $page->heroTitle }}</h1>
                <p class="mt-5 max-w-3xl text-base leading-8 text-white/85 sm:text-lg">{{ $page->heroSummary }}</p>
            </div>
        </header>

        <div class="container py-14 sm:py-20 lg:py-24">
            <section aria-labelledby="detail-intro-title" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div class="rounded-[1.25rem] border border-spu-blue/10 bg-white p-7 shadow-[0_20px_55px_rgba(32,39,89,0.08)] sm:p-10">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-spu-red">{{ $page->heroEyebrow }}</p>
                    <h2 id="detail-intro-title" class="mt-3 text-3xl font-black leading-tight sm:text-4xl">{{ $page->introTitle }}</h2>
                    <p class="mt-5 max-w-3xl text-base leading-8 text-spu-blue/70">{{ $page->introBody }}</p>
                </div>
                <aside class="rounded-[1.25rem] bg-spu-blue p-7 text-white shadow-[0_20px_55px_rgba(32,39,89,0.16)]" aria-labelledby="related-services-title">
                    <h2 id="related-services-title" class="text-xl font-black">{{ $locale === 'ar' ? 'خدمات ذات صلة' : 'Related services' }}</h2>
                    <ul class="mt-5 grid gap-3">
                        @foreach ($page->relatedLinks as $link)
                            <li><a href="{{ $link['url'] }}" class="flex min-h-11 items-center justify-between gap-4 rounded-xl border border-white/15 px-4 py-3 text-sm font-bold transition hover:border-white/40 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><span>{{ $link['title'] }}</span><span aria-hidden="true" class="rtl:rotate-180">&rarr;</span></a></li>
                        @endforeach
                    </ul>
                </aside>
            </section>

            <section aria-labelledby="guidance-title" class="mt-14 sm:mt-20">
                <div class="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-spu-red">{{ $locale === 'ar' ? 'إرشادات' : 'Guidance' }}</p>
                        <h2 id="guidance-title" class="mt-2 text-3xl font-black sm:text-4xl">{{ $locale === 'ar' ? 'معلومات أساسية' : 'Essential information' }}</h2>
                    </div>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($page->sections as $section)
                        <section class="rounded-[1.25rem] border border-spu-blue/10 bg-white p-7 shadow-[0_14px_38px_rgba(32,39,89,0.06)]">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-spu-red/10 text-sm font-black text-spu-red" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="mt-6 text-xl font-black">{{ $section['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-spu-blue/65">{{ $section['body'] }}</p>
                        </section>
                    @endforeach
                </div>
            </section>

            @if ($page->resourceLinks !== [])
                <section aria-labelledby="resource-links-title" class="mt-14 rounded-[1.5rem] border border-spu-blue/10 bg-white p-7 shadow-[0_18px_48px_rgba(32,39,89,0.07)] sm:mt-20 sm:p-10">
                    <h2 id="resource-links-title" class="text-3xl font-black">{{ $page->resourceLinksTitle }}</h2>
                    <div class="mt-7 grid gap-4 sm:grid-cols-2">
                        @foreach ($page->resourceLinks as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="group flex min-h-16 items-center justify-between gap-5 rounded-xl border border-spu-blue/10 px-5 py-4 font-bold transition hover:border-spu-red/50 hover:bg-spu-red/[0.03] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-red">
                                <span>{{ $link['title'] }}</span>
                                <span aria-hidden="true" class="text-spu-red transition group-hover:-translate-y-0.5">&#8599;</span>
                                <span class="sr-only">{{ $locale === 'ar' ? '(يفتح في نافذة جديدة)' : '(opens in a new window)' }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="detail-cta-title" class="mt-14 overflow-hidden rounded-[1.5rem] bg-spu-blue px-7 py-10 text-white shadow-[0_24px_60px_rgba(32,39,89,0.2)] sm:mt-20 sm:px-12 lg:flex lg:items-center lg:justify-between lg:gap-12">
                <div class="max-w-2xl">
                    <h2 id="detail-cta-title" class="text-3xl font-black">{{ $page->ctaTitle }}</h2>
                    <p class="mt-3 leading-7 text-white/75">{{ $page->ctaBody }}</p>
                </div>
                <a href="{{ $page->ctaUrl }}" class="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-spu-red px-7 py-3 text-sm font-black text-white transition hover:bg-[#8a2525] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white lg:mt-0">{{ $page->ctaLabel }}</a>
            </section>
        </div>
    </article>
@endsection
