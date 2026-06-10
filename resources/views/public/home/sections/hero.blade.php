@php($images = $section->payload->content['images'] ?? [])

<section x-data="heroSlider()" data-images="{{ json_encode($images, JSON_THROW_ON_ERROR) }}" class="home-hero relative overflow-hidden font-hacen bg-spu-blue text-white z-10">
    @foreach ($images as $img)
        <div x-show="isCurrent({{ $loop->index }})"
             x-transition:enter="transition ease-out duration-1000"
             x-transition:enter-start="opacity-0 transform scale-105"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0">
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
</section>
