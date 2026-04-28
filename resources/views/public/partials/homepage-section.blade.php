@switch($section->key)
    {{-- ═══════════════════════════════════════════════════════════════
         HERO SECTION
         ═══════════════════════════════════════════════════════════════ --}}
    @case('hero')
        <script>window.spuHeroImages = @json($section->payload->content['images'] ?? []);</script>

        <section x-data="heroSlider()"
                 class="home-hero relative overflow-hidden font-hacen bg-spu-blue text-white z-10">

            {{-- Background image slider --}}
            @foreach ($section->payload->content['images'] ?? [] as $img)
                <div x-show="currentIndex === {{ $loop->index }}"
                     x-transition:enter="transition ease-out duration-1000"
                     x-transition:enter-start="opacity-0 transform scale-105"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-1000"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0">
                    <img src="{{ $img }}" alt="{{ $section->payload->title }}" class="h-full w-full object-cover">
                </div>
            @endforeach

            {{-- Overlay + ambient (CSS-only) --}}
            <div class="home-hero__overlay" aria-hidden="true"></div>
            <div class="home-hero__ambient" aria-hidden="true"></div>

            <div class="container relative z-10">
                <div class="home-hero__inner">
                    <div class="home-hero__content">

                        @if ($section->payload->eyebrow)
                            <p class="home-hero__eyebrow">
                                <span class="home-hero__eyebrow-line" aria-hidden="true"></span>
                                <span>{{ $section->payload->eyebrow }}</span>
                            </p>
                        @endif

                        @if ($section->payload->badge)
                            <span class="home-hero__badge">{{ $section->payload->badge }}</span>
                        @endif

                        <h1 class="home-hero__title">{{ $section->payload->title }}</h1>

                        @if ($section->payload->subtitle)
                            <p class="home-hero__summary">{{ $section->payload->subtitle }}</p>
                        @endif

                        @if ($section->payload->summary)
                            <p class="home-hero__summary">{{ $section->payload->summary }}</p>
                        @elseif ($section->payload->body)
                            <p class="home-hero__summary">{{ $section->payload->body }}</p>
                        @endif

                        @if ($section->payload->primaryAction || $section->payload->secondaryAction)
                            <div class="home-hero__actions">
                                @if ($section->payload->primaryAction)
                                    <a href="{{ $section->payload->primaryAction->url }}"
                                       class="home-hero__primary-btn rounded-[8px]"
                                       @if ($section->payload->primaryAction->target) target="{{ $section->payload->primaryAction->target }}" rel="noreferrer" @endif>
                                        <span>{{ $section->payload->primaryAction->label }}</span>
                                        <img src="/images/icon-arrow-right-outline.svg" class="w-3.5 h-3.5 brightness-0 invert rtl:rotate-180" alt="">
                                    </a>
                                @endif

                                @if ($section->payload->secondaryAction)
                                    <a href="{{ $section->payload->secondaryAction->url }}"
                                       class="home-hero__secondary-btn rounded-[8px]"
                                       @if ($section->payload->secondaryAction->target) target="{{ $section->payload->secondaryAction->target }}" rel="noreferrer" @endif>
                                        <span>{{ $section->payload->secondaryAction->label }}</span>
                                    </a>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         STATS SECTIONS (hero_stats / bottom_stats)
         ═══════════════════════════════════════════════════════════════ --}}
    @case('hero_stats')
    @case('bottom_stats')
        <section x-data="statsCounter()"
                 class="stats-section relative z-20 w-full overflow-hidden font-hacen reveal scroll-mt-24">
            <div class="container">
                <div class="stats-shell reveal-item">

                    @if ($section->payload->title)
                        {{-- Title hidden to match frontend design where stats overlap hero --}}
                    @endif

                    @if ($section->payload->stats !== [])
                        <div class="stats-shell__grid">
                            @foreach ($section->payload->stats as $stat)
                                <article class="stats-card" style="--card-accent: #caa949;">
                                    <div class="stats-card__top">
                                        @if ($stat->icon)
                                            <div class="stats-icon-badge">
                                                <img src="{{ $stat->icon }}"
                                                     alt="{{ $stat->label }}"
                                                     class="h-7 w-7 object-contain brightness-0 text-white invert sm:h-8 sm:w-8">
                                            </div>
                                        @endif
                                    </div>

                                    <div class="stats-card__body">
                                        <div class="stats-card__value-row">
                                            @if ($stat->prefix)
                                                <span class="stats-card-value" translate="no">{{ $stat->prefix }}</span>
                                            @endif
                                            <span class="stats-card-value" data-value="{{ $stat->value }}" translate="no">0</span>
                                            @if ($stat->suffix)
                                                <span class="stats-card-plus">{{ $stat->suffix }}</span>
                                            @endif
                                        </div>

                                        <p class="stats-card-label">{{ $stat->label }}</p>

                                        @if ($stat->helperText)
                                            <p class="stats-card-summary">{{ $stat->helperText }}</p>
                                        @endif
                                    </div>

                                    <span class="stats-card-line" aria-hidden="true"></span>
                                </article>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         ACADEMIC FACULTIES
         ═══════════════════════════════════════════════════════════════ --}}
    @case('academic_faculties')
        <section x-data="facultiesSlider()"
                 class="mt-[120px] bg-white font-hacen relative">
            <div class="container relative">
                <div class="flex flex-col md:flex-row items-center gap-[52px] relative">

                    {{-- Left panel: dark blue card --}}
                    <div class="w-full relative md:w-[322px] h-[435px] text-center bg-[#1e2652] rounded-[24px] flex flex-col justify-center items-start text-white shrink-0 overflow-hidden group z-20 shadow-[0_30px_80px_rgba(17,26,63,0.18)]">
                        <div class="absolute inset-0 opacity-[0.15] z-0 animate-slow-pan"
                             style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
                        <div class="relative z-10 w-full px-6 flex flex-col h-full justify-center text-right">
                            <h2 class="text-[45px] text-center w-full font-bold leading-tight mb-12 transition-all duration-500">
                                {{ $section->payload->title }}
                            </h2>

                            @if ($section->payload->sectionAction)
                                <a href="{{ $section->payload->sectionAction->url }}"
                                   class="bg-white mx-auto absolute inset-x-0 bottom-10 w-[195px] h-[40px] text-spu-blue justify-center rounded-[10px] font-bold text-[16px] flex items-center gap-2 hover:bg-gray-100 transition-all shadow-lg group/btn overflow-hidden"
                                   @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>
                                    <span>{{ $section->payload->sectionAction->label }}</span>
                                    <img src="/images/icon-arrow-right-outline.svg"
                                         class="w-2.5 h-2.5 mt-1 transition-transform group-hover:translate-x-1 rtl:rotate-180"
                                         alt="">
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Right panel: slider --}}
                    <div class="flex-1 min-w-0 w-full relative">
                        {{-- Prev/Next buttons --}}
                        <div class="flex gap-3 absolute -top-20 z-50 rtl:left-0 ltr:right-0">
                            <button @click="slideFaculties('left')" type="button"
                                    class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                    aria-label="{{ __('public.previous') }}">
                                <img src="/images/icon-chevron-left-outline.svg"
                                     class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                            </button>
                            <button @click="slideFaculties('right')" type="button"
                                    class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                    aria-label="{{ __('public.next') }}">
                                <img src="/images/icon-chevron-right-outline.svg"
                                     class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                            </button>
                        </div>

                        {{-- Faculty cards track --}}
                        <div x-ref="facultiesTrack"
                             class="flex h-[390px] w-full snap-x snap-mandatory flex-nowrap gap-6 bg-transparent overflow-x-auto overflow-y-hidden no-scrollbar scroll-smooth overscroll-x-contain px-2 pb-5 items-start z-10">

                            @foreach ($section->payload->items as $item)
                                <article @mouseenter="activeFaculty = {{ $loop->index }}"
                                         @mouseleave="activeFaculty = null"
                                         :class="{
                                             'opacity-50 grayscale-[0.2]': activeFaculty !== null && activeFaculty !== {{ $loop->index }},
                                             'opacity-100 scale-[1.02] z-20 shadow-2xl border-transparent': activeFaculty === {{ $loop->index }},
                                             'opacity-100': activeFaculty === null
                                         }"
                                         class="faculty-card snap-start w-[292px] h-[380px] hover:cursor-pointer shrink-0 relative bg-white rounded-[24px] border border-gray-100 shadow-[0_10px_30px_rgba(0,0,0,0.03)] flex flex-col items-center text-center transition-all duration-300 group overflow-hidden">

                                    {{-- Accent top bar on hover --}}
                                    @if (! empty($item['accent']))
                                        <div class="absolute top-0 left-0 w-full h-0 group-hover:h-[6px] z-50 transition-all duration-300 ease-in-out"
                                             style="background-color: {{ $item['accent'] }};"></div>
                                    @endif

                                    {{-- Logo --}}
                                    @if (! empty($item['imageUrl']))
                                        <div class="relative w-[160px] h-[160px] mt-6 mb-4 flex items-center justify-center">
                                            <img src="{{ $item['imageUrl'] }}"
                                                 alt="{{ $item['title'] ?? '' }}"
                                                 class="relative z-10 w-[110px] h-[110px] object-contain transition-transform duration-500">
                                        </div>
                                    @endif

                                    {{-- Name --}}
                                    <div class="px-4 mb-4">
                                        <h3 class="text-[20px] font-bold leading-tight transition-colors duration-300 text-gray-800">
                                            {{ $item['title'] ?? '' }}
                                        </h3>
                                    </div>

                                    {{-- Metric badge (years) --}}
                                    @if (! empty($item['metric']))
                                        <div class="px-10 py-2.5 rounded-[8px] text-white font-bold text-[12px] mb-6 shadow-sm"
                                             @if (! empty($item['accent'])) style="background-color: {{ $item['accent'] }};" @endif>
                                            {{ $item['metric'] }}
                                        </div>
                                    @endif

                                    {{-- Learn more link --}}
                                    @if (! empty($item['action']['url']) && ! empty($item['action']['label']))
                                        <a href="{{ $item['action']['url'] }}"
                                           class="mt-auto mb-6 flex items-center gap-2 text-[13px] font-extrabold transition-all duration-300 group-hover:gap-3"
                                           @if (! empty($item['accent'])) style="color: {{ $item['accent'] }};" @endif
                                           @if (! empty($item['action']['target'])) target="{{ $item['action']['target'] }}" rel="noreferrer" @endif>
                                            <span>{{ $item['action']['label'] }}</span>
                                            <img src="/images/icon-arrow-right-outline.svg"
                                                 class="w-2.5 h-2.5 opacity-70 group-hover:opacity-100 transition-all rtl:rotate-180"
                                                 alt="">
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         CHOOSE YOUR PATH
         ═══════════════════════════════════════════════════════════════ --}}
    @case('choose_your_path')
        <section x-data="{
            slidePaths(direction) {
                const track = this.$refs.pathsTrack;
                if (!track) return;
                const firstCard = track.querySelector('.path-card');
                const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 292;
                const gap = 24;
                const step = Math.round(cardWidth + gap);
                track.scrollBy({ left: direction === 'right' ? step : -step, behavior: 'smooth' });
            }
        }" class="py-8 mt-[150px] relative font-hacen reveal" style="background-color: #EAF3FF40;">
            <div class="container relative">
                <div class="flex flex-col md:flex-row items-center gap-[52px] relative">

                    {{-- Left panel: dark blue card --}}
                    <div class="w-full relative md:w-[322px] h-[435px] text-center bg-[#1e2652] rounded-[24px] flex flex-col justify-center items-start text-white shrink-0 overflow-hidden group shadow-[0_30px_80px_rgba(17,26,63,0.18)] z-20">
                        <div class="absolute inset-0 opacity-[0.15] z-0 animate-slow-pan"
                             style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
                        <div class="relative z-10 w-full px-6 flex flex-col h-full justify-center text-right">
                            <h2 class="text-[45px] text-center w-full font-bold leading-tight mb-12 transition-all duration-500">
                                {{ $section->payload->title }}
                            </h2>
                            @if ($section->payload->sectionAction)
                                <a href="{{ $section->payload->sectionAction->url }}"
                                   class="bg-white mx-auto absolute inset-x-0 bottom-10 w-[195px] h-[40px] text-spu-blue justify-center rounded-[10px] font-bold text-[16px] flex items-center gap-2 hover:bg-gray-100 transition-all shadow-lg group/btn overflow-hidden">
                                    <span>{{ $section->payload->sectionAction->label }}</span>
                                    <img src="/images/icon-arrow-right-outline.svg"
                                         class="w-2.5 h-2.5 mt-2 transition-transform group-hover/btn:translate-x-1 rtl:rotate-180" alt="">
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Right panel: path cards slider --}}
                    <div class="flex-1 min-w-0 w-full relative">
                        <div class="flex gap-3 absolute -top-26 z-50 rtl:left-0 ltr:right-0">
                            <button type="button" @click="slidePaths('left')"
                                    class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                    aria-label="{{ __('public.previous') }}">
                                <img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                            </button>
                            <button type="button" @click="slidePaths('right')"
                                    class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                    aria-label="{{ __('public.next') }}">
                                <img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                            </button>
                        </div>

                        <div x-ref="pathsTrack"
                             class="flex h-[390px] w-full snap-x snap-mandatory flex-nowrap gap-6 bg-transparent overflow-x-auto overflow-y-hidden no-scrollbar scroll-smooth overscroll-x-contain px-2 pt-2 pb-5 items-start z-10">
                            @foreach ($section->payload->items as $item)
                                <article class="path-card snap-start w-[292px] h-[380px] hover:cursor-pointer shrink-0 relative rounded-[28px] border border-gray-100 bg-white shadow-[0_15px_35px_rgba(20,30,70,0.06)] transition-all duration-300 group overflow-hidden">
                                    {{-- Front face --}}
                                    <div class="absolute inset-0 bg-white flex flex-col items-center justify-center p-8 transition-transform duration-500 ease-in-out group-hover:-translate-y-full">
                                        <div class="absolute top-0 left-0 w-full h-[6px] bg-spu-red"></div>
                                        @if (!empty($item['icon']))
                                            <div class="w-20 h-20 rounded-2xl bg-slate-50 text-spu-blue flex items-center justify-center mb-8 shadow-sm">
                                                <span class="block h-10 w-10 bg-current" aria-hidden="true"
                                                      style="-webkit-mask: url('{{ $item['icon'] }}') center / contain no-repeat; mask: url('{{ $item['icon'] }}') center / contain no-repeat;"></span>
                                            </div>
                                        @endif
                                        <h3 class="text-[26px] font-bold text-[#1e2652] leading-tight text-center">
                                            {{ $item['title'] ?? '' }}
                                        </h3>
                                    </div>

                                    {{-- Back face (hover) --}}
                                    <div class="absolute inset-0 bg-[#1e2652] text-white p-7 flex flex-col translate-y-full transition-transform duration-500 ease-in-out group-hover:translate-y-0">
                                        <h4 class="text-lg font-bold mb-6 opacity-90 border-b border-white/10 pb-2">
                                            {{ $item['title'] ?? '' }}
                                        </h4>
                                        @if (!empty($item['links']))
                                            <ul class="space-y-4 mb-6 flex-1">
                                                @foreach ($item['links'] as $link)
                                                    <li class="flex items-center gap-3 text-[14px] font-medium opacity-85 hover:opacity-100 transition-opacity">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-spu-red"></span>
                                                        <span>{{ $link }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if (!empty($item['action']['label']))
                                            <div class="mt-auto flex items-center justify-between pt-4 border-t border-white/10">
                                                <span class="text-sm font-bold">{{ $item['action']['label'] }}</span>
                                                <img src="/images/icon-arrow-right-outline.svg"
                                                     class="w-3.5 h-3.5 brightness-0 invert rtl:rotate-180" alt="">
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         ACHIEVEMENTS HIGHLIGHTS (Honor Panel)
         ═══════════════════════════════════════════════════════════════ --}}
    @case('achievements_highlights')
        <script>window.spuHonorItems = @json($section->payload->items);</script>

        <section x-data="honorPanel()"
                 class="py-16 bg-white font-hacen relative overflow-hidden reveal">
            <div class="container">
                {{-- Section header --}}
                <div class="flex items-end justify-between mb-12">
                    <div>
                        @if ($section->payload->eyebrow)
                            <p class="text-spu-red font-bold tracking-widest text-xs uppercase mb-2">
                                {{ $section->payload->eyebrow }}
                            </p>
                        @endif
                        <h2 class="text-3xl lg:text-4xl font-bold text-spu-blue">
                            {{ $section->payload->title }}
                        </h2>
                    </div>

                    <div class="flex gap-3">
                        <button @click="handleManual('prev')" type="button"
                                class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all">
                            <img src="/images/icon-chevron-left-outline.svg"
                                 class="w-4 h-4 rtl:rotate-180" alt="{{ __('public.previous') }}">
                        </button>
                        <button @click="handleManual('next')" type="button"
                                class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all">
                            <img src="/images/icon-chevron-right-outline.svg"
                                 class="w-4 h-4 rtl:rotate-180" alt="{{ __('public.next') }}">
                        </button>
                    </div>
                </div>

                {{-- 3-panel mosaic (fully Alpine-driven) --}}
                <div class="relative h-[500px] w-full">
                    <template x-for="(item, index) in items" :key="item.id || index">
                        <div class="absolute transition-all duration-[1800ms] [transition-timing-function:cubic-bezier(0.25,1,0.5,1)] rounded-[40px] overflow-hidden group"
                             :class="{
                                 'w-full lg:w-[65%] h-full z-30 left-0 top-0 opacity-100 shadow-2xl scale-100': getPos(index) === 0,
                                 'w-0 lg:w-[32%] h-[48%] z-20 left-0 lg:left-[68%] top-0 opacity-0 lg:opacity-100 scale-95 brightness-90': getPos(index) === 1,
                                 'w-0 lg:w-[32%] h-[48%] z-10 left-0 lg:left-[68%] top-[52%] opacity-0 lg:opacity-100 scale-90 brightness-75': getPos(index) === 2
                             }">

                            <img :src="item.image"
                                 :alt="item.title || ''"
                                 class="absolute inset-0 w-full h-full object-cover transition-transform duration-[3000ms] group-hover:scale-110">

                            {{-- Active panel overlay (position 0) --}}
                            <div x-show="getPos(index) === 0"
                                 x-transition:enter="transition ease-out duration-[1200ms] delay-[600ms]"
                                 x-transition:enter-start="opacity-0 translate-y-12"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/40 to-transparent flex flex-col justify-end p-10 text-white">

                                <div class="flex items-center gap-3 mb-4">
                                    <template x-if="item.typeTag">
                                        <span class="honor-panel-pill px-4 py-1 rounded-full bg-spu-red text-[10px] font-bold uppercase"
                                              x-text="item.typeTag"></span>
                                    </template>
                                    <template x-if="item.meta">
                                        <span class="text-white/70 text-sm" x-text="item.meta"></span>
                                    </template>
                                </div>

                                <h3 class="text-2xl lg:text-4xl font-bold mb-4" x-text="item.title"></h3>

                                <template x-if="item.summary">
                                    <p class="text-white/80 leading-relaxed mb-6 line-clamp-2" x-text="item.summary"></p>
                                </template>

                                <template x-if="item.action && item.action.url">
                                    <a :href="item.action.url"
                                       class="honor-panel-cta inline-flex items-center gap-3 font-bold group/link"
                                       x-bind:target="item.action.target || null"
                                       x-bind:rel="item.action.target ? 'noreferrer' : null">
                                        <span class="border-b-2 border-spu-red pb-1" x-text="item.action.label"></span>
                                        <img src="/images/icon-arrow-right-outline.svg"
                                             class="w-4 h-4 brightness-0 invert transition-transform group-hover/link:translate-x-2 rtl:rotate-180"
                                             alt="">
                                    </a>
                                </template>
                            </div>

                            {{-- Inactive panel click overlay --}}
                            <div x-show="getPos(index) !== 0"
                                 class="absolute inset-0 bg-black/30 hover:bg-black/10 transition-colors cursor-pointer"
                                 @click="handleManual('goto', index)"></div>
                        </div>
                    </template>
                </div>

                {{-- Dot navigation --}}
                <div class="flex justify-center gap-3 mt-10">
                    <template x-for="(item, index) in items" :key="'dot-' + (item.id || index)">
                        <button @click="handleManual('goto', index)"
                                class="h-2 rounded-full transition-all duration-1000"
                                :class="activeIndex === index ? 'w-10 bg-spu-red' : 'w-2 bg-slate-200'"
                                :aria-label="'Item ' + (index + 1)"></button>
                    </template>
                </div>
            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         UNIVERSITY NEWS
         ═══════════════════════════════════════════════════════════════ --}}
    @case('university_news')
        <section class="py-7.5 mt-[70px] bg-white font-hacen overflow-hidden reveal">
            <div class="container">

                {{-- Section header --}}
                <div class="flex items-center justify-between relative mb-10">
                    <h2 class="text-[42px] font-bold text-[#1e2652] tracking-tight">
                        {{ $section->payload->title }}
                    </h2>

                    @if ($section->payload->sectionAction)
                        <div class="flex items-center absolute top-4.5 gap-6 rtl:left-0 ltr:right-0">
                            <a href="{{ $section->payload->sectionAction->url }}"
                               class="bg-[#1e2652] text-white w-[195px] h-[40px] text-center justify-center rounded-[12px] text-sm font-bold flex items-center gap-3 hover:bg-opacity-90 transition-all"
                               @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>
                                <span>{{ $section->payload->sectionAction->label }}</span>
                            </a>
                        </div>
                    @endif
                </div>

                @if ($section->payload->articles !== [])
                    <div class="grid grid-cols-1 gap-8 pb-10 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($section->payload->articles as $article)
                            <article class="reveal-item w-full bg-white rounded-[25px] shadow-[0_15px_35px_rgba(0,0,0,0.06)] overflow-hidden flex flex-col group cursor-pointer transition-all duration-500 ease-out hover:-translate-y-2 hover:shadow-[0_25px_60px_rgba(0,0,0,0.12)]">

                                {{-- Image --}}
                                @if ($article->imageUrl)
                                    <div class="relative h-[210px] overflow-hidden">
                                        <img src="{{ $article->imageUrl }}"
                                             alt="{{ $article->title }}"
                                             class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-110">

                                        @if ($article->categoryLabel)
                                            <div class="absolute top-4 left-4 px-4 py-1 rounded-md text-white text-[11px] font-bold z-10 bg-spu-blue">
                                                {{ $article->categoryLabel }}
                                            </div>
                                        @endif

                                        <div class="absolute bottom-0 left-0 w-full h-[3px] bg-spu-red opacity-80 z-10 transition-transform duration-500 group-hover:scale-x-110"></div>
                                    </div>
                                @endif

                                {{-- Content --}}
                                <div class="p-6 text-center flex flex-col flex-1">
                                    <h3 class="text-[22px] font-bold text-[#1B1B1F] mb-1 leading-tight">
                                        {{ $article->title }}
                                    </h3>

                                    @if ($article->publishedAt)
                                        <p class="text-spu-red text-[15px] mb-3" translate="no">{{ $article->publishedAt }}</p>
                                    @endif

                                    @if ($article->excerpt)
                                        <p class="text-gray-700 text-[14px] leading-[1.6] line-clamp-3">
                                            {{ $article->excerpt }}
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         RESEARCH STUDIES
         ═══════════════════════════════════════════════════════════════ --}}
    @case('research_studies')
        <section x-data="researchSlider()"
                 class="py-7.5 mt-[70px] bg-section font-hacen relative overflow-hidden reveal"
                 style="content-visibility: auto; contain-intrinsic-size: auto 500px;">
            <div class="container">

                {{-- Header --}}
                <div class="section-header relative">
                    <h2 class="section-header__title text-[42px] font-bold text-spu-blue flex items-center gap-4 rtl:flex-row-reverse rtl:text-right ltr:text-left">
                        {{ $section->payload->title }}
                    </h2>

                    <div class="flex gap-3 absolute top-0 z-50 rtl:left-0 ltr:right-0">
                        @if ($section->payload->sectionAction)
                            <a href="{{ $section->payload->sectionAction->url }}"
                               class="bg-[#1e2652] text-white w-[195px] h-[40px] text-center justify-center rounded-[12px] text-sm font-bold flex items-center gap-3 hover:bg-opacity-90 transition-all"
                               @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>
                                <span>{{ $section->payload->sectionAction->label }}</span>
                            </a>
                        @endif

                        <button type="button" @click="slide('left')"
                                class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                aria-label="{{ __('public.previous') }}">
                            <img src="/images/icon-chevron-left-outline.svg"
                                 class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                        </button>
                        <button type="button" @click="slide('right')"
                                class="slider-nav-btn w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all"
                                aria-label="{{ __('public.next') }}">
                            <img src="/images/icon-chevron-right-outline.svg"
                                 class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                        </button>
                    </div>
                </div>

                {{-- Research cards track --}}
                @if ($section->payload->researchItems !== [])
                    <div x-ref="researchTrack"
                         class="flex gap-8 overflow-x-auto no-scrollbar scroll-smooth pb-10"
                         style="will-change: scroll-position;">

                        @foreach ($section->payload->researchItems as $item)
                            <article class="reveal-item research-card w-[289px] h-[348.03px] shrink-0 relative bg-white rounded-[25px] shadow-[0_10px_30px_rgba(0,0,0,0.05)] overflow-hidden flex flex-col group"
                                     style="transform: translateZ(0);">

                                {{-- Image + category tag --}}
                                @if ($item->imageUrl)
                                    <div class="relative h-[50%] overflow-hidden bg-gray-100">
                                        <img src="{{ $item->imageUrl }}"
                                             alt="{{ $item->title }}"
                                             loading="lazy" decoding="async" width="289" height="174"
                                             class="w-full h-full object-cover"
                                             style="transform: translateZ(0);">

                                        @if ($item->categoryLabel)
                                            <div class="absolute top-4 left-4 px-4 py-1.5 rounded-lg text-white text-[11px] font-bold bg-spu-blue">
                                                {{ $item->categoryLabel }}
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Text content --}}
                                <div class="px-4 pt-2 h-[40%] flex flex-col justify-between items-start flex-1 border-b-[3px] border-transparent group-hover:border-spu-red transition-colors duration-200">
                                    <div>
                                        <h3 class="text-[18px] font-bold text-spu-blue mb-1 leading-tight">
                                            {{ $item->title }}
                                        </h3>

                                        @if ($item->summary)
                                            <p class="text-gray-500 text-[14px] py-4 line-clamp-2">
                                                {{ $item->summary }}
                                            </p>
                                        @endif

                                        @if ($item->authors !== [])
                                            <p class="text-gray-400 text-[12px]">
                                                {{ implode(' • ', $item->authors) }}
                                            </p>
                                        @endif
                                    </div>

                                    @if ($item->url)
                                        <div class="mt-2 mb-2">
                                            <a href="{{ $item->url }}"
                                               class="research-card__action"
                                               @if (! empty($item->target)) target="{{ $item->target }}" rel="noreferrer" @endif>
                                                <span>{{ $item->title }}</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor" stroke-width="2.25" stroke-linecap="round"
                                                     stroke-linejoin="round"
                                                     class="w-4 h-4 transition-transform duration-200 ease-in-out rtl:rotate-180">
                                                    <path d="M9 6l6 6-6 6" />
                                                </svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         EVENTS AND ACTIVITIES
         ═══════════════════════════════════════════════════════════════ --}}
    @case('events_activities')
        <script>window.spuEventsData = @json($section->payload->events);</script>

        <section x-data="calendarApp()" x-init="startCarousel()"
                 class="overflow-hidden bg-white py-16 font-hacen lg:py-20">
            <div class="container">

                <h2 class="mb-8 text-[34px] font-bold tracking-tight text-[#1e2652] sm:mb-10 sm:text-[42px] lg:text-[52px]">
                    {{ $section->payload->title }}
                </h2>

                <div class="grid grid-cols-1 gap-10 xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)] xl:items-stretch">

                    {{-- Left panel: featured event card (Alpine-driven) --}}
                    <div @mouseenter="stopCarousel()" @mouseleave="startCarousel()"
                         class="overflow-hidden rounded-[20px] bg-white shadow-[0_18px_40px_rgba(0,0,0,0.22)] h-full flex flex-col relative">

                        <template x-if="selectedEvent">
                            <article :key="activeEventIndex"
                                     x-transition:enter="transition ease-out duration-500"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     class="flex flex-1 flex-col">

                                <div class="relative h-[220px] overflow-hidden md:h-[250px]">
                                    <img :src="selectedEvent.image"
                                         :alt="selectedEvent.title || ''"
                                         class="h-full w-full object-cover transition-transform duration-700 hover:scale-110">
                                    <div class="absolute inset-x-6 top-6 w-fit rounded-[10px] bg-[#27316d] px-7 py-2 text-[15px] font-bold text-white rtl:right-6 rtl:left-auto ltr:left-6 ltr:right-auto"
                                         x-text="selectedEvent.type"></div>
                                </div>

                                <div class="flex flex-1 flex-col bg-[#edf2fa] px-8 py-6 md:px-8 md:py-5">
                                    <p class="mb-5 text-[15px] font-bold text-[#c63030]" x-html="selectedEvent.dateText"></p>

                                    <h3 class="mb-3 text-xl font-bold text-[#1e2652]"
                                        x-text="selectedEvent.title"></h3>

                                    <p class="mb-6 text-[17px] leading-[1.65] text-[#55627c]"
                                       x-text="selectedEvent.description"></p>

                                    <a :href="selectedEvent.link || '#'"
                                       class="inline-flex w-fit items-center gap-2 text-[18px] font-bold text-[#1e2652] transition-all ease-in-out delay-75 hover:text-spu-red">
                                        <span x-text="selectedEvent.linkLabel || ''"></span>
                                        <img src="/images/icon-chevron-right-outline.svg"
                                             class="w-2.5 h-2.5 rtl:rotate-180" alt="">
                                    </a>

                                    {{-- Dot navigation --}}
                                    <div class="mt-auto pt-5">
                                        <div class="border-t border-[#9c2a2a]/20"></div>
                                        <div class="flex items-center justify-center gap-2 pt-7">
                                            <template x-for="(eventItem, index) in selectedDateEvents" :key="eventItem.id || index">
                                                <button type="button" @click="selectEvent(index)"
                                                        class="h-[8px] rounded-full transition-all duration-300"
                                                        :class="activeEventIndex === index ? 'w-[24px] bg-[#27316d]' : 'w-[8px] bg-[#d1d5de]'"
                                                        :aria-label="'View event ' + (index + 1)"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>

                        <template x-if="!selectedEvent">
                            <div class="flex min-h-[440px] flex-col items-center justify-center bg-[#edf2fa] px-10 text-center">
                                <p class="mb-4 text-[15px] font-bold text-[#c63030]" x-html="selectedDateLabel"></p>
                                <p class="max-w-[360px] text-[18px] leading-[1.7] text-[#55627c]"
                                   x-text="noEventsLabel"></p>
                            </div>
                        </template>
                    </div>

                    {{-- Right panel: calendar grid (Alpine-driven) --}}
                    <div class="rounded-[28px] bg-white px-6 py-7 shadow-[0_18px_40px_rgba(0,0,0,0.18)] sm:px-8 sm:py-8 lg:px-7 xl:min-h-[440px] xl:px-8 h-full">
                        {{-- Month/year header + nav --}}
                        <div class="mb-7 flex items-start justify-between gap-4">
                            <div class="flex items-end gap-5 text-[#1e2652]">
                                <span class="text-[21px] font-bold" x-text="monthLabel"></span>
                                <span class="text-[40px] font-black leading-none sm:text-[48px]" translate="no"
                                      x-text="viewDate.format('YYYY')"></span>
                            </div>

                            <div class="flex items-center gap-2 text-[#111111]">
                                <button type="button" @click="prevMonth()"
                                        class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-100">
                                    <img src="/images/icon-chevron-left-outline.svg"
                                         class="w-3.5 h-3.5 rtl:rotate-180" alt="{{ __('public.previous') }}">
                                </button>
                                <button type="button" @click="nextMonth()"
                                        class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-gray-100">
                                    <img src="/images/icon-chevron-right-outline.svg"
                                         class="w-3.5 h-3.5 rtl:rotate-180" alt="{{ __('public.next') }}">
                                </button>
                            </div>
                        </div>

                        {{-- Calendar day grid --}}
                        <div class="grid grid-cols-7 gap-y-3 sm:gap-y-4">
                            <template x-for="day in calendarDays" :key="day.date">
                                <button type="button" @click="selectDate(day.date)"
                                        class="relative mx-auto flex h-[50px] w-[50px] items-center justify-center rounded-[14px] transition-colors sm:h-[56px] sm:w-[56px]"
                                        :class="selectedDate === day.date ? 'bg-[#27316d]' : 'bg-transparent hover:bg-[#f5f7fc]'">
                                    <span class="text-[18px] font-semibold transition-colors sm:text-[20px]"
                                          :class="selectedDate === day.date ? 'text-white' : day.isCurrentMonth ? 'text-[#111111]' : 'text-[#d0d0d0]'"
                                          x-html="day.dayNumber"></span>
                                    <span x-show="day.hasEvent && selectedDate !== day.date"
                                          class="absolute bottom-[6px] left-1/2 h-[4px] w-[4px] -translate-x-1/2 rounded-full bg-[#27316d]"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                </div>

                {{-- Calendar highlights sidebar --}}
                <div class="mt-10">
                    @foreach ($section->payload->content['calendarHighlights'] ?? [] as $highlight)
                        <div class="inline-block rounded-2xl border border-slate-100 px-5 py-3 mr-4 mb-4">
                            @if (! empty($highlight['label']))
                                <p class="font-medium text-spu-blue">{{ $highlight['label'] }}</p>
                            @endif
                            @if (! empty($highlight['date']))
                                <p class="mt-1 text-sm text-slate-400">{{ $highlight['date'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

            </div>
        </section>
        @break

    {{-- ═══════════════════════════════════════════════════════════════
         MEDICAL FACILITIES & SERVICES
         ═══════════════════════════════════════════════════════════════ --}}
    @case('medical_facilities_services')
        <section x-data="statsCounter()"
                 class="py-16 bg-slate-50 font-hacen overflow-hidden reveal">
            <div class="container">

                <h2 class="text-4xl lg:text-5xl font-bold text-spu-blue mb-10">
                    {{ $section->payload->title }}
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-16">

                    {{-- Main card (col-span-7) --}}
                    @isset($section->payload->items[0])
                        @php($mainItem = $section->payload->items[0])
                        <article class="lg:col-span-7 bg-white rounded-[2rem] shadow-xl overflow-hidden flex flex-col group hover:shadow-2xl transition-all duration-500">
                            @if (! empty($mainItem['imageUrl']))
                                <div class="h-[350px] overflow-hidden relative">
                                    <img src="{{ $mainItem['imageUrl'] }}"
                                         alt="{{ $mainItem['title'] ?? '' }}"
                                         class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                </div>
                            @endif

                            <div class="p-8 flex-1 flex flex-col justify-between">
                                <div>
                                    @if (! empty($mainItem['title']))
                                        <h3 class="text-2xl font-bold text-spu-blue mb-4">{{ $mainItem['title'] }}</h3>
                                    @endif

                                    @if (! empty($mainItem['summary']))
                                        <p class="text-gray-600 leading-relaxed mb-6">{{ $mainItem['summary'] }}</p>
                                    @endif

                                    @if (! empty($mainItem['features']))
                                        <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                                            @foreach ($mainItem['features'] as $feature)
                                                <li class="flex items-center gap-3">
                                                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                                        <img src="/images/icons/check-circle.svg" class="w-3 h-3" alt="">
                                                    </div>
                                                    <span class="text-spu-blue font-medium text-sm">{{ $feature }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                @if (! empty($mainItem['action']['url']) && ! empty($mainItem['action']['label']))
                                    <a href="{{ $mainItem['action']['url'] }}"
                                       class="inline-flex items-center justify-center px-10 py-3 bg-spu-blue text-white rounded-xl font-bold hover:bg-spu-red transition-all self-start"
                                       @if (! empty($mainItem['action']['target'])) target="{{ $mainItem['action']['target'] }}" rel="noreferrer" @endif>
                                        <span>{{ $mainItem['action']['label'] }}</span>
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endisset

                    {{-- Side cards (col-span-5) --}}
                    @if (isset($section->payload->items[1]) || isset($section->payload->items[2]))
                        <div class="lg:col-span-5 flex flex-col gap-8">

                            {{-- Hospital card --}}
                            @isset($section->payload->items[1])
                                @php($hospitalItem = $section->payload->items[1])
                                <article class="bg-white rounded-[2rem] shadow-lg overflow-hidden flex flex-col h-1/2 group hover:shadow-xl transition-all">
                                    @if (! empty($hospitalItem['imageUrl']))
                                        <div class="h-48 overflow-hidden">
                                            <img src="{{ $hospitalItem['imageUrl'] }}"
                                                 alt="{{ $hospitalItem['title'] ?? '' }}"
                                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                        </div>
                                    @endif
                                    <div class="p-6">
                                        @if (! empty($hospitalItem['title']))
                                            <h4 class="text-xl font-bold text-spu-blue mb-2">{{ $hospitalItem['title'] }}</h4>
                                        @endif
                                        @if (! empty($hospitalItem['summary']))
                                            <p class="text-gray-500 text-sm line-clamp-2">{{ $hospitalItem['summary'] }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endisset

                            {{-- Dental card --}}
                            @isset($section->payload->items[2])
                                @php($dentalItem = $section->payload->items[2])
                                <article class="bg-white rounded-[2rem] shadow-lg overflow-hidden flex flex-col h-1/2 group hover:shadow-xl transition-all">
                                    @if (! empty($dentalItem['imageUrl']))
                                        <div class="h-48 overflow-hidden">
                                            <img src="{{ $dentalItem['imageUrl'] }}"
                                                 alt="{{ $dentalItem['title'] ?? '' }}"
                                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                        </div>
                                    @endif
                                    <div class="p-6">
                                        @if (! empty($dentalItem['title']))
                                            <h4 class="text-xl font-bold text-spu-blue mb-2">{{ $dentalItem['title'] }}</h4>
                                        @endif
                                        @if (! empty($dentalItem['action']['url']) && ! empty($dentalItem['action']['label']))
                                            <a href="{{ $dentalItem['action']['url'] }}"
                                               class="text-spu-blue font-bold text-sm flex items-center gap-2 hover:text-spu-red transition-colors mt-2"
                                               @if (! empty($dentalItem['action']['target'])) target="{{ $dentalItem['action']['target'] }}" rel="noreferrer" @endif>
                                                <span>{{ $dentalItem['action']['label'] }}</span>
                                                <img src="/images/icon-chevron-right-outline.svg"
                                                     class="w-2.5 h-2.5 rtl:rotate-180" alt="">
                                            </a>
                                        @endif
                                    </div>
                                </article>
                            @endisset

                        </div>
                    @endif

                </div>

                {{-- Stats bar --}}
                @if ($section->payload->stats !== [])
                    <div class="bg-spu-blue rounded-[8px] py-12 px-8 shadow-2xl relative overflow-hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 relative z-10">
                            @foreach ($section->payload->stats as $stat)
                                <div class="flex flex-col items-center justify-center text-center px-4">
                                    <div class="flex items-baseline mb-3">
                                        <span class="text-5xl lg:text-6xl font-bold text-white tracking-tighter stats-card-value"
                                              data-value="{{ $stat->value }}" translate="no">0</span>

                                        @if ($stat->suffix)
                                            <span class="text-3xl font-bold text-spu-red ml-1" translate="no">{{ $stat->suffix }}</span>
                                        @endif
                                    </div>
                                    <p class="text-[#799DD6] text-xs font-bold tracking-widest uppercase">{{ $stat->label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </section>
        @break

@endswitch
