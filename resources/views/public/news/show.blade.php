@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $previousArticle = $adjacentArticles['previous'] ?? null;
        $nextArticle = $adjacentArticles['next'] ?? null;
    @endphp

    <section class="relative flex min-h-[285px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="/images/slider-1.webp" alt="{{ $article->title }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/64 to-spu-blue/12"></div>
        </div>

        <div class="container relative z-10 pb-10 text-center text-white">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $isAr ? 'الرئيسية' : 'Home' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/news/articles" class="transition-colors hover:text-white">{{ $isAr ? 'الأخبار' : 'News' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $isAr ? 'صفحة الخبر' : 'News Article Page' }}</span>
            </nav>

            <h1 class="text-[30px] font-bold leading-tight md:text-[40px]">{{ $isAr ? 'صفحة الخبر' : 'News Article Page' }}</h1>
        </div>
    </section>

    <article class="bg-white py-8 font-hacen md:py-10">
        <div class="container">
            <header class="mx-auto text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-spu-red">{{ $article->category?->name ?: ($isAr ? 'أخبار الجامعة' : 'University News') }}</p>
                <h1 class="mt-3 text-[24px] font-bold leading-tight text-spu-blue md:text-[30px]">{{ $article->title }}</h1>
                @if ($article->excerpt)
                    <p class="mx-auto mt-3 text-[13px] font-semibold leading-6 text-slate-500">{{ $article->excerpt }}</p>
                @endif

                <div class="mt-5 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[11px] font-bold uppercase tracking-[0.06em] text-slate-500">
                    @if ($article->publishedAt)
                        <span translate="no">{{ $article->publishedAt }}</span>
                        <span class="h-1 w-1 rounded-full bg-slate-300" aria-hidden="true"></span>
                    @endif
                    <span>{{ $isAr ? 'فريق أخبار الجامعة' : 'SPU News Desk' }}</span>
                    <span class="h-1 w-1 rounded-full bg-slate-300" aria-hidden="true"></span>
                    <span>{{ $isAr ? '3 دقائق قراءة' : '3 min read' }}</span>
                </div>
            </header>

            <figure class="mt-7 overflow-hidden rounded-[4px] bg-spu-blue">
                <img src="{{ $article->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $article->title }}" class="h-[250px] w-full object-cover md:h-[390px]">
            </figure>

            <div class="mx-auto mt-8 text-[14px] font-medium leading-7 text-slate-700">
                @if ($article->body)
                    <div class="prose max-w-none prose-p:mb-5 prose-p:text-[14px] prose-p:font-medium prose-p:leading-7 prose-p:text-slate-700 prose-headings:text-spu-blue prose-a:text-spu-red prose-img:rounded-[4px]">
                        {!! $article->body !!}
                    </div>
                @elseif ($article->excerpt)
                    <p class="mb-5">{{ $article->excerpt }}</p>
                @endif

                <div class="my-7 border-l-4 border-spu-red bg-slate-50 px-5 py-4 text-[14px] font-bold leading-7 text-spu-blue rtl:border-l-0 rtl:border-r-4">
                    {{ $isAr ? 'تواصل الجامعة توثيق التقدم الأكاديمي والتفاعل المجتمعي عبر الأخبار الرسمية.' : 'The university continues to document academic progress and community engagement through official news updates.' }}
                </div>
            </div>

            @if ($article->attachments !== [])
                <section class="mx-auto mt-9 rounded-[6px] border border-slate-200 bg-slate-50 p-6">
                    <h2 class="text-[20px] font-bold text-spu-blue">{{ $isAr ? 'المرفقات' : 'Attachments' }}</h2>
                    <div class="mt-5 grid gap-3">
                        @foreach ($article->attachments as $attachment)
                            @if ($attachment->url)
                                <a href="{{ $attachment->url }}" class="flex items-center justify-between rounded-[4px] bg-white px-4 py-3 text-[12px] font-bold text-spu-blue shadow-sm transition hover:text-spu-red">
                                    <span>{{ $attachment->label ?: ($isAr ? 'مرفق' : 'Attachment') }}</span>
                                    <span>{{ strtoupper($attachment->kind) }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <footer class="mx-auto mt-9 flex flex-col gap-4 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                @if ($previousArticle)
                    <a href="{{ $previousArticle->url }}" class="inline-flex items-center gap-2 text-[12px] font-bold text-spu-blue transition hover:text-spu-red">
                        <img src="/images/icon-arrow-left-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                        <span>{{ $isAr ? 'السابق' : 'Previous' }}</span>
                    </a>
                @else
                    <span></span>
                @endif

                <div class="flex items-center justify-center gap-3">
                    <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $isAr ? 'مشاركة الخبر' : 'Share article' }}</span>
                    <a href="https://www.facebook.com/SPUpage.sy/?ref=bookmarks" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 transition hover:border-spu-blue" aria-label="Facebook">
                        <img src="/images/icon-facebook-outline.svg" alt="" class="h-3.5 w-3.5" aria-hidden="true">
                    </a>
                    <a href="https://telegram.me/SPUchannel" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 transition hover:border-spu-blue" aria-label="Telegram">
                        <img src="/images/icon-telegram-outline.svg" alt="" class="h-3.5 w-3.5" aria-hidden="true">
                    </a>
                </div>

                @if ($nextArticle)
                    <a href="{{ $nextArticle->url }}" class="inline-flex items-center justify-end gap-2 text-[12px] font-bold text-spu-blue transition hover:text-spu-red">
                        <span>{{ $isAr ? 'التالي' : 'Next' }}</span>
                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                    </a>
                @else
                    <span></span>
                @endif
            </footer>
        </div>
    </article>

    @if ($relatedArticles->isNotEmpty())
        <section class="bg-white pb-14 font-hacen md:pb-16">
            <div class="container">
                <h2 class="text-[22px] font-bold text-spu-blue md:text-[28px]">{{ $isAr ? 'أخبار ذات صلة' : 'Related News' }}</h2>
                <div class="cms-grid-news mt-6 gap-6">
                    @foreach ($relatedArticles as $related)
                        <article class="overflow-hidden rounded-[6px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md">
                            <a href="{{ $related->url }}" class="block">
                                <div class="relative aspect-[1.58] overflow-hidden bg-slate-100">
                                    <img src="{{ $related->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $related->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                                    @if ($related->categoryLabel)
                                        <span class="absolute left-3 top-3 rounded-[3px] bg-white px-2.5 py-1 text-[10px] font-bold text-spu-blue shadow-sm rtl:left-auto rtl:right-3">{{ $related->categoryLabel }}</span>
                                    @endif
                                </div>
                            </a>

                            <div class="p-4">
                                <h3 class="text-[15px] font-bold leading-[20px] text-spu-blue">{{ $related->title }}</h3>
                                @if ($related->publishedAt)
                                    <p class="mt-2 text-[11px] font-bold text-spu-red" translate="no">{{ $related->publishedAt }}</p>
                                @endif
                                @if ($related->excerpt)
                                    <p class="mt-3 min-h-[58px] text-[12px] font-medium leading-[20px] text-slate-600">{{ $related->excerpt }}</p>
                                @endif
                                <a href="{{ $related->url }}" class="mt-4 inline-flex items-center gap-2 text-[12px] font-bold text-spu-red transition hover:text-spu-blue">
                                    <span>{{ $isAr ? 'اقرأ المزيد' : 'Read More' }}</span>
                                    <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
