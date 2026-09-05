@php
        $faculty = $page->faculty;
        $content = $page->content;
        $accent = $faculty['accentColor'] ?: '#202759';
        $accentHex = ltrim($accent, '#');
        $gallery = $content['gallery'] ?? [];
        $tabs = $content['tabs'] ?? [];
        $activeTab = $tabs[0] ?? null;
        $sections = $content['sections'] ?? [];
        $dean = $content['dean'] ?? [];
        $latestResearch = $content['latestResearch'] ?? [];
        $isAr = $locale === 'ar';
        $pageTitle = $content['title'] ?? $faculty['name'];
@endphp

    <section class="relative flex min-h-[600px] flex-col justify-center overflow-hidden font-hacen lg:min-h-[700px]">
        <img src="{{ $faculty['heroImage'] ?? '/images/uni-main-place.JPG' }}" alt="" aria-hidden="true" class="faculty-hero__image faculty-hero__image--ready absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0" style="background: linear-gradient({{ $isAr ? '270deg' : '90deg' }}, #{{ $accentHex }}d9 0%, #{{ $accentHex }}66 40%, rgba(0,0,0,0.15) 100%);"></div>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/20"></div>
        <div class="faculty-hero__grain" aria-hidden="true"></div>

        <div class="container relative z-10 pb-40 pt-40">
            <div class="faculty-hero__accent-line mb-8" style="background: linear-gradient(90deg, #fff 0%, #{{ $accentHex }}99 100%);"></div>
            <p class="faculty-hero__reveal faculty-hero__reveal--d1 text-sm font-bold uppercase tracking-[0.25em] text-white/70">
                {{ __('public.university_name') }}
            </p>
            <h1 class="faculty-hero__reveal faculty-hero__reveal--d2 mt-4 max-w-[800px] text-[clamp(2.2rem,5vw,3.8rem)] font-bold leading-[1.1] tracking-tight text-white">
                {{ $pageTitle }}
            </h1>
            <div class="faculty-hero__reveal faculty-hero__reveal--d3 mt-10 flex flex-wrap items-center gap-4">
                <a href="#overview" class="group inline-flex h-[54px] items-center gap-2.5 rounded-[6px] bg-white px-8 text-sm font-bold uppercase tracking-[1.4px] shadow-[0_4px_24px_rgba(0,0,0,0.12)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_8px_32px_rgba(0,0,0,0.18)]" style="color: {{ $accent }}">
                    <span>{{ __('public.explore_programs') }}</span>
                    <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 transition-transform duration-300 group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" aria-hidden="true">
                </a>
                <a href="/{{ $locale }}/admissions/how-to-apply" class="inline-flex h-[54px] items-center rounded-[6px] border border-white/30 bg-white/8 px-8 text-sm font-bold uppercase tracking-[1.4px] text-white backdrop-blur-md transition-all duration-300 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/15">
                    {{ __('public.admissions') }}
                </a>
            </div>
        </div>
    </section>

    <div class="relative z-30 -mt-[130px] flex flex-col items-center bg-transparent pb-6 md:-mt-[140px]">
        <div class="faculty-hero__logo-ring flex h-[200px] w-[200px] items-center justify-center rounded-full border-white bg-white md:h-[220px] md:w-[220px]">
            <img src="{{ $faculty['logoImage'] ?? '/images/logo-spu.png' }}" alt="{{ $faculty['title'] }}" class="h-full w-full object-cover transition-all duration-700">
        </div>
        <p class="mt-4 text-center text-2xl font-bold text-spu-blue">{{ $isAr ? ($faculty['nameAr'] ?? $faculty['name']) : ($faculty['nameEn'] ?? $faculty['name']) }}</p>
        <p class="mt-1 text-center text-[11px] font-bold uppercase tracking-[2px] text-slate-400">{{ 'FACULTY OF '.mb_strtoupper($faculty['nameEn'] ?? $faculty['name'], 'UTF-8') }}</p>
    </div>

    <div class="bg-white pb-10 pt-6 font-hacen">
        <div class="container">
            <div class="mx-auto max-w-[1100px] overflow-hidden rounded-lg bg-spu-blue shadow-[0_20px_25px_-5px_rgba(0,0,0,0.1),0_8px_10px_-6px_rgba(0,0,0,0.1)]">
                <div class="cms-grid-stats cms-grid-stats-cols-4">
                    @foreach (array_slice($page->stats, 0, 4) as $stat)
                        <div class="relative flex flex-col items-center gap-2 px-6 py-8 text-center">
                            @if (! $loop->first)
                                <div class="absolute left-0 top-1/2 hidden h-2/3 w-px -translate-y-1/2 bg-white/10 md:block" aria-hidden="true"></div>
                            @endif
                            <span class="text-3xl font-bold leading-none text-white" dir="ltr">{{ $stat['value'] ?? '' }}</span>
                            <span class="text-xs font-bold uppercase tracking-[1px] text-white/50">{{ $stat['label'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <section id="overview" class="bg-white py-16 font-hacen lg:py-24" x-data="{ activeTab: '{{ $tabs[0]['id'] ?? 'overview' }}' }">
        <div class="container">
            <div class="flex flex-col items-start gap-12 lg:flex-row lg:gap-20">
                <div class="relative hidden w-full shrink-0 lg:block lg:w-[440px]"
                     x-data="{
                         activeImage: '{{ $gallery[0] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG') }}',
                         activeIndex: '01'
                     }">
                    <div class="group relative aspect-square w-full overflow-hidden rounded-[20px]">
                        <img :src="activeImage"
                             src="{{ $gallery[0] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG') }}"
                             alt="{{ $faculty['title'] }}"
                             class="h-full w-full object-cover transition-transform duration-[5000ms] ease-out group-hover:scale-105">
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-32 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute bottom-6 left-7 z-20 flex items-baseline gap-1.5">
                            <span class="text-[28px] font-bold leading-none text-white" x-text="activeIndex">01</span>
                            <span class="text-xs font-bold text-white/40">/ {{ str_pad((string) max(count($gallery), 1), 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                    </div>
                    @if (count($gallery) > 1)
                        <div class="no-scrollbar mt-5 flex gap-3 overflow-x-auto pb-1">
                            @foreach ($gallery as $image)
                                @php
                                    $num = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                                @endphp
                                <button type="button"
                                        @click="activeImage = '{{ $image }}'; activeIndex = '{{ $num }}'"
                                        class="relative h-[64px] w-[88px] shrink-0 overflow-hidden rounded-xl cursor-pointer p-0 border-0 bg-transparent text-start transition-all duration-200"
                                        :class="activeImage === '{{ $image }}' ? 'ring-[2.5px] ring-offset-2 opacity-100' : 'opacity-40 hover:opacity-80'"
                                        :style="activeImage === '{{ $image }}' ? 'ring-color: {{ $accent }};' : ''"
                                        aria-label="{{ ($isAr ? 'الصورة ' : 'Image ').$loop->iteration }}">
                                    <img src="{{ $image }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
                                    <div x-show="activeImage === '{{ $image }}'" class="absolute inset-x-0 bottom-0 h-[3px]" style="background-color: {{ $accent }}"></div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap gap-3">
                        @foreach ($tabs as $tab)
                            <button type="button" @click="activeTab = '{{ $tab['id'] ?? 'overview' }}'" class="relative rounded-full px-7 py-3 text-[15px] font-bold transition-all duration-300" :style="activeTab === '{{ $tab['id'] ?? 'overview' }}' ? 'background-color: {{ $accent }}; color: #fff; box-shadow: 0 4px 16px {{ $accent }}33;' : 'background-color: transparent; color: {{ $accent }}; border: 1.5px solid #e2e8f0;'">{{ $tab['label'] ?? '' }}</button>
                        @endforeach
                    </div>

                    <div class="mt-10">
                        <div class="flex items-center gap-4">
                            <div class="h-[3px] w-10 shrink-0 rounded-full" style="background-color: {{ $accent }}"></div>
                            <h2 class="text-[clamp(1.6rem,3.5vw,2.4rem)] font-bold leading-tight" style="color: {{ $accent }}">{{ $pageTitle }}</h2>
                        </div>
                        <div class="mt-6 min-h-[140px] space-y-5">
                            @foreach ($tabs as $tab)
                                <div x-show="activeTab === '{{ $tab['id'] ?? 'overview' }}'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" @if (! $loop->first) style="display: none;" @endif>
                                    <p class="max-w-[680px] text-[20px] leading-[1.8] text-[#46464f]">{{ $tab['body'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-8 border-t border-slate-100 pt-6">
                            <a href="/{{ $locale }}/facilities/{{ $page->slug }}/overview" class="group inline-flex items-center gap-3 transition-all" style="color: {{ $accent }}">
                                <span class="text-base font-bold">{{ __('public.read_more') }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-2 rtl:rotate-180 rtl:group-hover:-translate-x-2" aria-hidden="true">
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (! empty($dean))
        <section id="dean" class="relative overflow-hidden bg-section font-hacen md:mt-16 lg:mt-24">
            <div class="absolute left-0 top-0 z-10 w-full bg-white md:h-[70px]"></div>
            <div class="container relative z-20">
                <div class="flex min-h-[600px] flex-col items-center gap-12 lg:min-h-[700px] lg:flex-row lg:gap-24">
                    <div class="relative w-full lg:w-1/2">
                        <div class="relative overflow-hidden">
                            <img src="{{ $dean['image'] ?? ($faculty['heroImage'] ?? '/images/uni-main-place.JPG') }}" alt="" class="w-full object-top">
                            <div class="absolute bottom-[10%] rounded-md bg-white px-6 py-4 text-center text-[#1e2652] shadow-2xl shadow-black/10 ltr:left-4 rtl:right-4">
                                <h5 class="text-[24px] font-bold">{{ $dean['name'] ?? ($isAr ? ($dean['nameAr'] ?? '') : ($dean['nameEn'] ?? '')) }}</h5>
                                <p class="text-[16px] font-normal">{{ $dean['role'] ?? ($isAr ? ($dean['roleAr'] ?? __('public.faculty_dean')) : ($dean['roleEn'] ?? __('public.faculty_dean'))) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative w-full lg:w-1/2">
                        <div class="absolute -top-12 mb-6 inline-flex items-center gap-3 lg:-top-16">
                            <div class="h-px w-10" style="background-color: {{ $accent }}"></div>
                            <span class="text-[12px] font-black uppercase tracking-[0.4em]" style="color: {{ $accent }}">{{ __('public.dean_message') }}</span>
                        </div>
                        <h2 class="mb-8 text-3xl font-bold leading-tight text-slate-900 md:text-5xl">
                            <span>{{ __('public.message_from') }}</span>
                            <span style="color: {{ $accent }}">{{ $dean['role'] ?? ($isAr ? ($dean['roleAr'] ?? 'عميد الكلية') : ($dean['roleEn'] ?? 'Faculty Dean')) }}</span>
                        </h2>
                        <p class="text-lg font-normal leading-relaxed text-[#475467] md:text-2xl">
                            {{ $dean['message'] ?? ($isAr ? ($dean['messageAr'] ?? '') : ($dean['messageEn'] ?? '')) }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 z-10 w-full bg-white md:h-[70px]"></div>
        </section>
    @endif

    @if (! empty($page->stats))
        <section id="stats" class="relative z-20 font-hacen">
            <div class="bg-spu-blue">
                <div class="container">
                    @php($_statsCount = count($page->stats))
                    @php($_statsCols = $_statsCount >= 4 ? 'cms-grid-stats-cols-4' : ($_statsCount === 3 ? 'cms-grid-stats-cols-3' : ($_statsCount === 2 ? 'cms-grid-stats-cols-2' : '')))
                    <div class="cms-grid-stats {{ $_statsCols }} gap-4">
                        @foreach ($page->stats as $stat)
                            <div class="p-8 text-center md:p-10">
                                @if (! empty($stat['icon']))
                                    <div class="mb-4 flex justify-center">
                                        <img src="{{ $stat['icon'] }}" class="h-10 w-10 brightness-0 invert" alt="" aria-hidden="true">
                                    </div>
                                @endif
                                <div class="mb-1 text-3xl font-black text-white" dir="ltr">{{ $stat['value'] ?? '' }}</div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/50 md:text-xs">{{ $stat['label'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @include('public.faculties.partials.latest-research', [
        'latestResearch' => $latestResearch,
        'locale' => $locale,
        'accent' => $accent,
        'sectionId' => 'latest-research',
    ])

    @include('public.faculties.partials.highlights', ['highlights' => $page->highlights, 'faculty' => $faculty, 'navigationItems' => $page->navigation])
