@extends('layouts.public')

@section('content')
    @php
        $weekdays = $locale === 'ar'
            ? [
                ['short' => 'أحد', 'full' => 'الأحد'],
                ['short' => 'اثن', 'full' => 'الاثنين'],
                ['short' => 'ثلا', 'full' => 'الثلاثاء'],
                ['short' => 'أرب', 'full' => 'الأربعاء'],
                ['short' => 'خمي', 'full' => 'الخميس'],
                ['short' => 'جمع', 'full' => 'الجمعة'],
                ['short' => 'سبت', 'full' => 'السبت'],
            ]
            : [
                ['short' => 'Sun', 'full' => 'Sunday'],
                ['short' => 'Mon', 'full' => 'Monday'],
                ['short' => 'Tue', 'full' => 'Tuesday'],
                ['short' => 'Wed', 'full' => 'Wednesday'],
                ['short' => 'Thu', 'full' => 'Thursday'],
                ['short' => 'Fri', 'full' => 'Friday'],
                ['short' => 'Sat', 'full' => 'Saturday'],
            ];
    @endphp

    <section class="events-calendar-hero relative flex min-h-[300px] items-end overflow-hidden pt-24 font-hacen sm:min-h-[340px]">
        <img src="{{ $page['heroImage'] }}" alt="{{ $page['calendarTitle'] }}" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-b from-spu-blue/45 via-spu-blue/70 to-spu-blue/95"></div>
        <div class="container relative z-10 pb-10 text-center text-white sm:pb-14">
            <p class="events-calendar-eyebrow">{{ $locale === 'ar' ? 'أخبار وفعاليات' : 'News & Events' }}</p>
            <h1 class="mt-3 text-[clamp(2rem,7vw,3.75rem)] font-bold leading-tight">{{ $page['calendarTitle'] }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-white/80 sm:text-base">{{ $page['summary'] }}</p>
        </div>
    </section>

    <section class="events-calendar-section bg-section py-10 font-hacen sm:py-14 lg:py-20">
        <div class="container max-w-[1200px]">
            <form method="GET" action="/{{ $locale }}/news/events" class="events-calendar-filter">
                <label class="events-calendar-filter-field">
                    <span>{{ $page['calendarTitle'] }}</span>
                    <input type="month" name="month" value="{{ $month }}" required aria-label="{{ $page['calendarTitle'] }}">
                </label>
                <button type="submit" class="events-calendar-filter-submit">{{ $page['detailsLabel'] }}</button>
            </form>

            <div class="events-calendar-card mt-8 sm:mt-10">
                <div class="events-calendar-toolbar">
                    <a href="/{{ $locale }}/news/events?month={{ $previousMonth }}" class="events-calendar-nav" aria-label="{{ __('public.previous') }}">
                        <span aria-hidden="true">{{ $locale === 'ar' ? '→' : '←' }}</span>
                        <span>{{ __('public.previous') }}</span>
                    </a>
                    <h2>{{ $monthLabel }}</h2>
                    <a href="/{{ $locale }}/news/events?month={{ $nextMonth }}" class="events-calendar-nav" aria-label="{{ __('public.next') }}">
                        <span>{{ __('public.next') }}</span>
                        <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                    </a>
                </div>
                <div class="events-calendar-weekdays" aria-hidden="true">
                    @foreach ($weekdays as $weekday)
                        <div title="{{ $weekday['full'] }}"><span class="sm:hidden">{{ $weekday['short'] }}</span><span class="hidden sm:inline">{{ $weekday['full'] }}</span></div>
                    @endforeach
                </div>
                <div class="events-calendar-grid">
                    @foreach ($days as $day)
                        <div class="events-calendar-day {{ $day['inMonth'] ? 'is-current-month' : 'is-outside-month' }}">
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                            @foreach ($day['events'] as $event)
                                <a href="{{ $event->detailUrl }}" class="events-calendar-event" title="{{ $event->title }}">{{ $event->title }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="events-month-heading mt-10 sm:mt-14">
                <div>
                    <p class="events-calendar-eyebrow text-spu-red">{{ $locale === 'ar' ? 'ضمن الشهر المحدد' : 'Selected month' }}</p>
                    <h2>{{ $page['upcomingTitle'] }}</h2>
                </div>
                <span class="events-month-count">{{ $events->count() }}</span>
            </div>

            <div class="events-month-grid mt-5 sm:mt-7">
                @forelse ($events as $event)
                    <article class="events-month-card">
                        <div class="events-month-card-media">
                            <img src="{{ $event->imageUrl }}" alt="{{ $event->title }}" loading="lazy" class="content-media-image">
                            <p>{{ $event->dateLabel }}</p>
                        </div>
                        <div class="events-month-card-body">
                            <h3><a href="{{ $event->detailUrl }}">{{ $event->title }}</a></h3>
                            <p class="events-month-card-summary">{{ $event->summary }}</p>
                            <div class="events-month-card-meta">
                                <span>{{ $event->timeLabel }}</span>
                                <span>{{ $event->location }}</span>
                            </div>
                            <a href="{{ $event->detailUrl }}" class="events-month-card-link">{{ $page['detailsLabel'] }} <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span></a>
                        </div>
                    </article>
                @empty
                    <p class="events-calendar-empty">{{ $page['emptyLabel'] }}</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
