@extends('layouts.public')

@section('content')
    <section class="bg-section pb-14 pt-32 font-hacen md:pb-20">
        <div class="container max-w-[1150px]">
            <h1 class="text-center text-3xl font-bold text-spu-blue" dir="auto">{{ __('public.article_event.registration_title', ['title' => $article->title]) }}</h1>

            @if ($event->registrationState !== 'open')
                <div class="mx-auto mt-12 max-w-xl rounded-2xl bg-white p-10 text-center shadow-sm">
                    <h2 class="text-xl font-bold text-spu-blue">{{ __('public.article_event.not_open_title') }}</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">{{ $event->registrationState === 'full' ? __('public.article_event.state_full') : __('public.article_event.state_closed') }}</p>
                    <a href="{{ $article->url }}" class="mt-6 inline-flex rounded bg-spu-red px-6 py-3 text-sm font-bold text-white transition hover:bg-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">{{ __('public.article_event.back_to_event') }}</a>
                </div>
            @else
                <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]" x-data="dynamicFormShell()" data-form-id="{{ $event->formId }}" data-locale="{{ $locale }}" data-event-source="{{ \App\Contracts\News\NewsArticleEventServiceInterface::REGISTRATION_SOURCE }}" data-event-id="{{ $event->id }}" data-preview="{{ !empty($isPreview) ? '1' : '0' }}">
                    <div>
                        <article class="overflow-hidden rounded-2xl bg-white shadow-sm">
                            <img src="{{ $event->invitationImageUrl ?? $article->imageUrl ?? '/images/uni-main-place.JPG' }}" alt="{{ $article->title }}" class="content-media-image h-72 w-full">
                            <div class="p-7">
                                <p class="text-xs font-bold text-spu-red">{{ __('public.article_event.kinds.'.$event->kind) }}</p>
                                <h2 class="card-title mt-2 text-2xl font-bold text-spu-blue" dir="auto">{{ $article->title }}</h2>
                                @if ($article->excerpt)
                                    <x-public.rich-text class="mt-4 text-sm leading-7 text-slate-600" :text="$article->excerpt" />
                                @endif
                                <ul class="mt-5 space-y-2 text-sm text-slate-600">
                                    @foreach ($event->sessions as $session)
                                        <li>
                                            <time datetime="{{ $session->startsAt }}" class="font-semibold text-spu-blue">{{ $session->dateLabel }}</time>
                                            <span dir="ltr" translate="no">{{ $session->timeLabel }}</span>
                                            @if ($session->title)<span dir="auto">— {{ $session->title }}</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($event->location)
                                    <p class="mt-3 text-sm text-slate-500" dir="auto">{{ __('public.article_event.place') }}: {{ $event->location }}</p>
                                @endif
                            </div>
                        </article>
                        <div class="mt-6 rounded-xl border border-spu-blue/10 bg-white p-5 text-sm leading-7 text-slate-600">
                            @if ($event->seatsLeft !== null)
                                <p class="font-bold text-spu-blue">{{ trans_choice('public.article_event.seats_left', $event->seatsLeft, ['count' => $event->seatsLeft]) }}</p>
                            @endif
                            @if (count($event->sessions) > 1)
                                <p>{{ __('public.article_event.register_one_for_all') }}</p>
                            @endif
                            <a href="{{ $article->url }}" class="mt-2 inline-flex font-bold text-spu-red hover:text-spu-blue">{{ __('public.article_event.back_to_event') }}</a>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-white p-7 shadow-sm lg:sticky lg:top-24 lg:self-start">
                        @include('public.forms.dynamic-form', ['formKey' => $event->formId])
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
