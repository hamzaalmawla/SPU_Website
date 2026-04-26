@switch($section->key)
    @case('hero')
        <section class="overflow-hidden rounded-[2rem] border border-sky-300/20 bg-slate-900/85 shadow-2xl shadow-sky-950/30">
            <div class="grid gap-8 lg:grid-cols-[1.25fr,0.75fr]">
                <div class="p-8 sm:p-10">
                    @if ($section->payload->eyebrow)
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-300">{{ $section->payload->eyebrow }}</p>
                    @endif

                    @if ($section->payload->badge)
                        <p class="mt-4 inline-flex rounded-full border border-sky-300/30 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-sky-100">{{ $section->payload->badge }}</p>
                    @endif

                    <h1 class="mt-5 max-w-4xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $section->payload->title }}</h1>

                    @if ($section->payload->subtitle)
                        <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-200">{{ $section->payload->subtitle }}</p>
                    @endif

                    @if ($section->payload->summary)
                        <p class="mt-4 max-w-3xl text-base leading-7 text-slate-300">{{ $section->payload->summary }}</p>
                    @elseif ($section->payload->body)
                        <p class="mt-4 max-w-3xl text-base leading-7 text-slate-300">{{ $section->payload->body }}</p>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($section->payload->primaryAction)
                            <a href="{{ $section->payload->primaryAction->url }}" @if ($section->payload->primaryAction->target) target="{{ $section->payload->primaryAction->target }}" @endif class="rounded-full bg-sky-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-300">
                                {{ $section->payload->primaryAction->label }}
                            </a>
                        @endif

                        @if ($section->payload->secondaryAction)
                            <a href="{{ $section->payload->secondaryAction->url }}" @if ($section->payload->secondaryAction->target) target="{{ $section->payload->secondaryAction->target }}" @endif class="rounded-full border border-white/15 px-5 py-3 font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                {{ $section->payload->secondaryAction->label }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="min-h-[18rem] border-t border-white/10 bg-slate-800/70 lg:min-h-full lg:border-l lg:border-t-0" @if ($section->payload->backgroundImageUrl) style="background-image: linear-gradient(rgba(15, 23, 42, 0.35), rgba(15, 23, 42, 0.8)), url('{{ $section->payload->backgroundImageUrl }}'); background-size: cover; background-position: center;" @endif>
                    <div class="flex h-full items-end p-8 text-sm text-slate-200">
                        @if ($section->payload->content['imageAlt'] ?? null)
                            <p>{{ $section->payload->content['imageAlt'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
        @break

    @case('hero_stats')
    @case('bottom_stats')
        <section class="rounded-[2rem] border border-white/10 bg-slate-900/75 p-8 shadow-2xl shadow-sky-950/20">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.'.$section->key) }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title }}</h2>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($section->payload->stats as $stat)
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <p class="text-3xl font-semibold text-white">{{ ($stat->prefix ?? '').$stat->value.($stat->suffix ?? '') }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $stat->label }}</p>
                        @if ($stat->helperText)
                            <p class="mt-2 text-xs text-slate-400">{{ $stat->helperText }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
        @break

    @case('academic_faculties')
    @case('achievements_highlights')
    @case('medical_facilities_services')
        <section class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.section_labels.'.$section->key) }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title }}</h2>
                    @if ($section->payload->subtitle)
                        <p class="mt-3 text-base leading-7 text-slate-300">{{ $section->payload->subtitle }}</p>
                    @endif
                </div>

                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="rounded-full border border-sky-300/30 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:bg-sky-300/10">
                        {{ $section->payload->sectionAction->label }}
                    </a>
                @endif
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($section->payload->items as $item)
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold text-white">{{ $item['title'] ?? '' }}</h3>
                            @if (($item['accent'] ?? null) || ($item['typeTag'] ?? null) || ($item['type_tag'] ?? null))
                                <span class="rounded-full border border-white/10 px-3 py-1 text-xs text-slate-300">{{ $item['typeTag'] ?? $item['type_tag'] ?? $item['accent'] }}</span>
                            @endif
                        </div>

                        @if (! empty($item['summary']))
                            <p class="mt-3 text-sm leading-7 text-slate-300">{{ $item['summary'] }}</p>
                        @endif

                        @if (! empty($item['metric']) || ! empty($item['dateLabel']) || ! empty($item['date_label']))
                            <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-400">
                                @if (! empty($item['metric']))
                                    <span>{{ $item['metric'] }}</span>
                                @endif
                                @if (! empty($item['dateLabel']) || ! empty($item['date_label']))
                                    <span>{{ $item['dateLabel'] ?? $item['date_label'] }}</span>
                                @endif
                            </div>
                        @endif

                        @php($itemAction = $item['action'] ?? null)
                        @if (is_array($itemAction) && ! empty($itemAction['label']) && ! empty($itemAction['url']))
                            <a href="{{ $itemAction['url'] }}" class="mt-5 inline-flex rounded-full border border-white/10 px-4 py-2 text-sm text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                {{ $itemAction['label'] }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($section->payload->stats !== [])
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($section->payload->stats as $stat)
                        <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <p class="text-3xl font-semibold text-white">{{ ($stat->prefix ?? '').$stat->value.($stat->suffix ?? '') }}</p>
                            <p class="mt-2 text-sm text-slate-300">{{ $stat->label }}</p>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($section->payload->items === [] && $section->payload->featuredItems !== [])
                <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($section->payload->featuredItems as $item)
                        <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <h3 class="text-lg font-semibold text-white">{{ $item->title }}</h3>
                            @if ($item->summary)
                                <p class="mt-3 text-sm leading-7 text-slate-300">{{ $item->summary }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
        @break

    @case('university_news')
        <section class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.section_labels.'.$section->key) }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title }}</h2>
                </div>

                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="rounded-full border border-sky-300/30 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:bg-sky-300/10">
                        {{ $section->payload->sectionAction->label }}
                    </a>
                @endif
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                @foreach ($section->payload->articles as $article)
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            @if ($article->categoryLabel)
                                <span>{{ $article->categoryLabel }}</span>
                            @endif
                            @if ($article->publishedAt)
                                <span>{{ $article->publishedAt }}</span>
                            @endif
                            @if ($article->badgeTag)
                                <span class="rounded-full border border-white/10 px-2 py-1">{{ $article->badgeTag }}</span>
                            @endif
                        </div>

                        <h3 class="mt-3 text-lg font-semibold text-white">{{ $article->title }}</h3>

                        @if ($article->excerpt)
                            <p class="mt-3 text-sm leading-7 text-slate-300">{{ $article->excerpt }}</p>
                        @endif

                        @if ($article->url)
                            <a href="{{ $article->url }}" class="mt-5 inline-flex rounded-full border border-white/10 px-4 py-2 text-sm text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                {{ $article->title }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
        @break

    @case('research_studies')
        <section class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.section_labels.'.$section->key) }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title }}</h2>
                </div>

                @if ($section->payload->sectionAction)
                    <a href="{{ $section->payload->sectionAction->url }}" class="rounded-full border border-sky-300/30 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:bg-sky-300/10">
                        {{ $section->payload->sectionAction->label }}
                    </a>
                @endif
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                @foreach ($section->payload->researchItems as $item)
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            @if ($item->categoryLabel)
                                <span>{{ $item->categoryLabel }}</span>
                            @endif
                            @if ($item->publishedAt)
                                <span>{{ $item->publishedAt }}</span>
                            @endif
                        </div>

                        <h3 class="mt-3 text-lg font-semibold text-white">{{ $item->title }}</h3>

                        @if ($item->summary)
                            <p class="mt-3 text-sm leading-7 text-slate-300">{{ $item->summary }}</p>
                        @endif

                        @if ($item->authors !== [])
                            <p class="mt-3 text-xs text-slate-400">{{ implode(' • ', $item->authors) }}</p>
                        @endif

                        @if ($item->url)
                            <a href="{{ $item->url }}" class="mt-5 inline-flex rounded-full border border-white/10 px-4 py-2 text-sm text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                {{ $item->title }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
        @break

    @case('events_activities')
        <section class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
            <div class="grid gap-6 lg:grid-cols-[1.35fr,0.65fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.section_labels.'.$section->key) }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white">{{ $section->payload->title }}</h2>

                    <div class="mt-6 grid gap-4">
                        @foreach ($section->payload->events as $event)
                            <article class="rounded-2xl border border-white/10 bg-white/5 p-5">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                    @if ($event->startsAt)
                                        <span>{{ $event->startsAt }}</span>
                                    @endif
                                    @if ($event->timeLabel)
                                        <span>{{ $event->timeLabel }}</span>
                                    @endif
                                    @if ($event->location)
                                        <span>{{ $event->location }}</span>
                                    @endif
                                </div>

                                <h3 class="mt-3 text-lg font-semibold text-white">{{ $event->title }}</h3>

                                @if ($event->summary)
                                    <p class="mt-3 text-sm leading-7 text-slate-300">{{ $event->summary }}</p>
                                @endif

                                @if ($event->url)
                                    <a href="{{ $event->url }}" class="mt-5 inline-flex rounded-full border border-white/10 px-4 py-2 text-sm text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                        {{ $event->title }}
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>

                <aside class="rounded-2xl border border-white/10 bg-white/5 p-5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.event_highlights') }}</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-300">
                        @foreach (($section->payload->content['calendarHighlights'] ?? []) as $highlight)
                            <div class="rounded-2xl border border-white/10 px-4 py-3">
                                <p class="font-medium text-white">{{ $highlight['label'] ?? '' }}</p>
                                @if (! empty($highlight['date']))
                                    <p class="mt-1 text-slate-400">{{ $highlight['date'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </aside>
            </div>
        </section>
        @break

@endswitch
