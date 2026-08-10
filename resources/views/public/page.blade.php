@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        <section class="relative overflow-hidden bg-spu-blue pt-44 pb-20 text-white lg:pt-52">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 32px 32px;" aria-hidden="true"></div>
            <div class="container relative z-10">
                <nav aria-label="Breadcrumb" class="mb-8 text-sm font-semibold text-white/70">
                    <ol class="flex flex-wrap items-center gap-2">
                        @foreach ($breadcrumbs->items as $item)
                            <li class="flex items-center gap-2">
                                @if (! $loop->first)
                                    <span class="text-white/35">/</span>
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

                <p class="mb-4 text-sm font-bold uppercase tracking-[0.25em] text-spu-red">{{ $page['navigationLabel'] ?? $page['title'] }}</p>
                <h1 class="max-w-4xl text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">{{ $page['headline'] ?? $page['title'] }}</h1>
                @if ($page['subheadline'])
                    <p class="mt-6 max-w-3xl text-lg leading-8 text-white/75">{{ $page['subheadline'] }}</p>
                @elseif ($page['excerpt'])
                    <p class="mt-6 max-w-3xl text-lg leading-8 text-white/75">{{ $page['excerpt'] }}</p>
                @endif

                @if ($page['cta'] && ! empty($page['cta']['label']) && ! empty($page['cta']['url']))
                    <a href="{{ $page['cta']['url'] }}" class="mt-8 inline-flex items-center rounded-full bg-spu-red px-6 py-3 text-sm font-bold text-white shadow-[0_14px_32px_rgba(111,22,22,0.25)] transition hover:-translate-y-0.5">
                        {{ $page['cta']['label'] }}
                    </a>
                @endif
            </div>
        </section>

        <section class="py-16 lg:py-20">
            <div class="container grid gap-8 {{ $page['sidebar'] ? 'lg:grid-cols-[1.45fr,0.75fr]' : '' }}">
                <article class="rounded-[28px] bg-white p-8 shadow-[0_18px_48px_rgba(20,30,70,0.08)] ring-1 ring-slate-100">
                    @if ($page['hero'])
                        <div class="mb-8 rounded-[22px] bg-[#edf2fa] p-6">
                            @if (! empty($page['hero']['title']))
                                <h2 class="text-2xl font-bold text-spu-blue">{{ $page['hero']['title'] }}</h2>
                            @endif
                            @if (! empty($page['hero']['summary']))
                                <p class="mt-3 text-base leading-7 text-[#55627c]">{{ $page['hero']['summary'] }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($page['overviewCards'] !== [])
                        <div class="cms-grid-cards mb-8 gap-4">
                            @foreach ($page['overviewCards'] as $card)
                                <div class="rounded-[18px] border border-slate-100 bg-slate-50 p-5">
                                    <h3 class="text-lg font-bold text-spu-blue">{{ $card['title'] ?? '' }}</h3>
                                    @if (! empty($card['summary']))
                                        <p class="mt-2 text-sm leading-6 text-[#55627c]">{{ $card['summary'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($page['stats'] !== [])
                        @php($_statsCount = count($page['stats']))
                        @php($_statsCols = $_statsCount >= 4 ? 'cms-grid-stats-cols-4' : ($_statsCount === 3 ? 'cms-grid-stats-cols-3' : ($_statsCount === 2 ? 'cms-grid-stats-cols-2' : '')))
                        <div class="cms-grid-stats {{ $_statsCols }} mb-8 gap-4">
                            @foreach ($page['stats'] as $stat)
                                <div class="rounded-[18px] bg-spu-blue p-5 text-white">
                                    <p class="text-3xl font-black" translate="no">{{ $stat['value'] ?? '' }}</p>
                                    <p class="mt-2 text-sm text-white/70">{{ $stat['label'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="prose max-w-none prose-p:leading-8 prose-p:text-[#55627c] prose-a:font-bold prose-a:text-spu-red">
                        @foreach ($page['bodyBlocks'] as $block)
                            @if (($block['type'] ?? null) === 'legacy_html' && ! empty($block['content']))
                                {!! app(\App\Support\HtmlSanitizer::class)->sanitize($block['content']) !!}
                            @elseif (! empty($block['content']))
                                <p>{{ $block['content'] }}</p>
                            @endif
                        @endforeach

                        @if ($page['bodyBlocks'] === [] && $page['body'])
                            <p>{{ $page['body'] }}</p>
                        @endif
                    </div>
                </article>

                @if ($page['sidebar'])
                    <aside class="space-y-6">
                        <div class="rounded-[28px] bg-white p-6 shadow-[0_12px_32px_rgba(20,30,70,0.06)] ring-1 ring-slate-100">
                            @if (! empty($page['sidebar']['title']))
                                <h2 class="text-lg font-bold text-spu-blue">{{ $page['sidebar']['title'] }}</h2>
                            @endif
                            @if (! empty($page['sidebar']['body']))
                                <p class="mt-3 text-sm leading-7 text-[#55627c]">{{ $page['sidebar']['body'] }}</p>
                            @endif
                        </div>
                    </aside>
                @endif
            </div>
        </section>
    </div>
@endsection
