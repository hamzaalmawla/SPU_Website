@extends('layouts.public')

@section('content')
    @php
        $content = $page->content;
        $isAr = $locale === 'ar';
        $tabs = array_values(array_filter(is_array($content['tabs'] ?? null) ? $content['tabs'] : [], fn ($tab) => is_array($tab) && trim((string) ($tab['body'] ?? '')) !== ''));
        $stats = array_values(array_filter(is_array($content['stats'] ?? null) ? $content['stats'] : [], fn ($stat) => is_array($stat) && trim((string) ($stat['value'] ?? '')) !== ''));
        $gallery = $page->center['gallery'];
        $manager = $page->manager;
        $accent = '#202759';
        $navigationBySlug = collect($page->navigation)->keyBy('slug');
        $highlights = collect(is_array($content['highlights'] ?? null) ? $content['highlights'] : [])
            ->filter(fn ($card) => is_array($card) && $navigationBySlug->has($card['subpage'] ?? null))
            ->map(fn (array $card): array => ['title' => (string) ($card['title'] ?? ''), 'link' => $navigationBySlug->get($card['subpage'])['url']])
            ->values();
        if ($highlights->isEmpty()) {
            $highlights = collect($page->navigation)->filter(fn (array $item) => $item['slug'] !== '')->map(fn (array $item): array => ['title' => $item['label'], 'link' => $item['url']])->values();
        }
        $highlightsTitle = (string) ($content['highlightsTitle'] ?? '');
        $staffPreview = array_slice($page->staff, 0, 4);
    @endphp

    @include('public.centers.partials.hero')

    @if ($tabs !== [])
        <section id="overview" class="bg-white py-16 font-hacen lg:py-24" dir="{{ $direction }}" x-data="{ activeTab: 0 }">
            <div class="container">
                <div class="flex flex-col items-start gap-12 lg:flex-row lg:gap-20">
                    @if ($gallery !== [])
                        <div class="w-full shrink-0 lg:w-[440px]">
                            <div class="grid grid-cols-2 gap-3">
                                @foreach (array_slice($gallery, 0, 4) as $image)
                                    <div class="{{ $loop->first ? 'col-span-2 aspect-[4/3]' : 'aspect-square' }} overflow-hidden rounded-[16px] bg-slate-100">
                                        <img src="{{ $image }}" alt="" class="h-full w-full object-cover" loading="lazy" aria-hidden="true">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        @if (count($tabs) > 1)
                            <div class="flex flex-wrap gap-3" role="tablist">
                                @foreach ($tabs as $index => $tab)
                                    <button type="button" role="tab" id="center-tab-{{ $index }}" aria-controls="center-panel-{{ $index }}"
                                            x-bind:aria-selected="activeTab === {{ $index }} ? 'true' : 'false'"
                                            @click="activeTab = {{ $index }}"
                                            class="rounded-full px-7 py-3 text-[15px] font-bold transition-all duration-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue"
                                            x-bind:class="activeTab === {{ $index }} ? 'bg-spu-blue text-white shadow-lg' : 'border border-slate-200 text-spu-blue'">{{ $tab['label'] ?? '' }}</button>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-8">
                            <div class="flex items-center gap-4">
                                <div class="h-[3px] w-10 shrink-0 rounded-full bg-spu-blue"></div>
                                <h2 class="text-[clamp(1.6rem,3.5vw,2.4rem)] font-bold leading-tight text-spu-blue">{{ count($tabs) > 1 ? $page->center['name'] : ($tabs[0]['label'] ?? $page->center['name']) }}</h2>
                            </div>
                            @foreach ($tabs as $index => $tab)
                                <div id="center-panel-{{ $index }}" role="tabpanel" aria-labelledby="center-tab-{{ $index }}" class="mt-6" x-show="activeTab === {{ $index }}" @if (! $loop->first) style="display: none;" @endif>
                                    <x-public.rich-text class="max-w-[680px] text-[18px] leading-[1.8] text-[#46464f]" :text="$tab['body'] ?? ''" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($manager !== null)
        <section id="manager" class="relative overflow-hidden bg-section py-16 font-hacen lg:py-24" dir="{{ $direction }}">
            <div class="container">
                <div class="flex flex-col items-center gap-12 lg:flex-row lg:gap-24" x-data="deanMessage" data-more-label="{{ __('public.show_more') }}" data-less-label="{{ __('public.show_less') }}">
                    <div class="relative w-full max-w-[460px] lg:w-1/2">
                        <div class="relative overflow-hidden rounded-[16px] bg-slate-100">
                            @if (! empty($manager['image']))
                                <img x-ref="photo" x-on:load="measure()" src="{{ $manager['image'] }}" alt="" class="w-full object-top">
                            @else
                                <div x-ref="photo" class="flex aspect-[4/5] items-center justify-center"><img src="/images/icon-user-graduate-outline.svg" alt="" class="h-24 w-24 opacity-30" aria-hidden="true"></div>
                            @endif
                            <div class="absolute bottom-[8%] rounded-md bg-white px-6 py-4 text-center text-[#1e2652] shadow-2xl shadow-black/10 ltr:left-4 rtl:right-4">
                                <h3 class="text-[22px] font-bold">
                                    <a href="{{ $manager['url'] }}" class="underline decoration-transparent underline-offset-4 transition-colors hover:text-spu-red hover:decoration-current focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">{{ $manager['name'] }}</a>
                                </h3>
                                <p class="text-[15px]">{{ $manager['title'] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="relative w-full lg:w-1/2">
                        <div class="mb-6 inline-flex items-center gap-3">
                            <div class="h-px w-10 bg-spu-blue"></div>
                            <span class="text-[12px] font-black uppercase tracking-[0.4em] text-spu-blue">{{ __('centers.manager_message') }}</span>
                        </div>
                        <h2 class="mb-8 text-3xl font-bold leading-tight text-slate-900 md:text-5xl">
                            <span>{{ __('centers.message_from') }}</span>
                            <span class="text-spu-blue">{{ $manager['title'] }}</span>
                        </h2>
                        @if ($manager['message'] !== '')
                            <div x-ref="text" id="manager-message-text" class="relative overflow-hidden" x-bind:style="textStyle()">
                                <x-public.rich-text class="text-base leading-7 text-[#475467] md:text-lg md:leading-8" :text="$manager['message']" />
                                <div x-show="isCollapsed()" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-[#fafcff] to-transparent" aria-hidden="true"></div>
                            </div>
                            <button type="button" x-show="overflowing" x-cloak x-on:click="toggle()" x-bind:aria-expanded="ariaExpanded()" aria-controls="manager-message-text" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4" x-text="toggleLabel()">{{ __('public.show_more') }}</button>
                        @else
                            <div x-ref="text"></div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($stats !== [])
        <section id="stats" class="relative z-20 bg-spu-blue font-hacen" dir="{{ $direction }}">
            <div class="container">
                <div class="cms-grid-stats {{ count($stats) >= 4 ? 'cms-grid-stats-cols-4' : (count($stats) === 3 ? 'cms-grid-stats-cols-3' : (count($stats) === 2 ? 'cms-grid-stats-cols-2' : '')) }} gap-4">
                    @foreach ($stats as $stat)
                        <div class="p-8 text-center md:p-10">
                            <div class="mb-1 text-3xl font-black text-white" dir="ltr">{{ $stat['value'] }}</div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/60 md:text-xs">{{ $stat['label'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($staffPreview !== [])
        <section id="staff" class="bg-white py-16 font-hacen lg:py-20" dir="{{ $direction }}">
            <div class="container">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-[28px] font-bold text-spu-blue md:text-[36px]">{{ $navigationBySlug->get('staff')['label'] ?? '' }}</h2>
                    <a href="{{ $navigationBySlug->get('staff')['url'] ?? '#' }}" class="group inline-flex items-center gap-2 text-sm font-bold text-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                        {{ __('centers.view_all_staff') }}
                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 transition-transform group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" aria-hidden="true">
                    </a>
                </div>
                @include('public.centers.partials.staff-grid', ['members' => $staffPreview])
            </div>
        </section>
    @endif

    @include('public.faculties.partials.navigation-section', [
        'navSectionId' => 'highlights',
        'navHeadingAr' => $highlightsTitle !== '' ? $highlightsTitle : $page->center['name'],
        'navHighlightAr' => '',
        'navHeadingEn' => $highlightsTitle !== '' ? $highlightsTitle : $page->center['name'],
        'navHighlightEn' => '',
        'navCards' => $highlights,
        'locale' => $locale,
    ])
@endsection
