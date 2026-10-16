@extends('layouts.public')

@section('content')
    @php($shareUrl = url($achievement->url))

    <section class="relative flex min-h-[285px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $achievement->image ?: '/images/slider-1.webp' }}" alt="" class="content-media-image content-media-image--dark h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/64 to-spu-blue/12"></div>
        </div>

        <div class="container relative z-10 pb-10 text-center text-white">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ __('public.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/achievements" class="transition-colors hover:text-white">{{ __('public.achievements.title') }}</a>
            </nav>
            <p class="text-[30px] font-bold leading-tight md:text-[40px]">{{ __('public.achievements.title') }}</p>
        </div>
    </section>

    <article class="bg-white py-8 font-hacen md:py-10">
        <div class="container">
            <header class="mx-auto text-center">
                @if ($achievement->typeTag)
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-spu-red">{{ $achievement->typeTag }}</p>
                @endif
                <h1 class="mt-3 text-[24px] font-bold leading-tight text-spu-blue md:text-[30px]">{{ $achievement->title }}</h1>
                @if ($achievement->summary)
                    <p class="mx-auto mt-3 max-w-3xl text-[13px] font-semibold leading-6 text-slate-500">{{ $achievement->summary }}</p>
                @endif

                @if ($achievement->publishedAt || $achievement->meta)
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[11px] font-bold uppercase tracking-[0.06em] text-slate-500">
                        @if ($achievement->publishedAt)
                            <span>{{ $achievement->publishedAt }}</span>
                        @endif
                        @if ($achievement->publishedAt && $achievement->meta)
                            <span class="h-1 w-1 rounded-full bg-slate-300" aria-hidden="true"></span>
                        @endif
                        @if ($achievement->meta)
                            <span>{{ $achievement->meta }}</span>
                        @endif
                    </div>
                @endif
            </header>

            @if ($achievement->image)
                <figure class="mt-7 w-full overflow-hidden rounded-[4px]">
                    <img src="{{ $achievement->image }}" alt="{{ $achievement->title }}" class="content-media-image h-[320px] w-full object-cover object-center md:h-[500px]">
                </figure>
            @endif

            <div class="mx-auto mt-8 text-[14px] font-medium leading-7 text-slate-700">
                @if ($achievement->body)
                    <div class="prose max-w-none prose-p:mb-5 prose-p:text-[14px] prose-p:font-medium prose-p:leading-7 prose-p:text-slate-700 prose-headings:text-spu-blue prose-a:text-spu-red prose-img:rounded-[4px]">
                        {!! $achievement->body !!}
                    </div>
                @endif

                @if ($achievement->link)
                    <a href="{{ $achievement->link['url'] }}" class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-spu-blue px-6 text-sm font-bold text-white transition hover:bg-opacity-90">
                        <span>{{ $achievement->link['label'] }}</span>
                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3.5 w-3.5 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                    </a>
                @endif
            </div>

            @if (count($achievement->categories) > 1)
                <ul class="mx-auto mt-8 flex flex-wrap gap-2" aria-label="{{ __('public.achievements.filter') }}">
                    @foreach ($achievement->categories as $category)
                        <li><a href="/{{ $locale }}/achievements?{{ http_build_query(['categories' => [$category->slug]]) }}" class="inline-flex rounded-full border border-slate-300 px-4 py-1.5 text-xs font-bold text-slate-600 transition hover:border-spu-red hover:text-spu-red">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            <footer class="mx-auto mt-9 flex flex-col gap-4 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <a href="/{{ $locale }}/achievements" class="inline-flex items-center gap-2 text-[12px] font-bold text-spu-blue transition hover:text-spu-red">
                    <img src="/images/icon-arrow-left-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                    <span>{{ __('public.achievements.all_achievements') }}</span>
                </a>

                <div x-data="newsShare" data-share-url="{{ $shareUrl }}" class="flex items-center justify-center gap-3">
                    <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ __('public.achievements.share') }}</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 transition hover:border-spu-blue" aria-label="{{ $locale === 'ar' ? 'مشاركة على فيسبوك' : 'Share on Facebook' }}">
                        <img src="/images/icon-facebook-outline.svg" alt="" class="h-3.5 w-3.5" aria-hidden="true">
                    </a>
                    <a href="https://t.me/share/url?url={{ urlencode($shareUrl) }}&text={{ urlencode($achievement->title) }}" target="_blank" rel="noopener noreferrer" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 transition hover:border-spu-blue" aria-label="{{ $locale === 'ar' ? 'مشاركة على تيليغرام' : 'Share on Telegram' }}">
                        <img src="/images/icon-telegram-outline.svg" alt="" class="h-3.5 w-3.5" aria-hidden="true">
                    </a>
                    <button type="button" x-on:click="copy" class="flex h-8 min-w-8 items-center justify-center rounded-full border border-slate-200 px-2 text-[10px] font-bold transition hover:border-spu-blue" aria-live="polite"><span x-text="copyLabel">{{ $locale === 'ar' ? 'نسخ الرابط' : 'Copy link' }}</span></button>
                </div>
            </footer>
        </div>
    </article>
@endsection
