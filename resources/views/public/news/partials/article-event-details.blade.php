{{-- Speakers, invitation, contact and results for an article event. --}}
@php($event = $article->event)

@if ($event->speakers !== [])
    <section class="mx-auto mt-9" aria-labelledby="article-event-speakers">
        <h2 id="article-event-speakers" class="mb-5 text-[20px] font-bold text-spu-blue">{{ __('public.article_event.speakers') }}</h2>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($event->speakers as $speaker)
                <li class="flex gap-4 rounded-[6px] border border-slate-200 bg-white p-4 shadow-sm">
                    @if ($speaker->photoUrl)
                        <img src="{{ $speaker->photoUrl }}" alt="{{ $speaker->name }}" loading="lazy" class="h-16 w-16 shrink-0 rounded-full object-cover">
                    @endif
                    <div class="min-w-0">
                        <h3 class="text-[14px] font-bold text-spu-blue" dir="auto">{{ $speaker->name }}</h3>
                        @if ($speaker->title)<p class="mt-1 text-[12px] font-semibold text-slate-600" dir="auto">{{ $speaker->title }}</p>@endif
                        @if ($speaker->organization)<p class="text-[12px] text-slate-500" dir="auto">{{ $speaker->organization }}</p>@endif
                        @if ($speaker->bio)<p class="mt-2 text-[12px] leading-5 text-slate-600" dir="auto">{{ $speaker->bio }}</p>@endif
                        @if ($speaker->profileUrl)
                            <a href="{{ $speaker->profileUrl }}" class="mt-2 inline-flex text-[12px] font-bold text-spu-red hover:text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-spu-red">{{ __('public.article_event.view_profile') }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if ($event->invitationImageUrl)
    <section class="mx-auto mt-9" aria-labelledby="article-event-invitation">
        <h2 id="article-event-invitation" class="mb-5 text-[20px] font-bold text-spu-blue">{{ __('public.article_event.invitation') }}</h2>
        <a href="{{ $event->invitationImageUrl }}" target="_blank" rel="noopener noreferrer" class="block max-w-[520px] overflow-hidden rounded-[6px] shadow-sm ring-1 ring-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red" aria-label="{{ __('public.article_event.view_invitation') }}">
            <img src="{{ $event->invitationImageUrl }}" alt="{{ __('public.article_event.invitation') }}: {{ $article->title }}" loading="lazy" class="h-auto w-full">
        </a>
    </section>
@endif

@if ($event->isPast && $event->results)
    <section class="mx-auto mt-9 rounded-[6px] bg-slate-50 p-6" aria-labelledby="article-event-results">
        <h2 id="article-event-results" class="text-[20px] font-bold text-spu-blue">{{ __('public.article_event.results') }}</h2>
        <x-public.rich-text class="mt-4 text-[14px] leading-7 text-slate-700" :text="$event->results" />
    </section>
@endif

@if ($event->contactEmail || $event->contactPhone)
    <section class="mx-auto mt-9 text-[13px] font-semibold text-slate-600">
        <h2 class="text-[16px] font-bold text-spu-blue">{{ __('public.article_event.contact') }}</h2>
        <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1">
            @if ($event->contactEmail)<a href="mailto:{{ $event->contactEmail }}" class="text-spu-red hover:text-spu-blue" dir="ltr">{{ $event->contactEmail }}</a>@endif
            @if ($event->contactPhone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $event->contactPhone) }}" class="text-spu-red hover:text-spu-blue" dir="ltr">{{ $event->contactPhone }}</a>@endif
        </p>
    </section>
@endif
