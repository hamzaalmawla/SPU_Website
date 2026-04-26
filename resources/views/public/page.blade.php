@extends('layouts.public')

@section('content')
    <div class="space-y-8">
        <nav aria-label="Breadcrumb" class="rounded-2xl border border-white/10 bg-slate-900/60 px-5 py-4 text-sm text-slate-300">
            <ol class="flex flex-wrap items-center gap-2">
                @foreach ($breadcrumbs->items as $item)
                    <li class="flex items-center gap-2">
                        @if (! $loop->first)
                            <span class="text-slate-500">/</span>
                        @endif

                        @if ($item->url)
                            <a href="{{ $item->url }}" class="transition hover:text-white">{{ $item->label }}</a>
                        @else
                            <span class="text-white">{{ $item->label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <section class="rounded-[2rem] border border-white/10 bg-slate-900/75 p-8 shadow-2xl shadow-sky-950/20">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-300">{{ $page['navigationLabel'] ?? $page['title'] }}</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $page['headline'] ?? $page['title'] }}</h1>
            @if ($page['subheadline'])
                <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-300">{{ $page['subheadline'] }}</p>
            @elseif ($page['excerpt'])
                <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-300">{{ $page['excerpt'] }}</p>
            @endif

            @if ($page['hero'])
                <div class="mt-8 grid gap-4 rounded-[1.5rem] border border-white/10 bg-white/5 p-6 lg:grid-cols-2">
                    <div>
                        @if (! empty($page['hero']['title']))
                            <h2 class="text-2xl font-semibold text-white">{{ $page['hero']['title'] }}</h2>
                        @endif
                        @if (! empty($page['hero']['summary']))
                            <p class="mt-3 text-base leading-7 text-slate-300">{{ $page['hero']['summary'] }}</p>
                        @endif
                    </div>

                    @if ($page['cta'] && ! empty($page['cta']['label']) && ! empty($page['cta']['url']))
                        <div class="flex items-center lg:justify-end">
                            <a href="{{ $page['cta']['url'] }}" class="rounded-full bg-sky-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-300">
                                {{ $page['cta']['label'] }}
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="grid gap-8 lg:grid-cols-[1.45fr,0.75fr]">
            <article class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-8">
                @if ($page['overviewCards'] !== [])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($page['overviewCards'] as $card)
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <h3 class="text-lg font-semibold text-white">{{ $card['title'] ?? '' }}</h3>
                                @if (! empty($card['summary']))
                                    <p class="mt-2 text-sm leading-6 text-slate-300">{{ $card['summary'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($page['stats'] !== [])
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        @foreach ($page['stats'] as $stat)
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-2xl font-semibold text-white">{{ $stat['value'] ?? '' }}</p>
                                <p class="mt-2 text-sm text-slate-300">{{ $stat['label'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 space-y-5 text-base leading-8 text-slate-200">
                    @foreach ($page['bodyBlocks'] as $block)
                        @if (($block['type'] ?? null) === 'legacy_html' && ! empty($block['content']))
                            <div class="prose prose-invert max-w-none prose-p:text-slate-200 prose-a:text-sky-300">{!! $block['content'] !!}</div>
                        @elseif (! empty($block['content']))
                            <p>{{ $block['content'] }}</p>
                        @endif
                    @endforeach

                    @if ($page['bodyBlocks'] === [] && $page['body'])
                        <p>{{ $page['body'] }}</p>
                    @endif
                </div>
            </article>

            <aside class="space-y-6">
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-400">{{ __('public.page_shell') }}</p>
                    <dl class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-4">
                            <dt>Slug</dt>
                            <dd class="text-white">{{ $page['slug'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt>Template</dt>
                            <dd class="text-white">{{ $page['template'] }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($page['cta'] && ! empty($page['cta']['label']) && ! empty($page['cta']['url']))
                    <div class="rounded-[2rem] border border-sky-300/20 bg-sky-400/10 p-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-sky-200">{{ __('public.call_to_action') }}</p>
                        <a href="{{ $page['cta']['url'] }}" class="mt-4 inline-flex rounded-full bg-sky-400 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-300">
                            {{ $page['cta']['label'] }}
                        </a>
                    </div>
                @endif

                @if ($page['sidebar'])
                    <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6 text-sm leading-7 text-slate-300">
                        @if (! empty($page['sidebar']['title']))
                            <h2 class="text-lg font-semibold text-white">{{ $page['sidebar']['title'] }}</h2>
                        @endif
                        @if (! empty($page['sidebar']['body']))
                            <p class="mt-3">{{ $page['sidebar']['body'] }}</p>
                        @endif
                    </div>
                @endif
            </aside>
        </section>
    </div>
@endsection
