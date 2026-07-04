@php
    $hero = $hero ?? [];
    $crumbs = $hero['breadcrumbs'] ?? [];
@endphp

<section class="bg-white font-hacen" dir="{{ $direction }}">
    <div class="relative h-[60vh] min-h-[400px] overflow-hidden">
        <img src="{{ $hero['backgroundImage'] ?? '/images/uni-main-place.JPG' }}" alt="SPU Campus" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-spu-blue/60"></div>
        <div class="container relative z-10 flex h-full items-center justify-center">
            <div class="text-center">
                @if (! empty($hero['eyebrow']))
                    <p class="text-[12px] font-bold uppercase tracking-[0.16em] text-white/80">{{ $hero['eyebrow'] }}</p>
                @endif
                <h1 class="mt-3 text-[32px] font-bold text-white md:text-[42px]">{{ $hero['title'] ?? '' }}</h1>
                @if (! empty($hero['summary']))
                    <p class="mx-auto mt-4 max-w-[600px] text-[14px] leading-[1.6] text-white/90">{{ $hero['summary'] }}</p>
                @endif
                @if ($crumbs !== [])
                    <nav class="mt-6 flex flex-wrap items-center justify-center gap-2 text-[12px] text-white/70" aria-label="Breadcrumb">
                        @foreach ($crumbs as $crumb)
                            <span class="flex items-center gap-2">
                                <a href="{{ $crumb['url'] ?? '#' }}" class="hover:text-white">{{ $crumb['label'] ?? '' }}</a>
                                @if (! $loop->last)
                                    <span>/</span>
                                @endif
                            </span>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </div>
</section>
