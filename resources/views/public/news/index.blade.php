@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $heroLinks = $page['heroLinks'] ?? [];
    @endphp

    <section class="relative flex h-screen items-center justify-center overflow-hidden py-24">
        <div class="absolute inset-0 z-0">
            <img src="{{ $page['heroImage'] ?? '/images/slider-1.webp' }}" alt="{{ $pageTitle }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-spu-blue/40"></div>
        </div>

        <div class="container relative top-26 z-10 text-center text-white">
            <h1 class="mb-6 text-[48px] font-bold uppercase tracking-[0.4em] text-spu-gold">{{ $page['heroTitle'] ?? $pageTitle }}</h1>
            <p class="mb-8 text-[30px] font-bold leading-tight">{{ $page['pageDescription'] ?? $pageDescription }}</p>

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
                        <img src="{{ $featured->imageUrl ?: '/images/news/first-webo.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/first-webo.jpeg'" alt="{{ $featured->title }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
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

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lastNews as $news)
                    <article class="group relative h-[500px] overflow-hidden rounded-[8px] border-2 border-[#CBD5E1B2] bg-white transition-all duration-300 hover:shadow-2xl {{ $loop->iteration === 4 ? 'lg:col-span-2' : 'col-span-1' }}">
                        <a href="{{ $news->url ?? '#' }}" class="block">
                            <div class="relative w-full overflow-hidden {{ $loop->iteration === 4 ? 'h-[60%]' : 'h-[55%]' }}">
                                <img src="{{ $news->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $news->title }}" class="h-full w-full object-cover">
                                <div class="absolute left-5 top-5 rtl:left-auto rtl:right-5">
                                    <span class="rounded-lg bg-white px-5 py-1.5 text-[12px] font-bold text-[#202759] shadow-sm">{{ $news->categoryLabel ?: ($page['newsFallbackCategory'] ?? ($isAr ? 'أخبار' : 'News')) }}</span>
                                </div>
                            </div>
                        </a>

                        <div class="p-8">
                            <div class="relative">
                                <a href="{{ $news->url ?? '#' }}" class="absolute top-[-60px] z-10 max-h-[62px] w-[300px] overflow-hidden bg-white px-5 py-[3px] text-[18px] font-bold text-[#202759] {{ $isAr ? 'right-[-32px]' : 'left-[-32px]' }}">{{ $news->title }}</a>
                                @if ($news->publishedAt)
                                    <p class="mb-5 text-[14px] font-medium lowercase text-[#c0392b]" translate="no">{{ $news->publishedAt }}</p>
                                @endif
                                @if ($news->excerpt)
                                    <p class="line-clamp-3 text-[16px] leading-relaxed text-slate-900">{{ $news->excerpt }}</p>
                                @endif
                            </div>
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
                            <article class="group flex gap-8 p-10 {{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                                <div class="flex-shrink-0 pt-1">
                                    <img src="{{ $loop->odd ? '/images/icon-envelope-outline.svg' : '/images/icon-file-outline.svg' }}" alt="" class="h-7 w-7" aria-hidden="true">
                                </div>
                                <div class="flex-grow">
                                    <div class="mb-3 flex items-start justify-between gap-6">
                                        <h3 class="text-2xl font-bold leading-tight text-[#202759]">{{ $announcement->title }}</h3>
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
