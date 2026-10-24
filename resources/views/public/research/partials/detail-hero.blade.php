@php
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $image = $image ?? '/images/uni-main-place.JPG';
    $eyebrow = $eyebrow ?? null;
@endphp

<section class="bg-white font-hacen" dir="{{ $direction }}">
    <div class="relative h-[50vh] min-h-[350px] overflow-hidden">
        <img src="{{ $image }}" alt="" class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-spu-blue/60"></div>
        <div class="container relative z-10 flex h-full items-end pb-12">
            <div>
                <nav class="mb-4 flex flex-wrap items-center gap-2 text-[12px] text-white/60" aria-label="Breadcrumb">
                    <a href="/{{ $locale }}" class="hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                    <span>/</span>
                    <a href="/{{ $locale }}/research" class="hover:text-white">{{ $locale === 'ar' ? 'البحث' : 'Research' }}</a>
                    @isset($parentUrl)
                        <span>/</span>
                        <a href="{{ $parentUrl }}" class="hover:text-white">{{ $parentLabel ?? '' }}</a>
                    @endisset
                </nav>
                @if ($eyebrow)
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-white/80">{{ $eyebrow }}</p>
                @endif
                <h1 class="mt-2 max-w-[900px] text-[28px] font-bold text-white md:text-[36px]">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-2 text-[14px] text-white/80">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
