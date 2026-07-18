@extends('layouts.public')

@section('content')
    <section class="relative flex min-h-[285px] items-end overflow-hidden pt-24 font-hacen">
        <img src="{{ $page['heroImage'] }}" alt="{{ $page['calendarTitle'] }}" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-spu-blue/75"></div>
        <div class="container relative z-10 pb-12 text-center text-white">
            <h1 class="text-4xl font-bold">{{ $page['calendarTitle'] }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-white/80">{{ $page['summary'] }}</p>
        </div>
    </section>

    <section class="bg-section py-14 font-hacen md:py-20">
        <div class="container max-w-[1120px]">
            <form method="GET" action="/{{ $locale }}/news/events" class="mx-auto flex max-w-md items-end gap-3 rounded-xl bg-white p-5 shadow-sm">
                <label class="flex-1 text-sm font-bold text-spu-blue">
                    <span class="mb-2 block">{{ $page['calendarTitle'] }}</span>
                    <input type="month" name="month" value="{{ $month }}" class="w-full rounded border border-slate-200 px-3 py-2" required>
                </label>
                <button type="submit" class="rounded bg-spu-red px-5 py-2.5 text-sm font-bold text-white">{{ $page['detailsLabel'] }}</button>
            </form>

            <div class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between bg-spu-blue px-5 py-4 text-white">
                    <a href="/{{ $locale }}/news/events?month={{ $previousMonth }}" class="rounded border border-white/30 px-3 py-2 text-xs font-bold" aria-label="{{ __('public.previous') }}">{{ __('public.previous') }}</a>
                    <h2 class="text-lg font-bold">{{ $monthLabel }}</h2>
                    <a href="/{{ $locale }}/news/events?month={{ $nextMonth }}" class="rounded border border-white/30 px-3 py-2 text-xs font-bold" aria-label="{{ __('public.next') }}">{{ __('public.next') }}</a>
                </div>
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-[11px] font-bold text-spu-blue">
                    @foreach (($locale === 'ar' ? ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'] : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']) as $weekday)
                        <div class="px-1 py-3">{{ $weekday }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7">
                    @foreach ($days as $day)
                        <div class="min-h-24 border-b border-e border-slate-100 p-2 {{ $day['inMonth'] ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                            <time datetime="{{ $day['date'] }}" class="text-xs font-bold">{{ $day['day'] }}</time>
                            @foreach ($day['events'] as $event)
                                <a href="/{{ $locale }}/news/events-list#{{ $event->id }}" class="mt-2 block rounded bg-spu-red/10 px-2 py-1 text-[10px] font-bold leading-4 text-spu-red hover:bg-spu-red hover:text-white">{{ $event->title }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($events as $event)
                    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" class="h-48 w-full object-cover">
                        <div class="p-6">
                            <p class="text-xs font-bold text-spu-red">{{ $event->dateLabel }}</p>
                            <h2 class="mt-2 text-lg font-bold text-spu-blue">{{ $event->title }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $event->summary }}</p>
                            <div class="mt-4 space-y-1 text-xs text-slate-500">
                                <p>{{ $event->timeLabel }}</p>
                                <p>{{ $event->location }}</p>
                            </div>
                            <a href="/{{ $locale }}/news/events-list#{{ $event->id }}" class="mt-5 inline-flex text-sm font-bold text-spu-red">{{ $page['detailsLabel'] }}</a>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full py-14 text-center text-slate-500">{{ $page['emptyLabel'] }}</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
