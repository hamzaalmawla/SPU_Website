@extends('layouts.public')

@section('content')
    <section class="relative flex min-h-[285px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $page['heroImage'] }}" alt="{{ $page['pageTitle'] }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/64 to-spu-blue/12"></div>
        </div>

        <div class="container relative z-10 pb-12 text-center text-white">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74" aria-label="Breadcrumb">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ __('public.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/news" class="transition-colors hover:text-white">{{ __('public.news') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $page['pageTitle'] }}</span>
            </nav>
            <h1 class="text-[32px] font-bold leading-tight md:text-[44px]">{{ $page['pageTitle'] }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-white/82">{{ $page['pageDescription'] }}</p>
        </div>
    </section>

    <section class="bg-white py-14 font-hacen md:py-20">
        <div class="container max-w-[1120px]">
            @if ($featured)
                <article class="grid overflow-hidden rounded-xl border border-spu-red/20 bg-section shadow-[0_18px_45px_rgba(9,17,68,0.08)] lg:grid-cols-[0.9fr_1.1fr]">
                    <a href="{{ $featured->url }}" class="block min-h-[250px] overflow-hidden bg-slate-100">
                        <img src="{{ $featured->imageUrl ?: '/images/news/researches.jpeg' }}" alt="{{ $featured->title }}" class="h-full w-full object-cover transition duration-500 hover:scale-[1.03]">
                    </a>
                    <div class="flex flex-col justify-center p-7 lg:p-10">
                        <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-spu-red">{{ $page['featuredLabel'] }}</p>
                        @if ($featured->publishedAt)
                            <time class="mt-3 text-xs font-semibold text-slate-500" datetime="{{ $featured->publishedAt }}">{{ $featured->publishedAt }}</time>
                        @endif
                        <h2 class="mt-3 text-2xl font-bold leading-tight text-spu-blue">{{ $featured->title }}</h2>
                        @if ($featured->excerpt)
                            <p class="mt-4 text-sm leading-7 text-slate-600">{{ $featured->excerpt }}</p>
                        @endif
                        <a href="{{ $featured->url }}" class="mt-6 inline-flex w-fit items-center gap-2 text-sm font-bold text-spu-red transition hover:text-spu-blue">
                            <span>{{ $page['readMoreLabel'] }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                        </a>
                    </div>
                </article>
            @endif

            <form method="GET" action="/{{ $locale }}/news/announcements" class="mt-10 flex flex-wrap gap-2">
                <button type="submit" name="category" value="" class="rounded-[5px] border px-4 py-2 text-[11px] font-bold transition {{ $activeCategory === null ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue hover:border-spu-blue' }}">{{ $page['allCategoriesLabel'] }}</button>
                @foreach ($categories as $category)
                    <button type="submit" name="category" value="{{ $category->slug }}" class="rounded-[5px] border px-4 py-2 text-[11px] font-bold transition {{ $activeCategory === $category->slug ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue hover:border-spu-blue' }}">{{ $category->name }}</button>
                @endforeach
            </form>

            <div class="mt-8 divide-y divide-slate-100 border-y border-slate-100">
                @forelse ($announcements->items as $announcement)
                    <article class="grid gap-5 py-7 md:grid-cols-[130px_1fr_auto] md:items-center">
                        <div>
                            @if ($announcement->publishedAt)
                                <time class="text-sm font-bold text-spu-red" datetime="{{ $announcement->publishedAt }}">{{ $announcement->publishedAt }}</time>
                            @endif
                            @if ($announcement->category)
                                <p class="mt-2 text-[11px] font-semibold text-slate-500">{{ $announcement->category->name }}</p>
                            @endif
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-spu-blue"><a href="{{ $announcement->url }}" class="hover:text-spu-red">{{ $announcement->title }}</a></h2>
                            @if ($announcement->excerpt)
                                <p class="mt-2 text-sm leading-7 text-slate-600">{{ $announcement->excerpt }}</p>
                            @endif
                        </div>
                        <a href="{{ $announcement->url }}" class="inline-flex items-center gap-2 text-xs font-bold text-spu-red transition hover:text-spu-blue">
                            <span>{{ $page['readMoreLabel'] }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
                        </a>
                    </article>
                @empty
                    <p class="py-12 text-center text-sm text-slate-500">{{ $page['emptyState'] }}</p>
                @endforelse
            </div>

            @if ($announcements->lastPage > 1)
                <nav class="mt-10 flex items-center justify-center gap-3" aria-label="{{ __('public.pagination') }}">
                    @if ($announcements->currentPage > 1)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $announcements->currentPage - 1]) }}" class="rounded border border-slate-200 px-4 py-2 text-xs font-bold text-spu-blue">{{ __('public.previous') }}</a>
                    @endif
                    <span class="rounded bg-spu-red px-4 py-2 text-xs font-bold text-white">{{ $announcements->currentPage }} / {{ $announcements->lastPage }}</span>
                    @if ($announcements->currentPage < $announcements->lastPage)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $announcements->currentPage + 1]) }}" class="rounded border border-slate-200 px-4 py-2 text-xs font-bold text-spu-blue">{{ __('public.next') }}</a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection
