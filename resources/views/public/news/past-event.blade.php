@extends('layouts.public')

@section('content')
    <section class="bg-section pb-16 pt-32 font-hacen md:pb-24">
        <div class="container max-w-[1100px]">
            @if (! $event)
                <div class="mx-auto max-w-xl rounded-2xl bg-white p-10 text-center shadow-sm"><h1 class="text-2xl font-bold text-spu-blue">{{ $page['notFoundTitle'] }}</h1><p class="mt-3 text-sm text-slate-600">{{ $page['notFoundText'] }}</p><a href="/{{ $locale }}/news/events-list" class="mt-6 inline-flex rounded bg-spu-red px-6 py-3 text-sm font-bold text-white">{{ $page['backLabel'] }}</a></div>
            @else
                <article class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" class="content-media-image h-[360px] w-full">
                    <div class="p-7 md:p-10">
                        <p class="text-xs font-bold text-spu-red">{{ $event->categoryLabel }}</p>
                        <h1 class="mt-2 text-3xl font-bold text-spu-blue">{{ $event->title }}</h1>
                        <div class="mt-5 flex flex-wrap gap-4 text-sm text-slate-500"><span>{{ $event->dateLabel }}</span><span>{{ $event->timeLabel }}</span><span>{{ $event->location }}</span>@if($event->participants)<span>{{ $event->participants }}</span>@endif</div>
                        <p class="mt-7 text-sm leading-8 text-slate-700">{{ $event->summary }}</p>

                        @if ($event->highlights !== [])
                            <h2 class="mt-10 text-xl font-bold text-spu-blue">{{ $page['highlightsLabel'] }}</h2>
                            <ul class="mt-4 grid gap-3 md:grid-cols-2">@foreach($event->highlights as $highlight)<li class="rounded bg-section px-4 py-3 text-sm text-slate-700">{{ $highlight }}</li>@endforeach</ul>
                        @endif
                        @if ($event->speakers !== [])
                            <h2 class="mt-10 text-xl font-bold text-spu-blue">{{ $page['speakersLabel'] }}</h2>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">@foreach($event->speakers as $speaker)<div class="rounded border border-slate-200 p-5"><h3 class="font-bold text-spu-blue">{{ $speaker['name'] }}</h3><p class="mt-1 text-sm text-slate-500">{{ $speaker['title'] }}</p></div>@endforeach</div>
                        @endif
                        @if ($event->results)
                            <h2 class="mt-10 text-xl font-bold text-spu-blue">{{ $page['resultsLabel'] }}</h2><p class="mt-4 rounded bg-section p-5 text-sm leading-7 text-slate-700">{{ $event->results }}</p>
                        @endif
                        @if ($event->gallery !== [])
                            <h2 class="mt-10 text-xl font-bold text-spu-blue">{{ $page['galleryLabel'] }}</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">@foreach($event->gallery as $image)<a href="{{ $image }}" target="_blank" rel="noopener noreferrer"><img src="{{ $image }}" alt="{{ $event->title }}" loading="lazy" class="content-media-image h-52 w-full rounded-lg"></a>@endforeach</div>
                        @endif
                    </div>
                </article>
            @endif
        </div>
    </section>
@endsection
