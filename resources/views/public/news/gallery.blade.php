@extends('layouts.public')

@section('content')
    @php
        $galleryQuery = array_filter(['category' => $activeCategory], fn (mixed $value): bool => is_string($value) && $value !== '');
        $galleryPageUrl = fn (int $pageNumber): string => '/'.$locale.'/news/gallery'.(($query = [...$galleryQuery, ...($pageNumber > 1 ? ['page' => $pageNumber] : [])]) !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    @endphp
    <section class="relative flex min-h-[285px] items-end overflow-hidden pt-24 font-hacen">
        <img src="{{ $page['heroImage'] }}" alt="{{ $page['title'] }}" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/65 to-spu-blue/20"></div>
        <div class="container relative z-10 pb-12 text-center text-white">
            <h1 class="text-4xl font-bold md:text-5xl">{{ $page['title'] }}</h1>
            <p class="mx-auto mt-4 max-w-3xl text-sm leading-7 text-white/82">{{ $page['summary'] }}</p>
        </div>
    </section>

    <section class="bg-white py-14 font-hacen md:py-20" x-data="newsGalleryViewer" x-on:keydown.escape.window="close" x-on:keydown.right.window="next" x-on:keydown.left.window="previous">
        <div class="container max-w-[1160px]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="/{{ $locale }}/news/gallery" class="flex flex-wrap gap-2">
                    <button type="submit" name="category" value="" class="rounded border px-4 py-2 text-xs font-bold {{ $activeCategory === null ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue' }}">{{ $page['allLabel'] }}</button>
                    @foreach ($page['categories'] as $category)
                        <button type="submit" name="category" value="{{ $category['id'] }}" class="rounded border px-4 py-2 text-xs font-bold {{ $activeCategory === $category['id'] ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue' }}">{{ $category['label'] }}</button>
                    @endforeach
                </form>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $page['latestLabel'] }}</span>
            </div>

            @php($galleryIndex = 0)
            @if ($featured)
                <button type="button" data-gallery-item data-gallery-index="{{ $galleryIndex++ }}" data-src="{{ $featured->imageUrl }}" data-alt="{{ $featured->altText }}" data-title="{{ $featured->title }}" data-caption="{{ $featured->caption }}" x-on:click="open" class="group relative mt-10 block h-[440px] w-full overflow-hidden rounded-2xl bg-slate-100 text-start shadow-sm" aria-label="{{ $page['openLabel'] }}: {{ $featured->title }}">
                    <img src="{{ $featured->imageUrl }}" alt="{{ $featured->altText }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]">
                    <span class="absolute inset-0 bg-gradient-to-t from-spu-blue/90 via-transparent to-transparent"></span>
                    <span class="absolute inset-x-0 bottom-0 p-7 text-white md:p-10"><span class="text-xs font-bold text-white/70">{{ $featured->categoryLabel }} · {{ $featured->dateLabel }}</span><strong class="mt-2 block text-2xl">{{ $featured->title }}</strong>@if($featured->caption)<span class="mt-2 block text-sm text-white/80">{{ $featured->caption }}</span>@endif</span>
                </button>
            @endif

            <div class="mt-7 grid gap-6 md:grid-cols-2">
                @forelse ($galleryItems->items as $item)
                    <button type="button" id="{{ $item->id }}" data-gallery-item data-gallery-index="{{ $galleryIndex++ }}" data-src="{{ $item->imageUrl }}" data-alt="{{ $item->altText }}" data-title="{{ $item->title }}" data-caption="{{ $item->caption }}" x-on:click="open" class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-start shadow-sm" aria-label="{{ $page['openLabel'] }}: {{ $item->title }}">
                        <span class="block h-64 overflow-hidden"><img src="{{ $item->imageUrl }}" alt="{{ $item->altText }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"></span>
                        <span class="block p-5"><span class="text-xs font-bold text-spu-red">{{ $item->categoryLabel }} · {{ $item->dateLabel }}</span><strong class="mt-2 block text-lg text-spu-blue">{{ $item->title }}</strong></span>
                    </button>
                @empty
                    @if (! $featured)<p class="col-span-full py-16 text-center text-slate-500">{{ $page['emptyLabel'] }}</p>@endif
                @endforelse
            </div>

            <x-public.pagination :current-page="$galleryItems->currentPage" :total-pages="$galleryItems->lastPage" :page-url="$galleryPageUrl" :locale="$locale" class="mt-10" />
        </div>

        <div x-cloak x-show="isOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-labelledby="gallery-viewer-title" x-ref="dialog" x-on:click.self="close" x-on:keydown.tab="trapFocus">
            <button type="button" x-ref="closeButton" x-on:click="close" class="absolute end-5 top-5 rounded-full bg-white/10 px-4 py-3 text-sm font-bold text-white" aria-label="{{ $page['closeLabel'] }}">×</button>
            <button type="button" x-on:click="previous" class="absolute start-5 rounded-full bg-white/10 px-4 py-3 text-white" aria-label="{{ $page['previousLabel'] }}">‹</button>
            <figure class="max-h-[90vh] max-w-6xl text-center text-white"><img x-bind:src="activeItem.src" x-bind:alt="activeItem.alt" class="max-h-[75vh] max-w-full object-contain"><figcaption class="mt-4"><strong id="gallery-viewer-title" class="text-lg" x-text="activeItem.title"></strong><span class="mt-1 block text-sm text-white/70" x-text="activeItem.caption"></span></figcaption></figure>
            <button type="button" x-on:click="next" class="absolute end-5 rounded-full bg-white/10 px-4 py-3 text-white" aria-label="{{ $page['nextLabel'] }}">›</button>
        </div>
    </section>
@endsection
