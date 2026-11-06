{{-- Dates, place, registration and calendar for an article in Events or Society's Events. --}}
@php
    $event = $article->event;
    $closesAt = $event->registrationClosesAt !== null
        ? \Carbon\CarbonImmutable::parse($event->registrationClosesAt)->locale($locale)->translatedFormat('j F Y H:i')
        : null;
@endphp

<section class="mx-auto mt-7 rounded-[6px] border border-slate-200 bg-slate-50 p-5 md:p-7" aria-labelledby="article-event-heading">
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="article-event-heading" class="text-[18px] font-bold text-spu-blue">{{ __('public.article_event.heading') }}</h2>
        <span class="rounded-[3px] bg-spu-blue px-2.5 py-1 text-[10px] font-bold text-white">{{ __('public.article_event.kinds.'.$event->kind) }}</span>
        @if ($event->isPast)
            <span class="rounded-[3px] bg-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-700">{{ __('public.article_event.past') }}</span>
        @elseif ($event->sessions !== [])
            <span class="rounded-[3px] bg-spu-red/10 px-2.5 py-1 text-[10px] font-bold text-spu-red">{{ __('public.article_event.upcoming') }}</span>
        @endif
    </div>

    @if ($event->sessions !== [])
        <h3 class="mt-5 text-[13px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ count($event->sessions) > 1 ? __('public.article_event.sessions') : __('public.article_event.when') }}</h3>
        <ol class="mt-3 grid gap-3">
            @foreach ($event->sessions as $session)
                <li class="rounded-[4px] bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <p class="text-[14px] font-bold text-spu-blue">
                            @if (count($event->sessions) > 1)
                                <span class="text-spu-red">{{ __('public.article_event.session_number', ['number' => $loop->iteration]) }}</span>
                                <span aria-hidden="true">·</span>
                            @endif
                            <time datetime="{{ $session->startsAt }}">{{ $session->dateLabel }}</time>
                        </p>
                        <p class="text-[13px] font-bold text-slate-600" dir="ltr" translate="no">{{ $session->timeLabel }}</p>
                    </div>
                    @if ($session->title)
                        <p class="mt-1 text-[13px] font-semibold text-slate-700" dir="auto">{{ $session->title }}</p>
                    @endif
                    @if ($session->location)
                        <p class="mt-1 text-[12px] text-slate-500" dir="auto">{{ __('public.article_event.place') }}: {{ $session->location }}</p>
                    @endif
                    @if ($session->speakers !== [])
                        <p class="mt-2 text-[12px] text-slate-600">
                            <span class="font-bold">{{ __('public.article_event.session_speakers') }}:</span>
                            @foreach ($session->speakers as $speaker)
                                @if ($speaker->profileUrl)
                                    <a href="{{ $speaker->profileUrl }}" class="font-semibold text-spu-blue underline-offset-2 hover:text-spu-red hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-red" dir="auto">{{ $speaker->name }}</a>
                                @else
                                    <span dir="auto">{{ $speaker->name }}</span>
                                @endif
                                @unless ($loop->last)<span aria-hidden="true">{{ $locale === 'ar' ? '،' : ',' }}</span>@endunless
                            @endforeach
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    <dl class="mt-5 grid gap-x-6 gap-y-4 text-[13px] sm:grid-cols-2">
        @if ($event->location || $event->mapUrl || $event->onlineUrl)
            <div>
                <dt class="font-bold text-slate-500">{{ __('public.article_event.place') }}</dt>
                <dd class="mt-1 font-semibold text-slate-700">
                    @if ($event->location)<span dir="auto">{{ $event->location }}</span>@endif
                    @if ($event->mapUrl)
                        <a href="{{ $event->mapUrl }}" target="_blank" rel="noopener noreferrer" class="ms-2 text-spu-red hover:text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-red">{{ __('public.article_event.map') }}</a>
                    @endif
                    @if ($event->onlineUrl)
                        <a href="{{ $event->onlineUrl }}" target="_blank" rel="noopener noreferrer" class="ms-2 text-spu-red hover:text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-red">{{ __('public.article_event.online') }}</a>
                    @endif
                </dd>
            </div>
        @endif
        @if ($event->audience !== [] || $event->audienceNote)
            <div>
                <dt class="font-bold text-slate-500">{{ __('public.article_event.audience') }}</dt>
                <dd class="mt-1 font-semibold text-slate-700">
                    {{ collect($event->audience)->map(fn (string $audience): string => __('public.article_event.audiences.'.$audience))->implode($locale === 'ar' ? '، ' : ', ') }}
                    @if ($event->audienceNote)<span class="block font-medium text-slate-600" dir="auto">{{ $event->audienceNote }}</span>@endif
                </dd>
            </div>
        @endif
        @if ($event->organizer)
            <div>
                <dt class="font-bold text-slate-500">{{ __('public.article_event.organizer') }}</dt>
                <dd class="mt-1 font-semibold text-slate-700" dir="auto">{{ $event->organizer }}</dd>
            </div>
        @endif
        @if ($event->language)
            <div>
                <dt class="font-bold text-slate-500">{{ __('public.article_event.language') }}</dt>
                <dd class="mt-1 font-semibold text-slate-700">{{ __('public.article_event.languages.'.$event->language) }}</dd>
            </div>
        @endif
        @if ($event->hasCertificate)
            <div>
                <dt class="font-bold text-slate-500">{{ __('public.article_event.certificate') }}</dt>
                <dd class="mt-1 font-semibold text-slate-700" dir="auto">{{ $event->certificateNote ?? __('public.article_event.certificate_available') }}</dd>
            </div>
        @endif
    </dl>

    @if ($event->requiresRegistration || $event->calendarUrl)
        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
            @if ($event->registrationUrl)
                <a href="{{ $event->registrationUrl }}" class="rounded-[4px] bg-spu-red px-6 py-3 text-[13px] font-bold text-white transition hover:bg-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">{{ __('public.article_event.register') }}</a>
            @elseif ($event->requiresRegistration && ! empty($isPreview))
                <span class="text-[13px] font-bold text-slate-500">{{ __('public.article_event.preview_note') }}</span>
            @elseif ($event->registrationState === 'full')
                <span class="rounded-[4px] bg-slate-200 px-4 py-2 text-[13px] font-bold text-slate-700">{{ __('public.article_event.state_full') }}</span>
            @elseif ($event->registrationState === 'closed')
                <span class="rounded-[4px] bg-slate-200 px-4 py-2 text-[13px] font-bold text-slate-700">{{ __('public.article_event.state_closed') }}</span>
            @endif

            @if ($event->calendarUrl)
                <a href="{{ $event->calendarUrl }}" class="rounded-[4px] border border-spu-blue px-5 py-3 text-[13px] font-bold text-spu-blue transition hover:bg-spu-blue hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue" download>{{ __('public.article_event.add_to_calendar') }}</a>
            @endif

            @if ($event->registrationState === 'open')
                <div class="text-[12px] font-semibold text-slate-500">
                    @if ($event->seatsLeft !== null)
                        <p>{{ trans_choice('public.article_event.seats_left', $event->seatsLeft, ['count' => $event->seatsLeft]) }}</p>
                    @endif
                    @if (count($event->sessions) > 1)
                        <p>{{ __('public.article_event.register_one_for_all') }}</p>
                    @endif
                    @if ($closesAt)
                        <p>{{ __('public.article_event.registration_closes', ['date' => $closesAt]) }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</section>
