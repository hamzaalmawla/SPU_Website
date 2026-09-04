@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $heroLinks = $page['heroLinks'] ?? [];
    @endphp

    <section class="relative flex min-h-[32rem] h-[min(38rem,100svh)] items-center justify-center overflow-hidden py-24 md:h-screen">
        <div class="absolute inset-0 z-0">
            <img src="{{ $page['heroImage'] ?? '/images/slider-1.webp' }}" alt="{{ $pageTitle }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-spu-blue/40"></div>
        </div>

        <div class="container relative z-10 pt-16 text-center text-white md:pt-20">
            {{-- Not gold. This heading carried text-spu-gold for months while no such
                 token existed, so it inherited white and measured 2.37:1 on the hero
                 photograph. The moment --color-spu-gold was defined the class woke up
                 and the heading dropped to 1.27:1 — worse than what it replaced. The
                 scrim behind it is the open item; the colour is not. --}}
            <h1 class="mb-6 text-[clamp(2.15rem,10vw,3rem)] font-bold uppercase tracking-[0.12em] sm:tracking-[0.22em] md:tracking-[0.4em]">{{ $page['heroTitle'] ?? $pageTitle }}</h1>
            <p class="mb-8 text-[clamp(1.15rem,5vw,1.875rem)] font-bold leading-tight">{{ $page['pageDescription'] ?? $pageDescription }}</p>

            <div class="mt-12 space-y-4">
                <div class="flex flex-wrap items-center justify-center gap-4">
                    @foreach (array_slice($heroLinks, 0, 3) as $link)
                        <a href="#{{ $link['id'] }}" class="rounded-xl border border-white/20 bg-white/5 px-10 py-3 text-sm font-bold backdrop-blur-md transition-all hover:border-white/40 hover:bg-white/20">{{ $link['label'] }}</a>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    @foreach (array_slice($heroLinks, 3) as $link)
                        <a href="#{{ $link['id'] }}" class="rounded-xl border border-white/20 bg-white/5 px-10 py-3 text-sm font-bold backdrop-blur-md transition-all hover:border-white/40 hover:bg-white/20">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($featured)
        <section class="bg-white py-16 font-hacen">
            <div class="container">
                <article class="group flex flex-col items-center gap-12 lg:flex-row">
                    <a href="{{ $featured->url ?? '/'.$locale.'/news/articles' }}" class="aspect-video w-full overflow-hidden rounded-2xl lg:w-1/2">
                        <img src="{{ $featured->imageUrl ?: '/images/news/first-webo.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/first-webo.jpeg'" alt="{{ $featured->title }}" class="content-media-image h-full w-full">
                    </a>

                    <div class="w-full lg:w-1/2">
                        <div class="mb-6 flex items-center gap-3 text-sm font-bold uppercase tracking-widest text-spu-red">
                            <span>{{ $featured->categoryLabel ?: ($page['universityNewsFallbackCategory'] ?? ($isAr ? 'أخبار الجامعة' : 'University News')) }}</span>
                            <span class="h-[1px] w-4 bg-slate-300"></span>
                            @if ($featured->publishedAt)
                                <span class="text-slate-400" translate="no">{{ $featured->publishedAt }}</span>
                            @endif
                        </div>

                        <h2 class="mb-6 text-3xl font-bold leading-tight text-spu-blue lg:text-4xl">{{ $featured->title }}</h2>
                        @if ($featured->excerpt)
                            <p class="mb-8 text-lg leading-relaxed text-slate-600">{{ $featured->excerpt }}</p>
                        @endif

                        <a href="{{ $featured->url ?? '/'.$locale.'/news/articles' }}" class="group/link inline-flex items-center gap-2 font-bold text-spu-blue">
                            <span>{{ $page['readMoreLabel'] ?? ($isAr ? 'اقرأ المزيد' : 'Read More') }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-5 w-5 transition-transform group-hover/link:translate-x-1 rtl:rotate-180 rtl:group-hover/link:-translate-x-1" aria-hidden="true">
                        </a>
                    </div>
                </article>
            </div>
        </section>
    @endif

    <section id="last-news" class="bg-white py-20 font-hacen">
        <div class="container">
            <div class="mb-12 flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-3xl font-bold text-[#202759] md:text-5xl">{{ $page['lastNewsTitle'] ?? ($isAr ? 'آخر الأخبار' : 'Last News') }}</h2>
                <a href="/{{ $locale }}/news/articles" class="rounded border border-slate-300 px-6 py-2 text-sm font-bold text-[#202759] transition-colors hover:bg-slate-50">{{ $page['lastNewsViewAllLabel'] ?? ($isAr ? 'عرض الكل' : 'View All News') }}</a>
            </div>

            <div data-news-grid="landing" class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 xl:gap-8">
                @foreach ($lastNews as $news)
                    <article data-news-card class="group flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_12px_34px_rgba(32,39,89,0.07)] transition duration-300 hover:-translate-y-1 hover:border-spu-blue/20 hover:shadow-[0_22px_52px_rgba(32,39,89,0.13)]">
                        <a href="{{ $news->url ?? '#' }}" class="block">
                            <div class="relative aspect-[16/10] w-full overflow-hidden border-b border-slate-100 bg-slate-100">
                                <img src="{{ $news->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $news->title }}" class="content-media-image h-full w-full">
                                <span class="absolute start-4 top-4 max-w-[calc(100%_-_2rem)] truncate rounded-full bg-white/95 px-3.5 py-1.5 text-[11px] font-bold text-spu-blue shadow-md backdrop-blur-sm">{{ $news->categoryLabel ?: ($page['newsFallbackCategory'] ?? ($isAr ? 'أخبار' : 'News')) }}</span>
                            </div>
                        </a>

                        <div class="flex flex-1 flex-col p-5 sm:p-6">
                            @if ($news->publishedAt)
                                <p class="text-[12px] font-bold text-spu-red" translate="no">{{ $news->publishedAt }}</p>
                            @endif
                            <a href="{{ $news->url ?? '#' }}" class="mt-3 block rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">
                                <h3 class="line-clamp-3 text-[19px] font-bold leading-[1.45] text-spu-blue transition-colors group-hover:text-spu-red sm:text-[20px]">{{ $news->title }}</h3>
                            </a>
                            @if ($news->excerpt)
                                <p class="mt-4 line-clamp-3 text-[14px] font-medium leading-7 text-slate-600">{{ $news->excerpt }}</p>
                            @endif
                            <a href="{{ $news->url ?? '#' }}" class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-5 text-[12px] font-bold text-spu-blue transition-colors hover:text-spu-red">
                                <span>{{ $page['readMoreLabel'] ?? ($isAr ? 'اقرأ المزيد' : 'Read More') }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 shrink-0 rtl:rotate-180" aria-hidden="true">
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-24 font-hacen">
        <div class="container">
            <div class="mb-12 flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-3xl font-bold text-[#202759] md:text-5xl">{{ $page['announcementsTitle'] ?? ($isAr ? 'الإعلانات' : 'Announcements') }}</h2>
                <a href="/{{ $locale }}/news/announcements" class="rounded border border-slate-300 px-6 py-2 text-sm font-bold text-[#202759] transition-colors hover:bg-slate-50">{{ $page['announcementsViewAllLabel'] ?? ($isAr ? 'عرض كافة الإعلانات' : 'View All Announcements') }}</a>
            </div>

            <div class="grid items-stretch gap-8 lg:grid-cols-12">
                <div id="events" class="lg:col-span-4">
                    <div class="flex h-full flex-col rounded-[12px] bg-[#1e2a5e] p-10 text-white">
                        <div class="mb-12 flex items-center justify-between border-b border-white/20 pb-6">
                            <h3 class="text-3xl font-bold">{{ $page['eventsTitle'] ?? ($isAr ? 'الفعاليات القادمة' : 'Upcoming Events') }}</h3>
                            <img src="/images/icon-calendar-outline.svg" alt="" class="h-7 w-7 brightness-0 invert" aria-hidden="true">
                        </div>

                        <div class="relative flex-grow space-y-12 pb-10">
                            <div class="absolute bottom-12 left-[7px] top-2 w-[2px] bg-white/20 rtl:left-auto rtl:right-[7px]"></div>
                            @foreach ($events as $event)
                                <a href="{{ $event->detailUrl }}" class="relative block pl-10 rtl:pl-0 rtl:pr-10">
                                    <div class="absolute left-0 top-1.5 z-10 h-4 w-4 rounded-full border-2 border-white bg-[#1e2a5e] rtl:left-auto rtl:right-0"></div>
                                    <p class="mb-2 text-[12px] font-bold uppercase tracking-widest text-white/60" translate="no">{{ $event->dateLabel }}</p>
                                    <h4 class="mb-2 text-xl font-bold">{{ $event->title }}</h4>
                                    <p class="text-[13px] uppercase tracking-wide text-white/50">{{ $event->categoryLabel }}</p>
                                </a>
                            @endforeach
                        </div>

                        <a href="/{{ $locale }}/news/events-list" class="mt-auto block w-full rounded-lg border border-white py-4 text-center text-sm font-bold transition-colors hover:bg-white/10">{{ $page['eventsViewAllLabel'] ?? ($isAr ? 'عرض تفاصيل كافة الفعاليات' : 'View All Events Details') }}</a>
                    </div>
                </div>

                <div id="announcements" class="lg:col-span-8">
                    <div class="h-full overflow-hidden rounded-[12px] border border-slate-200 bg-white">
                        @forelse ($announcements as $announcement)
                            <article class="group flex flex-col gap-4 p-5 sm:flex-row sm:gap-8 sm:p-10 {{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                                <div class="flex-shrink-0 pt-1">
                                    <img src="{{ $loop->odd ? '/images/icon-envelope-outline.svg' : '/images/icon-file-outline.svg' }}" alt="" class="h-7 w-7" aria-hidden="true">
                                </div>
                                <div class="flex-grow">
                                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3 sm:gap-6">
                                        <h3 class="min-w-0 flex-1 text-xl font-bold leading-tight text-[#202759] sm:text-2xl">{{ $announcement->title }}</h3>
                                        <span class="whitespace-nowrap rounded-md bg-[#202759] px-4 py-1.5 text-[11px] font-bold uppercase tracking-widest text-white">{{ $page['newLabel'] ?? ($isAr ? 'جديد' : 'New') }}</span>
                                    </div>
                                    @if ($announcement->excerpt)
                                        <p class="mb-6 max-w-[90%] text-[17px] leading-relaxed text-slate-600">{{ $announcement->excerpt }}</p>
                                    @endif
                                    <a href="{{ $announcement->url ?? '#' }}" class="group/link inline-flex items-center gap-2 text-[15px] font-bold text-[#202759]">
                                        <span>{{ $page['viewDetailsLabel'] ?? ($isAr ? 'عرض التفاصيل' : 'View Details') }}</span>
                                        <span class="transition-transform group-hover/link:translate-x-1 rtl:group-hover/link:-translate-x-1">→</span>
                                    </a>
                                </div>
                            </article>
                        @empty
                            <div class="p-10 text-slate-600">{{ $page['emptyAnnouncements'] ?? ($isAr ? 'لا توجد إعلانات منشورة حالياً.' : 'No announcements are currently published.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-section bg-slate-50 py-24 font-hacen">
        <div class="container">
            <h2 class="mb-16 text-center text-3xl font-bold text-spu-blue">{{ $page['exploreMoreTitle'] ?? ($isAr ? 'استكشف المزيد' : 'Explore More') }}</h2>
            <div class="cms-grid-wide mx-auto max-w-5xl gap-8">
                <a id="media-gallery" href="/{{ $locale }}/news/gallery" class="group relative flex flex-col items-center gap-6 overflow-hidden rounded-[32px] border border-slate-100 bg-white p-12 text-center text-spu-blue shadow-sm transition-all duration-500 hover:translate-y-[-8px] hover:bg-[#1e2a5e] hover:text-white hover:shadow-2xl">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 transition-colors group-hover:bg-white/20">
                        <img src="/images/icon-file-outline.svg" alt="" class="h-8 w-8" aria-hidden="true">
                    </div>
                    <div>
                        <h3 class="mb-3 text-3xl font-bold">{{ $page['archiveTitle'] ?? ($isAr ? 'أرشيف الأخبار' : 'News Archive') }}</h3>
                        <p class="flex items-center justify-center gap-2 text-sm font-bold uppercase tracking-widest opacity-60"><span>{{ $page['archiveCta'] ?? ($isAr ? 'انقر للزيارة' : 'Visit Room') }}</span><span class="text-lg">→</span></p>
                    </div>
                </a>

                <a id="press-room" href="/{{ $locale }}/news/announcements" class="group relative flex flex-col items-center gap-6 overflow-hidden rounded-[32px] border border-slate-100 bg-white p-12 text-center text-spu-blue shadow-sm transition-all duration-500 hover:translate-y-[-8px] hover:bg-[#1e2a5e] hover:text-white hover:shadow-2xl">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 transition-colors group-hover:bg-white/20">
                        <img src="/images/icon-book-outline.svg" alt="" class="h-8 w-8" aria-hidden="true">
                    </div>
                    <div>
                        <h3 class="mb-3 text-3xl font-bold">{{ $page['announcementsCardTitle'] ?? ($isAr ? 'الإعلانات' : 'Announcements') }}</h3>
                        <p class="flex items-center justify-center gap-2 text-sm font-bold uppercase tracking-widest opacity-60"><span>{{ $page['announcementsCardCta'] ?? ($isAr ? 'انقر للزيارة' : 'Visit Room') }}</span><span class="text-lg">→</span></p>
                    </div>
                </a>
            </div>
        </div>
    </section>
@endsection
