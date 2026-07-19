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
             class="absolute inset-0"
             role="group"
             aria-roledescription="{{ __('public.slide') }}"
             :aria-label="slideLabel({{ $loop->index }})"
             :aria-hidden="isHidden({{ $loop->index }})">
            <img src="{{ $img }}" alt="{{ $section->payload->title }}" class="h-full w-full object-cover" @if ($loop->first) fetchpriority="high" @else loading="lazy" decoding="async" @endif width="1920" height="800">
        </div>
    @endforeach

    <div class="home-hero__overlay" aria-hidden="true"></div>
    <div class="home-hero__ambient" aria-hidden="true"></div>

    <div class="container relative z-10">
        <div class="home-hero__inner">
            <div class="home-hero__content">
                @if ($section->payload->eyebrow)
                    <p class="home-hero__eyebrow transition duration-700 ease-out" :class="visibleClass()">
                        <span class="home-hero__eyebrow-line" aria-hidden="true"></span>
                        <span>{{ $section->payload->eyebrow }}</span>
                    </p>
                @endif

                @if ($section->payload->badge)
                    <span class="home-hero__badge">{{ $section->payload->badge }}</span>
                @endif

                <h1 class="home-hero__title transition duration-700 ease-out delay-75" :class="visibleClass()">{{ $section->payload->title }}</h1>

                @if ($section->payload->subtitle)
                    <p class="home-hero__summary transition duration-700 ease-out delay-150" :class="visibleClass()">{{ $section->payload->subtitle }}</p>
                @endif

                @if ($section->payload->summary)
                    <p class="home-hero__summary transition duration-700 ease-out delay-150" :class="visibleClass()">{{ $section->payload->summary }}</p>
                @elseif ($section->payload->body)
                    <p class="home-hero__summary transition duration-700 ease-out delay-150" :class="visibleClass()">{{ $section->payload->body }}</p>
                @endif

                @if ($section->payload->primaryAction || $section->payload->secondaryAction)
                    <div class="home-hero__actions transition duration-700 ease-out delay-200" :class="visibleClass()">
                        @if ($section->payload->primaryAction)
                            <a href="{{ $section->payload->primaryAction->url }}" class="home-hero__primary-btn rounded-[8px]" @if ($section->payload->primaryAction->target) target="{{ $section->payload->primaryAction->target }}" rel="noreferrer" @endif>
                                <span>{{ $section->payload->primaryAction->label }}</span>
                                <img src="/images/icon-arrow-right-outline.svg" class="w-3.5 h-3.5 brightness-0 invert rtl:rotate-180" alt="">
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

    @if (count($images) > 1)
        <div class="absolute inset-x-0 bottom-6 z-20 flex items-center justify-center gap-3" aria-label="{{ __('public.carousel_controls') }}">
            <button type="button" @click="manualPrevious()" class="flex h-10 w-10 items-center justify-center rounded-full border border-white/40 bg-spu-blue/50 transition hover:bg-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" aria-label="{{ __('public.previous') }}">
                <img src="/images/icon-chevron-left-outline.svg" class="h-3.5 w-3.5 brightness-0 invert rtl:rotate-180" alt="">
            </button>
            @foreach ($images as $image)
                <button type="button" @click="goTo({{ $loop->index }})" class="h-2 rounded-full bg-white transition-all" :class="dotClass({{ $loop->index }})" :aria-label="slideLabel({{ $loop->index }})" :aria-current="isCurrent({{ $loop->index }})"></button>
            @endforeach
            <button type="button" @click="manualNext()" class="flex h-10 w-10 items-center justify-center rounded-full border border-white/40 bg-spu-blue/50 transition hover:bg-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" aria-label="{{ __('public.next') }}">
                <img src="/images/icon-chevron-right-outline.svg" class="h-3.5 w-3.5 brightness-0 invert rtl:rotate-180" alt="">
            </button>
        </div>
    @endif
</section>
