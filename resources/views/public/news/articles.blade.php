@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $articleQuery = array_filter(['category' => $activeCategory, 'search' => $search], fn (mixed $value): bool => is_string($value) && $value !== '');
        $articlePageUrl = fn (int $pageNumber): string => '/'.$locale.'/news/articles'.(($query = [...$articleQuery, ...($pageNumber > 1 ? ['page' => $pageNumber] : [])]) !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    @endphp

    <section class="relative flex min-h-[280px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $page['heroImage'] }}" alt="{{ $pageTitle }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/64 to-spu-blue/12"></div>
        </div>

        <div class="container relative z-10 pb-12 text-center text-white">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $isAr ? 'الرئيسية' : 'Home' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/news" class="transition-colors hover:text-white">{{ $isAr ? 'الأخبار' : 'News' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $pageTitle }}</span>
            </nav>

            <h1 class="text-[30px] font-bold leading-tight md:text-[40px]">{{ $pageTitle }}</h1>
        </div>
    </section>

    <section class="bg-white py-14 font-hacen md:py-16">
        <div class="container max-w-[1180px]">
            <form method="GET" action="/{{ $locale }}/news/articles" role="search" class="flex flex-wrap items-end justify-start gap-2">
                <label class="min-w-[240px] flex-1"><span class="sr-only">{{ $page['searchLabel'] }}</span><input type="search" name="search" value="{{ $search }}" placeholder="{{ $page['searchPlaceholder'] }}" class="h-10 w-full rounded-[5px] border border-slate-200 px-4 text-sm focus:border-spu-blue focus:outline-none focus:ring-2 focus:ring-spu-blue/15"></label>
                @if ($activeCategory)<input type="hidden" name="category" value="{{ $activeCategory }}">@endif
                <button class="h-10 rounded-[5px] bg-spu-blue px-5 text-xs font-bold text-white" type="submit">{{ $page['searchAction'] }}</button>
            </form>
            <form method="GET" action="/{{ $locale }}/news/articles" class="mt-4 flex flex-wrap items-center justify-start gap-2">
                @if ($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                <button name="category" value="" class="rounded-[5px] border px-4 py-2 text-[11px] font-bold transition {{ $activeCategory === null ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 bg-white text-spu-blue hover:border-spu-blue' }}" type="submit">{{ $page['allLabel'] }}</button>
                @foreach ($categories as $category)
                    <button name="category" value="{{ $category->slug }}" class="rounded-[5px] border px-4 py-2 text-[11px] font-bold transition {{ $activeCategory === $category->slug ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 bg-white text-spu-blue hover:border-spu-blue' }}" type="submit">{{ $category->name }}</button>
                @endforeach
            </form>

            <div data-news-grid="articles" class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 xl:gap-7">
                @forelse ($articles->items as $article)
                    <article id="article-{{ $article->id }}" data-news-card class="group flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_12px_34px_rgba(32,39,89,0.07)] transition duration-300 hover:-translate-y-1 hover:border-spu-blue/20 hover:shadow-[0_22px_52px_rgba(32,39,89,0.13)]">
                        <a href="{{ $article->url }}" class="block">
                            <div class="relative aspect-[16/10] overflow-hidden border-b border-slate-100 bg-slate-100">
                                <img src="{{ $article->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $article->title }}" loading="lazy" class="content-media-image h-full w-full">
                                @if ($article->category)
                                    <span class="absolute start-4 top-4 max-w-[calc(100%_-_2rem)] truncate rounded-full bg-white/95 px-3.5 py-1.5 text-[11px] font-bold text-spu-blue shadow-md backdrop-blur-sm">{{ $article->category->name }}</span>
                                @endif
                            </div>
                        </a>

                        <div class="flex flex-1 flex-col p-5 sm:p-6">
                            @if ($article->publishedAt)
                                <p class="text-[12px] font-bold text-spu-red" translate="no">{{ $article->publishedAt }}</p>
                            @endif
                            <a href="{{ $article->url }}" class="mt-3 block rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red" aria-label="{{ $article->title }}">
                                <h2 class="line-clamp-3 text-[18px] font-bold leading-[1.45] text-spu-blue transition-colors group-hover:text-spu-red sm:text-[19px]">{{ $article->title }}</h2>
                            </a>
                            @if ($article->excerpt)
                                <p class="mt-4 line-clamp-3 text-[14px] font-medium leading-7 text-slate-600">{{ $article->excerpt }}</p>
                            @endif

                            <a href="{{ $article->url }}" class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-5 text-[12px] font-bold text-spu-blue transition-colors hover:text-spu-red">
                                <span>{{ $page['readMoreLabel'] }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 shrink-0 rtl:rotate-180" aria-hidden="true">
                            </a>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full rounded-lg bg-slate-50 p-8 text-center text-sm font-bold text-slate-500">{{ $page['emptyLabel'] }}</p>
                @endforelse
            </div>

            <x-public.pagination :current-page="$articles->currentPage" :total-pages="$articles->lastPage" :page-url="$articlePageUrl" :locale="$locale" class="mt-10" />
        </div>
    </section>
@endsection
