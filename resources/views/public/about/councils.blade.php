{{-- About › Councils: every shown council with published content, as a card linking to its page. A council added under Councils in the admin appears here once published. --}}
@extends('layouts.public')

@once
    @push('styles')
        <style @cspNonce>
            .cl-section { padding: 3.5rem 0 4.5rem; }
            .cl-grid { display: grid; gap: 1.5rem; margin: 0 auto; max-width: 1200px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
            .cl-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border: 1px solid rgba(32, 39, 89, .1); border-radius: 1rem; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); transition: box-shadow .2s, transform .2s, border-color .2s; }
            .cl-card:hover, .cl-card:focus-within { transform: translateY(-3px); border-color: rgba(111, 22, 22, .25); box-shadow: 0 16px 32px rgba(32, 39, 89, .12); }
            .cl-media { position: relative; height: 150px; background: linear-gradient(135deg, #202759, #3a438a); }
            .cl-media img { width: 100%; height: 100%; object-fit: cover; opacity: .55; }
            .cl-media::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(32, 39, 89, 0) 30%, rgba(32, 39, 89, .75)); }
            .cl-badge { position: absolute; inset-inline-start: 1.25rem; bottom: -1.5rem; z-index: 1; display: flex; align-items: center; justify-content: center; width: 3.25rem; height: 3.25rem; border: 3px solid #fff; border-radius: .9rem; background: #6f1616; color: #fff; box-shadow: 0 6px 14px rgba(111, 22, 22, .3); }
            .cl-badge svg { width: 1.6rem; height: 1.6rem; }
            .cl-body { display: flex; flex: 1; flex-direction: column; gap: .6rem; padding: 2.25rem 1.5rem 1.5rem; }
            .cl-title { margin: 0; color: #202759; font-size: 1.1rem; font-weight: 800; line-height: 1.5; }
            .cl-title a::after { content: ""; position: absolute; inset: 0; }
            .cl-title a:focus-visible { outline: none; }
            .cl-card:focus-within { outline: 2px solid #202759; outline-offset: 2px; }
            .cl-summary { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; color: rgba(32, 39, 89, .7); font-size: .88rem; line-height: 1.8; }
            .cl-head { display: flex; align-items: center; gap: .45rem; color: #6f1616; font-size: .82rem; font-weight: 700; }
            .cl-head span { color: rgba(32, 39, 89, .6); font-weight: 600; }
            .cl-meta { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: auto; padding-top: .75rem; }
            .cl-chip { display: inline-flex; align-items: center; padding: .25rem .75rem; border-radius: 999px; background: rgba(32, 39, 89, .06); color: #202759; font-size: .75rem; font-weight: 700; }
            .cl-more { display: inline-flex; align-items: center; gap: .4rem; margin-inline-start: auto; color: #6f1616; font-size: .8rem; font-weight: 800; }
            .cl-empty { margin: 0 auto; max-width: 560px; padding: 2.5rem 1.5rem; border: 1px dashed rgba(32, 39, 89, .2); border-radius: 1rem; color: rgba(32, 39, 89, .7); text-align: center; }
            @media (max-width: 640px) { .cl-grid { grid-template-columns: 1fr; } }
            @media (prefers-reduced-motion: reduce) { .cl-card { transition: none; } .cl-card:hover, .cl-card:focus-within { transform: none; } }
        </style>
    @endpush
@endonce

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

        <section class="cl-section bg-[#faf9fb]" dir="{{ $direction }}" aria-label="{{ $page->title }}">
            <div class="container mx-auto px-6">
                @if ($councils->isEmpty())
                    <p class="cl-empty">{{ __('councils.empty', [], $locale) }}</p>
                @else
                    <div class="cl-grid">
                        @foreach ($councils as $council)
                            <article class="cl-card reveal reveal-up">
                                <div class="cl-media" aria-hidden="true">
                                    @if ($council->image)
                                        <img src="{{ $council->image }}" alt="" loading="lazy" decoding="async">
                                    @endif
                                    <span class="cl-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    </span>
                                </div>
                                <div class="cl-body">
                                    <h2 class="cl-title" dir="auto"><a href="{{ $council->url }}">{{ $council->title }}</a></h2>
                                    @if ($council->summary !== '')
                                        <p class="cl-summary" dir="auto">{{ $council->summary }}</p>
                                    @endif
                                    @if ($council->headNames !== [])
                                        <p class="cl-head" dir="auto"><span>{{ __('councils.card.head', [], $locale) }}:</span> {{ implode('، ', $council->headNames) }}</p>
                                    @endif
                                    <div class="cl-meta">
                                        @if ($council->memberCount > 0)
                                            <span class="cl-chip">{{ __('councils.card.members', ['count' => $council->memberCount], $locale) }}</span>
                                        @endif
                                        @if ($council->functionCount > 0)
                                            <span class="cl-chip">{{ __('councils.card.functions', ['count' => $council->functionCount], $locale) }}</span>
                                        @endif
                                        <span class="cl-more" aria-hidden="true">
                                            {{ __('councils.card.open', [], $locale) }}
                                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180">
                                        </span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
