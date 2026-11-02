@php($images = $section->payload->content['images'] ?? [])

<section x-data="heroSlider()"
         data-images="{{ json_encode($images, JSON_THROW_ON_ERROR) }}"
         data-slide-label="{{ __('public.show_slide') }}"
         class="home-hero relative overflow-hidden font-hacen bg-spu-blue text-white z-10"
         role="region"
         aria-roledescription="{{ __('public.carousel') }}"
         aria-label="{{ $section->payload->title }}"
         tabindex="0"
         @keydown="handleKey($event)"
         @mouseenter="stopAuto()"
         @mouseleave="startAuto()"
         @focusin="stopAuto()"
         @focusout="resumeAuto($event)">
    @foreach ($images as $img)
        <div x-show="isCurrent({{ $loop->index }})"
             x-transition:enter="transition ease-out duration-1000"
             x-transition:enter-start="opacity-0 transform scale-105"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 w-full h-full overflow-hidden transition-all duration-[1000ms] [transition-timing-function:cubic-bezier(0.25,1,0.5,1)]"
             role="group"
             aria-roledescription="{{ __('public.slide') }}"
             :aria-label="slideLabel({{ $loop->index }})"
             :aria-hidden="isHidden({{ $loop->index }})"
             {{-- Hidden from the first byte, not from when Alpine starts: until
                  then every slide sat in the viewport, so loading="lazy" did
                  nothing and slides 2-4 downloaded alongside the stylesheet and
                  slide 1. x-show takes over the display property once it runs. --}}
             @unless ($loop->first) style="display: none;" @endunless>
            {{-- Slides cover a box that is taller than wide on a phone (the hero is
                 at least 46rem high), so the rendered width follows the height:
                 a 3:2 photo needs about 1.5x the hero's height. 100vw would pick
                 a phone-width file and blur it. --}}
            @php($slideSrcset = \App\Support\MediaUrlResolver::legacySrcset($img))
            <img src="{{ $img }}" @if ($slideSrcset) srcset="{{ $slideSrcset }}" sizes="max(100vw, calc(1.5 * clamp(46rem, 100svh, 58rem)))" @endif alt="{{ $loop->first ? $section->payload->title : ($section->payload->slideAlts[$loop->index] ?? $section->payload->title.' '.($loop->index + 1)) }}" class="h-full w-full object-cover" data-hero-slide @if ($loop->first) fetchpriority="high" @else loading="lazy" fetchpriority="low" decoding="async" @endif width="1920" height="800">
        </div>
    @endforeach

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
                            <a href="{{ $section->payload->primaryAction->url }}" class="home-hero__primary-btn rounded-[8px]" @if ($section->payload->primaryAction->target) target="{{ $section->payload->primaryAction->target }}" rel="noreferrer" @endif>
                                <span>{{ $section->payload->primaryAction->label }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" class="w-3.5 h-3.5 brightness-0 invert rtl:rotate-180" alt="" width="24" height="24" decoding="async">
                            </a>
                        @endif

                        @if ($section->payload->secondaryAction)
                            <a href="{{ $section->payload->secondaryAction->url }}" class="home-hero__secondary-btn rounded-[8px]" @if ($section->payload->secondaryAction->target) target="{{ $section->payload->secondaryAction->target }}" rel="noreferrer" @endif>
                                <span>{{ $section->payload->secondaryAction->label }}</span>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
