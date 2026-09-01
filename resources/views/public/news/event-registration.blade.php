@extends('layouts.public')

@section('content')
    <section class="bg-section pb-14 pt-32 font-hacen md:pb-20">
        <div class="container max-w-[1150px]">
            <h1 class="text-center text-3xl font-bold text-spu-blue">{{ $page['registrationTitle'] }}</h1>
            @if (! $event)
                <div class="mx-auto mt-12 max-w-xl rounded-2xl bg-white p-10 text-center shadow-sm">
                    <h2 class="text-xl font-bold text-spu-blue">{{ $page['notFoundTitle'] }}</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ $page['notFoundText'] }}</p>
                    <a href="/{{ $locale }}/news/events-list" class="mt-6 inline-flex rounded bg-spu-red px-6 py-3 text-sm font-bold text-white">{{ $page['backLabel'] }}</a>
                </div>
            @else
                <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]" x-data="dynamicFormShell()" data-form-id="{{ $event->formId }}" data-locale="{{ $locale }}" data-event-source="news-events" data-event-id="{{ $event->id }}" data-preview="{{ !empty($isPreview) ? '1' : '0' }}">
                    <div>
                        <article class="overflow-hidden rounded-2xl bg-white shadow-sm">
                            <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" class="content-media-image h-72 w-full">
                            <div class="p-7"><p class="text-xs font-bold text-spu-red">{{ $event->categoryLabel }}</p><h2 class="mt-2 text-2xl font-bold text-spu-blue">{{ $event->title }}</h2><p class="mt-4 text-sm leading-7 text-slate-600">{{ $event->summary }}</p><div class="mt-5 space-y-2 text-sm text-slate-500"><p>{{ $event->dateLabel }} · {{ $event->timeLabel }}</p><p>{{ $event->location }}</p></div></div>
                        </article>
                        <p class="mt-6 rounded-xl border border-spu-blue/10 bg-white p-5 text-sm leading-7 text-slate-600">{{ $page['registrationInfo'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-7 shadow-sm lg:sticky lg:top-24 lg:self-start">
                        @include('public.forms.dynamic-form')
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
