{{-- The council template: the council head(s) and members, then the council's functions. Every council page under About › Councils uses it; all text comes from the council's CMS content. --}}
@once
    @push('styles')
        <style>
            .rc { --rc-blue: #202759; --rc-red: #6f1616; --rc-gold: #caa949; }
            .rc-section { padding: 3rem 0; }
            .rc-heading { margin: 0 auto 2rem; max-width: 760px; text-align: center; }
            .rc-heading h2 { margin: 0; color: var(--rc-blue); font-size: 1.35rem; font-weight: 800; }
            .rc-heading h2::after { content: ""; display: block; width: 56px; height: 3px; margin: .75rem auto 0; border-radius: 999px; background: var(--rc-red); }
            .rc-heading p { margin: .85rem 0 0; color: rgba(32, 39, 89, .65); font-size: .9rem; }
            .rc-heads, .rc-members { display: grid; gap: 1.25rem; margin: 0 auto; max-width: 1200px; }
            .rc-heads { grid-template-columns: repeat(auto-fit, minmax(280px, 420px)); justify-content: center; margin-bottom: 1.75rem; }
            .rc-members { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
            .rc-card { position: relative; display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: 1.75rem 1.25rem 1.5rem; border: 1px solid rgba(32, 39, 89, .1); border-radius: 1rem; background: #fff; text-align: center; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); transition: box-shadow .2s, transform .2s, border-color .2s; }
            .rc-card:hover { transform: translateY(-2px); border-color: rgba(111, 22, 22, .25); box-shadow: 0 12px 28px rgba(32, 39, 89, .1); }
            .rc-card--head { border-color: rgba(202, 169, 73, .55); background: linear-gradient(180deg, rgba(202, 169, 73, .09), #fff 55%); padding-top: 2.25rem; }
            .rc-avatar { display: flex; align-items: center; justify-content: center; width: 112px; height: 112px; margin-bottom: .6rem; overflow: hidden; border: 4px solid rgba(32, 39, 89, .08); border-radius: 999px; background: rgba(32, 39, 89, .05); color: rgba(32, 39, 89, .35); }
            .rc-card--head .rc-avatar { width: 136px; height: 136px; border-color: rgba(202, 169, 73, .6); }
            .rc-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .rc-avatar svg { width: 52%; height: 52%; }
            .rc-chip { display: inline-flex; align-items: center; padding: .2rem .7rem; border-radius: 999px; background: rgba(32, 39, 89, .07); color: var(--rc-blue); font-size: .72rem; font-weight: 800; }
            .rc-card--head .rc-chip { background: var(--rc-red); color: #fff; }
            .rc-name { margin: .25rem 0 0; color: var(--rc-blue); font-size: 1rem; font-weight: 800; line-height: 1.4; }
            .rc-card--head .rc-name { font-size: 1.15rem; }
            .rc-meta { color: var(--rc-red); font-size: .82rem; font-weight: 600; }
            .rc-sub { color: rgba(32, 39, 89, .65); font-size: .8rem; }
            .rc-email { color: rgba(32, 39, 89, .6); font-size: .75rem; word-break: break-all; }
            .rc-email:hover { color: var(--rc-red); }
            .rc-link { display: inline-flex; align-items: center; gap: .35rem; margin-top: .6rem; padding: .45rem 1rem; border-radius: .5rem; background: var(--rc-blue); color: #fff; font-size: .75rem; font-weight: 700; transition: background .2s; }
            .rc-link:hover, .rc-link:focus-visible { background: var(--rc-red); }
            .rc-functions { background: rgba(32, 39, 89, .03); }
            .rc-grid { display: grid; gap: 1.25rem; margin: 0 auto; max-width: 1200px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
            .rc-function { position: relative; display: flex; gap: 1rem; padding: 1.5rem; border: 1px solid rgba(32, 39, 89, .1); border-inline-start: 4px solid var(--rc-red); border-radius: .85rem; background: #fff; transition: box-shadow .2s, transform .2s; }
            .rc-function:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(32, 39, 89, .1); }
            .rc-function__number { flex: none; display: flex; align-items: center; justify-content: center; width: 2.6rem; height: 2.6rem; border-radius: .7rem; background: rgba(32, 39, 89, .07); color: var(--rc-blue); font-size: 1rem; font-weight: 800; }
            .rc-function h3 { margin: 0 0 .4rem; color: var(--rc-blue); font-size: 1rem; font-weight: 800; }
            .rc-function__text { color: rgba(32, 39, 89, .72); font-size: .88rem; line-height: 1.75; }
            @media (max-width: 640px) { .rc-grid { grid-template-columns: 1fr; } .rc-heads { grid-template-columns: 1fr; } }
            @media (prefers-reduced-motion: reduce) { .rc-card, .rc-function { transition: none; } .rc-card:hover, .rc-function:hover { transform: none; } }
        </style>
    @endpush
@endonce

@php
    /** @var \App\DTOs\Council\CouncilPageDTO $council */
    $personIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>';
@endphp

<div class="rc font-hacen" dir="{{ $council->direction }}">
    @if ($council->heads !== [] || $council->members !== [])
        <section class="rc-section bg-white" aria-labelledby="rc-council-title">
            <div class="container mx-auto px-6">
                <header class="rc-heading">
                    <h2 id="rc-council-title">{{ $council->membersTitle }}</h2>
                    @if ($council->membersSubtitle !== '')
                        <p>{{ $council->membersSubtitle }}</p>
                    @endif
                </header>

                @foreach ([['people' => $council->heads, 'isHead' => true], ['people' => $council->members, 'isHead' => false]] as $group)
                    @continue($group['people'] === [])
                    <div class="{{ $group['isHead'] ? 'rc-heads' : 'rc-members' }}">
                        @foreach ($group['people'] as $person)
                            <article class="rc-card {{ $group['isHead'] ? 'rc-card--head' : '' }}">
                                <span class="rc-avatar">
                                    @if ($person->image)
                                        <img src="{{ $person->image }}" alt="{{ $person->name }}" loading="lazy" decoding="async">
                                    @else
                                        {!! $personIcon !!}
                                    @endif
                                </span>
                                <span class="rc-chip">{{ $group['isHead'] ? $council->headLabel : $council->memberLabel }}</span>
                                <h3 class="rc-name" dir="auto">{{ $person->name }}</h3>
                                @if ($person->rank !== '')
                                    <span class="rc-meta" dir="auto">{{ $person->rank }}</span>
                                @endif
                                @if ($person->position !== '')
                                    <span class="{{ $person->rank !== '' ? 'rc-sub' : 'rc-meta' }}" dir="auto">{{ $person->position }}</span>
                                @endif
                                @if ($person->email !== '')
                                    <a href="mailto:{{ $person->email }}" class="rc-email">{{ $person->email }}</a>
                                @endif
                                @if ($person->url)
                                    <a href="{{ $person->url }}" class="rc-link">
                                        {{ $council->profileLabel }}
                                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($council->functions !== [])
        <section class="rc-section rc-functions" aria-labelledby="rc-functions-title">
            <div class="container mx-auto px-6">
                <header class="rc-heading">
                    <h2 id="rc-functions-title">{{ $council->functionsTitle }}</h2>
                    @if ($council->functionsSubtitle !== '')
                        <p>{{ $council->functionsSubtitle }}</p>
                    @endif
                </header>
                <div class="rc-grid">
                    @foreach ($council->functions as $item)
                        <article class="rc-function">
                            <span class="rc-function__number">{{ $loop->iteration }}</span>
                            <div>
                                <h3 dir="auto">{{ $item['title'] }}</h3>
                                <x-public.rich-text class="rc-function__text" :text="$item['description']" />
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
