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

            <div class="cms-grid-news mt-8 gap-7">
                @forelse ($articles->items as $article)
                    <article id="article-{{ $article->id }}" class="overflow-hidden rounded-[6px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md">
                        <a href="{{ $article->url }}" class="block">
                            <div class="relative aspect-[1.58] overflow-hidden bg-slate-100">
                                <img src="{{ $article->imageUrl ?: '/images/news/researches.jpeg' }}" onerror="this.onerror=null;this.src='/images/news/researches.jpeg'" alt="{{ $article->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                                @if ($article->category)
                                    <span class="absolute left-3 top-3 rounded-[3px] bg-white px-2.5 py-1 text-[10px] font-bold text-spu-blue shadow-sm rtl:left-auto rtl:right-3">{{ $article->category->name }}</span>
                                @endif
                            </div>
                        </a>

                        <div class="p-6">
                            <a href="{{ $article->url }}" class="block" aria-label="{{ $article->title }}">
                                <h2 class="text-[16px] font-bold leading-[21px] text-spu-blue">{{ $article->title }}</h2>
                            </a>
                            @if ($article->publishedAt)
                                <p class="mt-2 text-[11px] font-bold text-spu-red" translate="no">{{ $article->publishedAt }}</p>
                            @endif
                            @if ($article->excerpt)
                                <p class="mt-4 min-h-[68px] text-[13px] font-medium leading-[22px] text-slate-600">{{ $article->excerpt }}</p>
                            @endif

                            <a href="{{ $article->url }}" class="mt-5 inline-flex items-center gap-2 text-[12px] font-bold text-spu-red transition hover:text-spu-blue">
                                <span>{{ $page['readMoreLabel'] }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
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
