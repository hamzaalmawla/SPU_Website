@php
    $content      = $page->content;
    $hero         = $content['hero'] ?? [];
    $facts        = $content['facts'] ?? [];
    $model        = $content['model'] ?? [];
    $facultyLinks = collect($content['facultyLinks'] ?? [])->filter(fn ($item) => is_array($item))->values();
    $isAr         = $locale === 'ar';
    $faculties    = $page->faculties; // FacultyCardDTO collection (slug / heroImage / logoImage / accent / years)
    $totalFac     = max(count((array) $faculties), 1);
    $gridFaculties = $page->faculties;
    $defaultPanorama = (string) ($hero['image'] ?? '/images/campus-feature-01.webp');
    $navBlue      = '#1e2652';
    $spuRed       = '#8a1c1c';

    // Per-faculty bespoke icon + panoramic gallery image (keyed by faculty slug).
    $iconMap = [
        'medicine'                      => '/images/icons/hospital.svg',
        'dentistry'                     => '/images/icons/clinic.svg',
        'pharmacy'                      => '/images/icons/lab.svg',
        'artificial-intelligence'       => '/images/icons/ai.svg',
        'building-construction-engineering' => '/images/icons/bim.svg',
        'petroleum'                     => '/images/icons/oil.svg',
        'business-administration'       => '/images/icons/business.svg',
    ];
    $panoramaMap = [
        'medicine'                      => '/images/campus-hospital.webp',
        'dentistry'                     => '/images/campus-dental.webp',
        'pharmacy'                      => '/images/research-pharmaceutical-sciences.webp',
        'artificial-intelligence'       => '/images/research-applied-ai.webp',
        'building-construction-engineering' => '/images/research-smart-construction.webp',
        'petroleum'                     => '/images/dsc-1075.webp',
        'business-administration'       => '/images/dsc-1060.webp',
    ];
    $defaultIcon = '/images/icon-university-outline.svg';

    // Bilingual UI strings for the new explorer section
    $ui = $isAr ? [
        'eyebrow'     => 'سبع كليات. مسارات لا محدودة.',
        'heading'     => 'المرافق الأكاديمية',
        'subheading'  => 'اختر الكلية التي تناسب طموحك. كل مسار أكاديمي يجمع بين التعليم التطبيقي والبحث والتجربة المهنية.',
        'learnMore'   => 'اعرف المزيد',
        'searchPh'    => 'ابحث عن كلية…',
        'explore'     => 'استكشف الكلية',
        'years'       => 'سنوات الدراسة',
        'campus'      => 'استكشف خريطة الحرم',
        'apply'       => 'قدّم الآن',
        'scroll'      => 'مرّر للاستكشاف',
    ] : [
        'eyebrow'     => 'Seven faculties. Endless pathways.',
        'heading'     => 'Academic Facilities',
        'subheading'  => 'Choose the faculty that fits your ambition. Every academic path blends applied learning, research, and professional experience.',
        'learnMore'   => 'Learn More',
        'searchPh'    => 'Search a faculty…',
        'explore'     => 'Explore faculty',
        'years'       => 'Years of study',
        'campus'      => 'Explore Campus Map',
        'apply'       => 'Apply Now',
        'scroll'      => 'Scroll to explore',
    ];

    // Stats-bar inline icons keyed by display order (values come from CMS `$facts`).
    $statsIcons = ['/images/icons/book.svg', '/images/icon-sitemap-outline.svg', '/images/icons/lab.svg', '/images/icon-users-outline.svg'];
@endphp

{{-- ═══════════════════════════════════════════════════════
     HERO — Diagonal-split Academic Facilities explorer.
     Left: panoramic interactive gallery (cross-fades per faculty on hover).
     Right: floating vertical list of faculty cards (01–07, bespoke icon, "Learn More").
     ═══════════════════════════════════════════════════════ --}}
<section x-data="{ active: null, panel: 0 }"
         class="fac-hub-hero relative overflow-hidden pt-10 font-hacen bg-[#08102b]"
         style="min-height: 88vh;" dir="{{ $direction }}">

    {{-- Panoramic interactive gallery — stacked layers, cross-fade on hover --}}
    <div class="absolute inset-0 z-0">
        {{-- Default: wide architecture view of the main SPU campus --}}
        <img src="{{ $defaultPanorama }}" alt="{{ $ui['heading'] }}"
             class="fac-gallery__layer faculty-hero__image h-full w-full object-cover"
             :class="active === null ? 'opacity-100' : 'opacity-0'">
        @foreach ($faculties as $faculty)
            @php
                $slug  = is_array($faculty) ? ($faculty['slug'] ?? '') : $faculty->slug;
                $pano  = $panoramaMap[$slug] ?? (is_array($faculty) ? ($faculty['heroImage'] ?? null) : $faculty->heroImage) ?: $defaultPanorama;
            @endphp
            <img src="{{ $pano }}" alt=""
                 class="fac-gallery__layer h-full w-full object-cover"
                 :class="active === {{ $loop->index }} ? 'opacity-100' : 'opacity-0'"
                 aria-hidden="true">
        @endforeach

        {{-- Diagonal dark gradient: keeps the right list legible above the gallery --}}
        <div class="absolute inset-0"
             style="background: linear-gradient({{ $isAr ? '255deg' : '105deg' }}, rgba(8,13,44,0.10) 0%, rgba(8,13,44,0.35) 42%, rgba(8,13,44,0.92) 58%, rgba(8,13,44,0.96) 100%);"></div>

        {{-- Sleek metallic diagonal divider line --}}
        <div class="fac-hub-divider hidden lg:block" aria-hidden="true"
             style="{{ $isAr ? 'right' : 'left' }}: 44%; transform: skewX(-{{ $isAr ? '8' : '8' }}deg);"></div>

        {{-- Animated grain for cinematic depth --}}
        <div class="faculty-hero__grain" aria-hidden="true"></div>
    </div>

    {{-- Mobile-only flat dark wash --}}
    <div class="absolute inset-0 z-0 bg-white lg:hidden" aria-hidden="true" style="background: linear-gradient(180deg, rgba(8,13,44,0.55), rgba(8,13,44,0.92));"></div>

    <div class="container relative z-10 flex flex-col lg:flex-row lg:items-stretch" style="min-height: 88vh;">

        {{-- LEFT: panoramic gallery caption space (image shows through) --}}
        <div class="flex w-full lg:w-[46%] flex-col justify-center py-12 lg:py-12 {{ $isAr ? 'lg:items-end text-right' : 'lg:items-start text-left' }}">
            <div class="fac-hub-hero__reveal fac-hub-hero__reveal--d1 max-w-[420px] text-white mx-auto">
                <div class="fac-hub-hero__reveal fac-hub-hero__reveal--d3 mt-8 mb-4 flex flex-wrap gap-3 {{ $isAr ? 'self-start mr-1' : 'self-end' }}">
                    <a href="{{ $hero['applyUrl'] ?? ('/'.$locale.'/admissions/how-to-apply') }}"
                    class="fac-hub-btn-primary inline-flex h-[42px] items-center justify-center rounded-[6px] bg-[#8a1c1c] px-8 text-[14px] font-bold uppercase tracking-[0.14em] text-white transition-colors hover:bg-[#6b1515]">
                        {{ $hero['applyLabel'] ?? $ui['apply'] }}
                    </a>
                    <a href="#faculties-explorer"
                    class="inline-flex h-[42px] items-center justify-center rounded-[6px] border border-white/40 px-6 text-[14px] font-bold uppercase tracking-[0.14em] text-white transition hover:bg-white/10">
                        {{ $hero['campusMapLabel'] ?? $ui['campus'] }}
                    </a>
                </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-[12px] font-bold uppercase tracking-[0.25em] text-white/85 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#e2b864]"></span>
                    {{ $ui['eyebrow'] }}
                </span>
                <p class="mt-4 text-[14px] leading-relaxed text-white/75 lg:text-white/70">
                    {{ $hero['summary'] ?? $ui['subheading'] }}
                </p>
                
            </div>
        </div>

        {{-- RIGHT: Global title + floating vertical faculty list --}}
        <div class="flex w-full lg:w-[54%] flex-col py-10 lg:py-16 items-start">

            {{-- Global title above the list --}}
            <div class="fac-hub-hero__reveal fac-hub-hero__reveal--d2 mb-6 lg:mb-8 w-full max-w-[620px] {{ $isAr ? 'text-right' : 'text-left' }}">
                <h1 class="text-[clamp(2.1rem,3.4vw,3rem)] font-black uppercase tracking-[0.04em] leading-[1.05] text-white drop-shadow-[0_2px_18px_rgba(0,0,0,0.45)]">
                    {{ $hero['title'] ?? $ui['heading'] }}
                </h1>
                <span class="fac-hub-hero__accent-line mt-4 block h-[3px] w-0 rounded-full bg-[#e2b864]" aria-hidden="true"></span>
            </div>

            {{-- Floating staircase list — same-width cards starting from the beginning edge, stepping inward --}}
            <div class="fac-hub-stairs flex w-full max-w-[620px] flex-col gap-2.5">
                @foreach ($faculties as $faculty)
                    @php
                        $title  = is_array($faculty) ? ($faculty['title'] ?? '')   : $faculty->title;
                        $summary= is_array($faculty) ? ($faculty['summary'] ?? '') : $faculty->summary;
                        $url    = is_array($faculty) ? ($faculty['url'] ?? '#')    : $faculty->url;
                        $slug   = is_array($faculty) ? ($faculty['slug'] ?? '')   : $faculty->slug;
                        $logo   = is_array($faculty) ? null : $faculty->logoImage;
                        $accent = (string) ((is_array($faculty) ? ($faculty['accentColor'] ?? null) : $faculty->accentColor) ?: $navBlue);
                        $icon   = $iconMap[$slug] ?? $defaultIcon;
                        $idx    = $loop->index;
                        // Staircase — each card indents progressively from the beginning edge
                        // (left in LTR, right in RTL). All cards keep the same width.
                        $stairStep   = 35;
                        $stairIndent = $stairStep * $idx;
                        $indentSide  = $isAr ? 'margin-right' : 'margin-left';
                    @endphp

                    <a href="{{ $url }}"
                       @mouseenter="active = {{ $idx }}" @mouseleave="active = null"
                       @focusin="active = {{ $idx }}" @focusout="active = null"
                       class="fac-hub-card fac-hub-card--stair group flex w-full items-center gap-3.5 rounded-xl bg-white px-3.5 py-3 text-[#1e2652] ring-1 ring-[#e5e7eb] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_44px_rgba(0,0,0,0.12)] lg:py-3.5 lg:px-4"
                       style="--accent: {{ $accent }}; {{ $indentSide }}: {{ $stairIndent }}px;"
                       aria-label="{{ $title }}">

                        {{-- Sequence number 01–07 --}}
                        <span class="fac-hub-card__num shrink-0 text-[12px] font-black tracking-tight" style="color: {{ $accent }};">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>

                        {{-- Bespoke icon badge --}}
                        <span class="fac-hub-card__icon shrink-0 flex h-11 w-11 items-center justify-center rounded-lg ring-1 ring-[#e5e7eb] transition-colors"
                              style="background-color: {{ $accent }}1f;">
                            @if (! empty($icon))
                                <img src="{{ $icon }}" alt="" class="h-6 w-6" style="filter: brightness(0) saturate(100%) invert(15%) sepia(20%) saturate(1200%) hue-rotate(200deg);">
                            @elseif (! empty($logo))
                                <img src="{{ $logo }}" alt="" class="h-7 w-7 object-contain">
                            @endif
                        </span>

                        {{-- Faculty name + summary --}}
                        <span class="flex min-w-0 flex-1 flex-col {{ $isAr ? 'text-right' : 'text-left' }}">
                            <span class="truncate text-[15px] lg:text-[16px] font-bold leading-tight transition-colors">
                                {{ $title }}
                            </span>
                            @if (! empty($summary))
                                <span class="mt-0.5 line-clamp-1 text-[12px] text-[#6b7280] group-hover:text-[#4b5563] transition-colors">{{ $summary }}</span>
                            @endif
                        </span>

                        {{-- "Learn More" arrow --}}
                        <span class="fac-hub-card__cta shrink-0 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.12em] opacity-100 transition-all group-hover:gap-2.5"
                              style="color: {{ $accent }};">
                            <span class="hidden sm:inline">{{ $ui['learnMore'] }}</span>
                            <svg class="h-4 w-4 {{ $isAr ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Primary CTAs --}}
            
        </div>
    </div>

    {{-- Scroll hint --}}
   
</section>

{{-- ═══════════════════════════════════════════════════════
     FLOATING STATS BAR — glassmorphism, overlaps the hero.
     ═══════════════════════════════════════════════════════ --}}
@if (! empty($facts))
<section class="relative z-20 font-hacen" dir="{{ $direction }}">
    <div class="container -mt-10 lg:-mt-14">
        <div class=" bg-spu-blue mx-auto grid max-w-5xl grid-cols-2 gap-px overflow-hidden rounded-2xl text-white shadow-[0_24px_60px_rgba(4,7,24,0.45)] md:grid-cols-4 lg:max-w-6xl">
            @foreach ($facts as $fact)
                @php
                    $value = (string) ($fact['value'] ?? '');
                    $icon  = $statsIcons[$loop->index] ?? null;
                @endphp
                <div class="group flex flex-col items-center justify-center gap-2 px-4 py-7 text-center transition-colors hover:bg-white/[0.08]">
                    @if (! empty($icon))
                        <img src="{{ $icon }}" alt="" class="h-6 w-6 opacity-85 transition-transform group-hover:scale-110">
                    @endif
                    <span class="text-[1.9rem] lg:text-[2.3rem] font-black leading-none text-white" dir="ltr">
                        {{ str_ends_with($value, '+') ? substr($value, 0, -1) : $value }}@if(str_ends_with($value, '+'))<span class="text-[#e2b864]">+</span>@endif
                    </span>
                    <span class="text-[10px] lg:text-[11px] font-bold uppercase tracking-[0.16em] text-white/60">
                        {{ $fact['label'] ?? '' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ═══════════════════════════════════════════════════════
     ACADEMIC MODEL — centered bento
     ═══════════════════════════════════════════════════════ --}}
@if (! empty($model))
<section id="faculties-overview" class="bg-[#fcfdfd] py-20 lg:py-28 font-hacen" dir="{{ $direction }}">
    <div class="container">

        <div class="mb-16 text-center">
            <h2 class="mx-auto max-w-[700px] text-[1.8rem] lg:text-[2.4rem] font-bold leading-[1.2] tracking-tight text-[#1e2652]">
                {{ $model['title'] ?? ($isAr ? 'نموذج أكاديمي مبني حول الممارسة والبحث' : 'An Academic Model Built Around Practice and Research') }}
            </h2>
        </div>

        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 md:grid-cols-3">
            @foreach (($model['cards'] ?? []) as $card)
                @php
                    $featured = $loop->first;
                    $isBottomRow = $loop->iteration > 3;
                @endphp
                <article class="flex flex-col items-center text-center rounded-xl p-8 lg:p-10 transition-transform hover:-translate-y-1
                                {{ $featured ? 'bg-[#1e2652] text-white shadow-[0_20px_40px_rgba(30,38,82,0.2)]' : 'bg-white text-[#1e2652] shadow-[0_15px_40px_rgba(0,0,0,0.06)]' }}
                                ">

                    <h3 class="text-[1.2rem] lg:text-[1.3rem] font-bold leading-snug {{ $featured ? 'text-white' : 'text-[#1e2652]' }}">
                        {{ $card['title'] ?? '' }}
                    </h3>
                    <p class="mt-4 text-[14px] leading-relaxed {{ $featured ? 'text-white/80' : 'text-[#4b5563]' }}">
                        {{ $card['summary'] ?? '' }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif