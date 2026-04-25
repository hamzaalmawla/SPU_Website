@extends('layouts.public')

@section('content')
    <div class="space-y-10">
        <section class="grid gap-6 lg:grid-cols-[1.35fr,0.95fr]">
            <div class="rounded-[2rem] border border-sky-300/20 bg-slate-900/80 p-8 shadow-2xl shadow-sky-950/30">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-300">{{ $heroSection?->payload->eyebrow ?? 'SPU' }}</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                    {{ $heroSection?->payload->title ?? ($locale === 'ar' ? 'الجامعة الخاصة السورية' : 'Syrian Private University') }}
                </h1>
                @if ($heroSection?->payload->summary)
                    <p class="mt-4 max-w-2xl text-lg leading-8 text-slate-300">{{ $heroSection->payload->summary }}</p>
                @elseif ($heroSection?->payload->body)
                    <p class="mt-4 max-w-2xl text-lg leading-8 text-slate-300">{{ $heroSection->payload->body }}</p>
                @endif

                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($heroSection?->payload->primaryAction)
                        <a href="{{ $heroSection->payload->primaryAction->url }}" class="rounded-full bg-sky-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-300">
                            {{ $heroSection->payload->primaryAction->label }}
                        </a>
                    @endif

                    @if ($heroSection?->payload->secondaryAction)
                        <a href="{{ $heroSection->payload->secondaryAction->url }}" class="rounded-full border border-white/15 px-5 py-3 font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5">
                            {{ $heroSection->payload->secondaryAction->label }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-slate-900/65 p-8">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">Public Runtime</p>
                <h2 class="mt-4 text-2xl font-semibold text-white">{{ $settings->defaultSeo->title }}</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">{{ $settings->defaultSeo->metaDescription }}</p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    @foreach ($bodySections as $section)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ str_replace('_', ' ', $section->key) }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-white">{{ $section->payload->title ?? ($locale === 'ar' ? $section->arabicTranslation->headline : $section->englishTranslation->headline) }}</h3>
                            @if ($section->payload->body)
                                <p class="mt-2 text-sm leading-6 text-slate-300">{{ $section->payload->body }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            @foreach ($bodySections as $section)
                <article class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ str_replace('_', ' ', $section->key) }}</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title ?? ($locale === 'ar' ? $section->arabicTranslation->headline : $section->englishTranslation->headline) }}</h2>
                        </div>
                        @if ($section->payload->primaryAction)
                            <a href="{{ $section->payload->primaryAction->url }}" class="rounded-full border border-sky-300/30 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:bg-sky-300/10">
                                {{ $section->payload->primaryAction->label }}
                            </a>
                        @endif
                    </div>

                    @if ($section->payload->summary)
                        <p class="mt-4 text-base leading-7 text-slate-300">{{ $section->payload->summary }}</p>
                    @elseif ($section->payload->body)
                        <p class="mt-4 text-base leading-7 text-slate-300">{{ $section->payload->body }}</p>
                    @endif

                    @if ($section->payload->stats !== [])
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            @foreach ($section->payload->stats as $stat)
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <p class="text-2xl font-semibold text-white">{{ $stat->value }}</p>
                                    <p class="mt-2 text-sm text-slate-300">{{ $stat->label }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($section->payload->featuredItems !== [])
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            @foreach ($section->payload->featuredItems as $item)
                                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <h3 class="text-lg font-semibold text-white">{{ $item->title }}</h3>
                                    @if ($item->summary)
                                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ $item->summary }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    </div>
@endsection
