@php($subPageUrl = $subPage['link'] ?? null)

@if ($subPageUrl && $subPageUrl !== '#')
    <a href="/{{ $locale }}{{ $subPageUrl }}" class="group relative flex h-[118px] flex-col items-center justify-center overflow-hidden rounded-[6px] border border-slate-200/70 bg-white p-6 text-center shadow transition-colors duration-300 hover:bg-spu-blue hover:text-white focus:bg-spu-blue focus:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue">
        <h3 class="text-[17px] font-bold leading-tight">{{ $subPage['title'] ?? '' }}</h3>
        <span class="mt-5 text-[8px] font-bold uppercase tracking-[0.22em] text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus:opacity-100">{{ $locale === 'ar' ? 'استكشف ←' : 'Explore →' }}</span>
    </a>
@else
    <div class="relative flex h-[118px] items-center justify-center overflow-hidden rounded-[6px] border border-slate-200/70 bg-white p-6 text-center">
        <h3 class="text-[17px] font-bold leading-tight text-slate-950">{{ $subPage['title'] ?? '' }}</h3>
    </div>
@endif
