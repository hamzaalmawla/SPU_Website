@extends('layouts.public')

@section('content')
    <section class="relative flex min-h-[360px] items-end overflow-hidden pt-24 font-hacen">
        <img src="{{ $page['heroImage'] }}" alt="{{ $page['title'] }}" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/70 to-spu-blue/25"></div>
        <div class="container relative z-10 pb-12 text-center text-white">
            <h1 class="text-4xl font-bold md:text-5xl">{{ $page['title'] }}</h1>
            <p class="mx-auto mt-4 max-w-3xl text-sm leading-7 text-white/82">{{ $page['summary'] }}</p>
        </div>
    </section>

    <section class="bg-white py-14 font-hacen md:py-20">
        <div class="container max-w-[1180px]">
            <h2 class="text-3xl font-bold text-spu-blue">{{ $page['upcomingTitle'] }}</h2>
            <form method="GET" action="/{{ $locale }}/news/events-list" class="mt-6 flex flex-wrap gap-2">
                <button type="submit" name="category" value="" class="rounded border px-4 py-2 text-xs font-bold {{ $activeCategory === null ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue' }}">{{ $page['allCategoriesLabel'] }}</button>
                @foreach ($page['categories'] as $category)
                    <button type="submit" name="category" value="{{ $category['id'] }}" class="rounded border px-4 py-2 text-xs font-bold {{ $activeCategory === $category['id'] ? 'border-spu-red bg-spu-red text-white' : 'border-slate-200 text-spu-blue' }}">{{ $category['label'] }}</button>
                @endforeach
            </form>

            <div class="mt-10 grid gap-7 md:grid-cols-2">
                @forelse ($upcomingEvents as $event)
                    <article id="{{ $event->id }}" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" class="content-media-image h-60 w-full">
                        <div class="p-7">
                            <div class="flex items-center justify-between gap-3 text-xs font-bold"><span class="text-spu-red">{{ $event->categoryLabel }}</span><span class="text-emerald-700">{{ $page['freeLabel'] }}</span></div>
                            <h3 class="mt-3 text-xl font-bold text-spu-blue"><a href="{{ $event->detailUrl }}" class="transition hover:text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-red">{{ $event->title }}</a></h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $event->summary }}</p>
                            <div class="mt-5 grid gap-2 text-xs text-slate-500 sm:grid-cols-2"><span>{{ $event->dateLabel }}</span><span>{{ $event->timeLabel }}</span><span class="sm:col-span-2">{{ $event->location }}</span></div>
                            <div class="mt-6 flex items-center justify-between gap-4">
                                @if ($event->remainingCapacity !== null)
                                    <span class="text-xs font-semibold text-slate-500">{{ $event->remainingCapacity }} {{ $page['spotsLeftLabel'] }}</span>
                                @endif
                                @if ($event->registrationUrl)
                                    <a href="{{ $event->registrationUrl }}" class="rounded bg-spu-red px-5 py-2.5 text-xs font-bold text-white transition hover:bg-spu-blue">{{ $page['registerLabel'] }}</a>
                                @endif
                            </div>
                            <a href="{{ $event->detailUrl }}" class="mt-5 inline-flex text-xs font-bold text-spu-red hover:text-spu-blue">{{ $page['detailsLabel'] }}</a>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full py-12 text-center text-slate-500">{{ $page['emptyLabel'] }}</p>
                @endforelse
            </div>

            <h2 class="mt-20 text-3xl font-bold text-spu-blue">{{ $page['pastTitle'] }}</h2>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ($pastEvents as $event)
                    <a href="{{ $event->detailUrl }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-section">
                        <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" loading="lazy" class="content-media-image h-44 w-full">
                        <div class="p-5"><p class="text-xs font-bold text-spu-red">{{ $event->dateLabel }}</p><h3 class="mt-2 font-bold text-spu-blue">{{ $event->title }}</h3><span class="mt-4 inline-flex text-xs font-bold text-spu-red">{{ $page['detailsLabel'] }}</span></div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
